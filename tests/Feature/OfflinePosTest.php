<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ChartAccount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Treasury;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflinePosTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('manager');
        $this->warehouse = Warehouse::create([
            'name' => 'Main store',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'General']);
        $this->product = Product::create([
            'name' => 'Test product',
            'sku' => 'TEST-001',
            'barcode' => '1234567890123',
            'cost_price' => '500.00',
            'selling_price' => '750.25',
            'category_id' => $category->id,
        ]);
        $this->warehouse->products()->attach($this->product->id, [
            'quantity' => 5,
            'shelf_number' => 'A1',
        ]);

        $this->actingAs($this->cashier);
    }

    public function test_pos_page_and_barcode_lookup_are_available_to_sales_role(): void
    {
        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee('امسح الباركود')
            ->assertSee('إتمام البيع وطباعة الإيصال')
            ->assertSee('تطبيق بنكي')
            ->assertSee('إعدادات نقطة البيع');

        config(['app.env' => 'local']);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('نقطة البيع')
            ->assertSee('إعدادات نقطة البيع');

        $this->getJson(route('pos.products', [
            'warehouse_id' => $this->warehouse->id,
            'barcode' => $this->product->barcode,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->product->id)
            ->assertJsonPath('data.0.stock', 5)
            ->assertJsonPath('data.0.price', '750.25');
    }

    public function test_checkout_records_sale_snapshots_and_decrements_inventory_once(): void
    {
        $requestKey = (string) Str::uuid();
        $payload = $this->checkoutPayload($requestKey, 2);

        $response = $this->postJson(route('pos.checkout'), $payload)
            ->assertCreated()
            ->assertJsonPath('total', '1500.50');

        $this->assertDatabaseHas('sales', [
            'request_key' => $requestKey,
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'total' => '1500.50',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $this->product->id,
            'product_name' => 'Test product',
            'quantity' => 2,
            'unit_price' => '750.25',
            'line_total' => '1500.50',
            'unit_cost' => '500.00',
        ]);

        $this->assertSame(3, (int) $this->warehouse->products()->first()->pivot->quantity);
        $this->assertSame(1, \App\Models\JournalEntry::query()->count());
        $this->assertSame('1500.50', \App\Models\Treasury::query()->where('code', 'CASH-MAIN')->firstOrFail()->balance());
        $this->assertDatabaseHas('treasury_transactions', [
            'treasury_id' => \App\Models\Treasury::query()->where('code', 'CASH-MAIN')->value('id'),
            'type' => 'cash_in',
            'amount' => '1500.50',
        ]);

        $this->postJson(route('pos.checkout'), $payload)
            ->assertOk()
            ->assertJsonPath('receipt_number', $response->json('receipt_number'));

        $this->assertSame(1, Sale::count());
        $this->assertSame(3, (int) $this->warehouse->products()->first()->pivot->quantity);

        $this->get($response->json('receipt_url'))
            ->assertOk()
            ->assertSee('فاتورة بيع — نسخة العميل')
            ->assertSee('Test product');
        $this->get(route('pos.sales'))
            ->assertOk()
            ->assertSee($response->json('receipt_number'))
            ->assertSee('نسخة المخزن');
    }

    public function test_checkout_rejects_insufficient_stock_without_creating_a_sale(): void
    {
        $this->postJson(route('pos.checkout'), $this->checkoutPayload((string) Str::uuid(), 6))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, Sale::count());
        $this->assertSame(5, (int) $this->warehouse->products()->first()->pivot->quantity);
    }

    public function test_mixed_cash_and_bank_app_payment_posts_to_separate_treasuries_and_renders_invoices(): void
    {
        $bankTreasury = Treasury::create([
            'code' => 'BANK-APP',
            'name' => 'حساب التطبيق البنكي',
            'account_id' => ChartAccount::query()->where('system_key', 'cash_at_bank')->value('id'),
            'currency' => 'IQD',
            'is_active' => true,
        ]);
        $cashTreasury = Treasury::query()->where('code', 'CASH-MAIN')->firstOrFail();
        $payload = array_merge($this->checkoutPayload((string) Str::uuid(), 2), [
            'customer_name' => 'عميل تجريبي',
            'customer_phone' => '07700000000',
            'payment_method' => 'mixed',
            'cash_amount' => '700.25',
            'bank_amount' => '800.25',
            'treasury_id' => $cashTreasury->id,
            'bank_treasury_id' => $bankTreasury->id,
        ]);

        $response = $this->postJson(route('pos.checkout'), $payload)
            ->assertCreated()
            ->assertJsonStructure(['receipt_number', 'receipt_url', 'warehouse_invoice_url']);
        $sale = Sale::query()->where('request_key', $payload['request_key'])->firstOrFail();

        $this->assertSame('700.25', $sale->cash_amount);
        $this->assertSame('800.25', $sale->bank_amount);
        $this->assertSame($cashTreasury->id, $sale->cash_treasury_id);
        $this->assertSame($bankTreasury->id, $sale->bank_treasury_id);
        $this->assertDatabaseHas('treasury_transactions', [
            'treasury_id' => $cashTreasury->id,
            'amount' => '700.25',
            'description' => 'تحصيل بيع '.$sale->receipt_number.' نقداً',
        ]);
        $this->assertDatabaseHas('treasury_transactions', [
            'treasury_id' => $bankTreasury->id,
            'amount' => '800.25',
            'description' => 'تحصيل بيع '.$sale->receipt_number.' عبر التطبيق البنكي',
        ]);

        $this->get($response->json('receipt_url'))
            ->assertOk()
            ->assertSee('فاتورة بيع — نسخة العميل')
            ->assertSee('عميل تجريبي')
            ->assertSee($this->product->barcode)
            ->assertSee('باركود الفاتورة')
            ->assertSee('باركود الفاتورة: '.$sale->receipt_number)
            ->assertSee('750.25')
            ->assertSee('700.25')
            ->assertSee('800.25')
            ->assertSee('<svg', false);

        $this->get($response->json('warehouse_invoice_url'))
            ->assertOk()
            ->assertSee('نسخة المخزن — مستند صرف مرتبط بفاتورة بيع')
            ->assertSee($this->warehouse->name)
            ->assertSee($sale->receipt_number);
    }

    public function test_bank_app_payment_is_posted_to_the_selected_bank_treasury(): void
    {
        $bankTreasury = Treasury::create([
            'code' => 'BANK-ONLY',
            'name' => 'حساب التحويل البنكي',
            'account_id' => ChartAccount::query()->where('system_key', 'cash_at_bank')->value('id'),
            'currency' => 'IQD',
            'is_active' => true,
        ]);
        $payload = array_merge($this->checkoutPayload((string) Str::uuid(), 1), [
            'payment_method' => 'bank_app',
            'bank_treasury_id' => $bankTreasury->id,
        ]);

        $response = $this->postJson(route('pos.checkout'), $payload)
            ->assertCreated();
        $sale = Sale::query()->where('request_key', $payload['request_key'])->firstOrFail();

        $this->assertSame('0.00', $sale->cash_amount);
        $this->assertSame('750.25', $sale->bank_amount);
        $this->assertNull($sale->cash_treasury_id);
        $this->assertSame($bankTreasury->id, $sale->bank_treasury_id);
        $this->get($response->json('receipt_url'))
            ->assertOk()
            ->assertSee('تطبيق بنكي')
            ->assertSee('حساب التحويل البنكي');
    }

    public function test_mixed_payment_amount_must_equal_invoice_total(): void
    {
        $bankTreasury = Treasury::create([
            'code' => 'BANK-APP',
            'name' => 'حساب التطبيق البنكي',
            'account_id' => ChartAccount::query()->where('system_key', 'cash_at_bank')->value('id'),
            'currency' => 'IQD',
            'is_active' => true,
        ]);

        $this->postJson(route('pos.checkout'), array_merge(
            $this->checkoutPayload((string) Str::uuid(), 1),
            [
                'payment_method' => 'mixed',
                'cash_amount' => '100.00',
                'bank_amount' => '100.00',
                'bank_treasury_id' => $bankTreasury->id,
            ],
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(5, (int) $this->warehouse->products()->first()->pivot->quantity);
    }

    public function test_user_without_sales_permission_cannot_open_pos(): void
    {
        $this->cashier->syncRoles('user');

        $this->get(route('pos.index'))->assertForbidden();
    }

    public function test_pos_settings_can_be_saved_and_used_by_the_receipt(): void
    {
        $this->get(route('pos.settings'))
            ->assertOk()
            ->assertSee('عرض ورق الطابعة الحرارية');

        $this->put(route('pos.settings.update'), [
            'store_name' => 'متجر تجريبي',
            'store_address' => 'بغداد',
            'store_phone' => '07700000000',
            'receipt_footer' => 'أهلاً بكم',
            'paper_width_mm' => '58',
            'auto_print' => '0',
            'default_warehouse_id' => $this->warehouse->id,
        ])->assertRedirect(route('pos.settings'));

        $this->assertDatabaseHas('system_settings', [
            'key' => 'pos.store_name',
            'value' => 'متجر تجريبي',
            'group' => 'pos',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'pos.auto_print',
            'value' => '0',
        ]);

        $saleResponse = $this->postJson(route('pos.checkout'), $this->checkoutPayload((string) Str::uuid(), 1))
            ->assertCreated();

        $this->get($saleResponse->json('receipt_url'))
            ->assertOk()
            ->assertSee('متجر تجريبي')
            ->assertSee('بغداد')
            ->assertSee('أهلاً بكم')
            ->assertSee('size: 58mm auto')
            ->assertDontSee("window.addEventListener('load'");
    }

    public function test_users_without_settings_permission_cannot_view_or_change_pos_settings(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('user');

        $this->actingAs($cashier)
            ->get(route('pos.settings'))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->put(route('pos.settings.update'), [
                'store_name' => 'Unauthorized',
                'paper_width_mm' => '58',
            ])
            ->assertForbidden();
    }

    private function checkoutPayload(string $requestKey, int $quantity): array
    {
        return [
            'request_key' => $requestKey,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => $quantity,
            ]],
        ];
    }
}
