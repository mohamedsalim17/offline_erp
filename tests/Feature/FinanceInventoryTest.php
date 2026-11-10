<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ChartAccount;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\SystemSetting;
use App\Models\Treasury;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use App\Services\StocktakeService;
use App\Services\TreasuryService;
use App\Services\FinancialReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
    }

    public function test_cash_in_expense_and_safe_transfer_post_balanced_entries(): void
    {
        $source = Treasury::query()->where('code', 'CASH-MAIN')->firstOrFail();
        $destination = Treasury::create([
            'code' => 'CASH-SECOND',
            'name' => 'الخزنة الثانية',
            'account_id' => $source->account_id,
            'currency' => 'IQD',
            'is_active' => true,
        ]);
        $equity = $this->account('opening_balance');
        $expense = $this->account('inventory_adjustment');
        $service = app(TreasuryService::class);

        $service->record($source->id, $equity->id, 'cash_in', '1000.00', '2026-11-10', 'رصيد افتتاحي', $this->manager->id);
        $service->record($source->id, $expense->id, 'expense', '150.00', '2026-11-10', 'مصاريف تشغيل', $this->manager->id);
        $service->transfer($source->id, $destination->id, '200.00', '2026-11-10', 'تحويل نقدي', $this->manager->id);

        $this->assertSame('650.00', $source->fresh()->balance());
        $this->assertSame('200.00', $destination->fresh()->balance());
        $this->assertSame(3, DB::table('journal_entries')->count());
        $this->assertSame(
            (float) DB::table('journal_lines')->sum('debit'),
            (float) DB::table('journal_lines')->sum('credit'),
        );
        $this->assertDatabaseHas('treasury_transactions', ['type' => 'transfer', 'destination_treasury_id' => $destination->id]);

        $reports = app(FinancialReportService::class);
        $trialBalance = $reports->trialBalance(['to' => '2026-11-10']);
        $income = $reports->incomeStatement(['from' => '2026-11-10', 'to' => '2026-11-10']);
        $balanceSheet = $reports->balanceSheet(['to' => '2026-11-10']);
        $this->assertEquals($trialBalance['debit_total'], $trialBalance['credit_total']);
        $this->assertEquals(-150.0, $income['net_profit']);
        $this->assertEquals(850.0, $balanceSheet['assets']);
        $this->assertEquals(850.0, $balanceSheet['liabilities_and_equity']);
    }

    public function test_inventory_receipts_use_moving_average_and_transfers_keep_valuation(): void
    {
        [$warehouse, $child, $product] = $this->inventoryFixture();
        $service = app(StockMovementService::class);
        $offset = $this->account('opening_balance');

        foreach ([['5', '10.00'], ['5', '20.00']] as [$quantity, $cost]) {
            $beforeQuantity = (int) DB::table('product_warehouse')->where('warehouse_id', $warehouse->id)->sum('quantity');
            $movement = $service->create([
                'type' => 'receipt',
                'destination_warehouse_id' => $warehouse->id,
                'offset_account_id' => $offset->id,
                'movement_date' => '2026-11-10',
                'reason' => 'توريد مخزون',
                'items' => [['product_id' => $product->id, 'quantity' => (int) $quantity, 'unit_cost' => $cost]],
            ], $this->manager->id);
            $this->assertSame($beforeQuantity, (int) DB::table('product_warehouse')->where('warehouse_id', $warehouse->id)->sum('quantity'));
            $service->approve($movement, $this->manager->id);
        }

        $this->assertSame(10, (int) $warehouse->products()->first()->pivot->quantity);
        $this->assertEquals(15.0, (float) $warehouse->products()->first()->pivot->average_cost);

        $transfer = $service->create([
            'type' => 'transfer',
            'source_warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => $child->id,
            'movement_date' => '2026-11-10',
            'reason' => 'نقل إلى الفرع',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => '0']],
        ], $this->manager->id);
        $service->approve($transfer, $this->manager->id);

        $this->assertSame(8, (int) $warehouse->products()->first()->pivot->quantity);
        $this->assertSame(2, (int) $child->products()->first()->pivot->quantity);
        $this->assertEquals(15.0, (float) $child->products()->first()->pivot->average_cost);
        $this->assertSame(2, DB::table('journal_entries')->count());
    }

    public function test_stocktake_applies_snapshot_variance_to_current_stock_and_posts_adjustment(): void
    {
        [$warehouse, , $product] = $this->inventoryFixture();
        $movementService = app(StockMovementService::class);
        $movementService->approve($movementService->create([
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouse->id,
            'offset_account_id' => $this->account('opening_balance')->id,
            'movement_date' => '2026-11-10',
            'reason' => 'رصيد افتتاحي',
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => '10.00']],
        ], $this->manager->id), $this->manager->id);

        $service = app(StocktakeService::class);
        $stocktake = $service->start($warehouse->id, 'annual', '2026-11-10', 'جرد سنوي', $this->manager->id);
        $item = $stocktake->items->firstOrFail();

        $movementService->approve($movementService->create([
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouse->id,
            'offset_account_id' => $this->account('opening_balance')->id,
            'movement_date' => '2026-11-10',
            'reason' => 'استلام خلال الجرد',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => '10.00']],
        ], $this->manager->id), $this->manager->id);

        $service->saveCounts($stocktake, [$item->id => 4]);
        $service->submit($stocktake);
        $service->approve($stocktake, $this->manager->id);

        $this->assertSame(6, (int) $warehouse->products()->first()->pivot->quantity);
        $this->assertSame('approved', $stocktake->fresh()->status);
        $this->assertSame(3, DB::table('journal_entries')->count());
        $this->assertDatabaseHas('stock_movements', ['id' => $stocktake->fresh()->stock_movement_id, 'type' => 'stocktake']);
    }

    public function test_product_codes_are_generated_and_warehouse_tree_cannot_cycle(): void
    {
        $category = Category::create(['name' => 'اختبار']);
        $product = Product::create([
            'name' => 'منتج تلقائي',
            'sku' => null,
            'barcode' => null,
            'cost_price' => '1.00',
            'selling_price' => '2.00',
            'category_id' => $category->id,
        ]);
        $this->assertNotEmpty($product->sku);
        $this->assertMatchesRegularExpression('/^\d{13}$/', $product->barcode);

        $root = Warehouse::create(['name' => 'الرئيسي']);
        $child = Warehouse::create(['name' => 'الفرع', 'parent_id' => $root->id]);
        $this->expectException(ValidationException::class);
        $root->update(['parent_id' => $child->id]);
    }

    public function test_finance_and_inventory_screens_render_for_authorized_manager(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('treasuries.index'))->assertOk()->assertSee('الخزائن والحركات المالية');
        $this->get(route('stock-movements.index'))->assertOk()->assertSee('طلب حركة جديدة');
        $this->get(route('stocktakes.index'))->assertOk()->assertSee('الجرد الآني والسنوي');
        $this->get(route('finance.reports'))->assertOk()->assertSee('ميزان المراجعة');
        SystemSetting::updateOrCreate(
            ['key' => 'system.locale'],
            ['value' => 'ar', 'type' => 'string', 'group' => 'system', 'is_public' => false],
        );
        $this->get('/admin/chart-accounts')
            ->assertOk()
            ->assertSee('شجرة الحسابات')
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_dashboard_language_switch_changes_page_locale_and_direction(): void
    {
        $this->actingAs($this->manager)
            ->get(route('lang.switch', ['lang' => 'en']))
            ->assertRedirect('/admin');

        $this->get('/admin/chart-accounts')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false);

        $this->get(route('lang.switch', ['lang' => 'ar']))
            ->assertRedirect('/admin');

        $this->get('/admin/chart-accounts')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_products_list_shows_stock_total_across_warehouses(): void
    {
        $category = Category::create(['name' => 'منتجات']);
        $product = Product::create([
            'name' => 'صنف تجريبي',
            'cost_price' => '100.00',
            'selling_price' => '150.00',
            'category_id' => $category->id,
        ]);
        $warehouse = Warehouse::create(['name' => 'مخزن الاختبار']);
        $warehouse->products()->attach($product->id, [
            'quantity' => 12,
            'average_cost' => '100.00',
        ]);

        $this->actingAs($this->manager)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('الكمية بالمخازن')
            ->assertSee('12 قطعة');
    }

    public function test_finance_and_inventory_screens_are_forbidden_without_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user);

        $this->get(route('treasuries.index'))->assertForbidden();
        $this->get(route('stock-movements.index'))->assertForbidden();
        $this->get(route('stocktakes.index'))->assertForbidden();
        $this->get(route('finance.reports'))->assertForbidden();
    }

    public function test_existing_inventory_is_opened_to_ledger_only_once(): void
    {
        SystemSetting::query()->where('key', 'finance.opening_stock_migrated')->delete();
        [$warehouse, , $product] = $this->inventoryFixture();
        $warehouse->products()->attach($product->id, ['quantity' => 4, 'average_cost' => '5.00']);

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->assertSame(1, DB::table('journal_entries')->where('source_type', 'system.opening-inventory')->count());
        $this->assertSame(20.0, (float) DB::table('journal_lines')->where('account_id', $this->account('inventory')->id)->sum('debit'));
    }

    private function inventoryFixture(): array
    {
        $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي']);
        $child = Warehouse::create(['name' => 'مخزن فرعي', 'parent_id' => $warehouse->id]);
        $category = Category::create(['name' => 'عام']);
        $product = Product::create([
            'name' => 'منتج مخزون',
            'sku' => 'STOCK-001',
            'barcode' => '1234567890123',
            'cost_price' => '12.00',
            'selling_price' => '20.00',
            'category_id' => $category->id,
        ]);

        return [$warehouse, $child, $product];
    }

    private function account(string $key): ChartAccount
    {
        return ChartAccount::query()->where('system_key', $key)->firstOrFail();
    }
}
