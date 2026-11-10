<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Exports\AdvancedReportExport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AdvancedReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $mainWarehouse;

    private Warehouse $subWarehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->mainWarehouse = Warehouse::create([
            'name' => 'المخزن الرئيسي',
            'is_active' => true,
        ]);
        $this->subWarehouse = Warehouse::create([
            'name' => 'فرع الكرادة',
            'parent_id' => $this->mainWarehouse->id,
            'location' => 'الكرادة',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'مشروبات']);
        $this->product = Product::create([
            'name' => 'ماء معدني',
            'sku' => 'WATER-01',
            'barcode' => '1234567890123',
            'cost_price' => '500.00',
            'selling_price' => '750.25',
            'category_id' => $category->id,
        ]);

        $this->mainWarehouse->products()->attach($this->product->id, ['quantity' => 3]);
        $this->subWarehouse->products()->attach($this->product->id, ['quantity' => 8]);

        $this->createSale('completed', $this->mainWarehouse, 2);
        $this->createSale('cancelled', $this->mainWarehouse, 20);
    }

    public function test_report_page_displays_top_seller_totals_and_filterable_subwarehouse_balances(): void
    {
        $this->get(route('filament.admin.pages.advanced-reports', [
            'report' => 'top-products',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('ماء معدني')
            ->assertSee('1,500.50')
            ->assertSee('500.50')
            ->assertSee('المنتجات الأعلى مبيعاً');

        $this->get(route('filament.admin.pages.advanced-reports', [
            'report' => 'warehouse-balances',
            'warehouse_id' => $this->mainWarehouse->id,
        ]))
            ->assertOk()
            ->assertSee('فرع الكرادة')
            ->assertSee('4,000.00')
            ->assertSee('6,002.00');
    }

    public function test_excel_export_is_downloadable_with_report_filters(): void
    {
        Excel::fake();

        $this->get(route('reports.excel', [
            'report' => 'top-products',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();

        Excel::assertDownloaded(
            'top-selling-products-'.now()->toDateString().'_'.now()->toDateString().'.xlsx',
        );
    }

    public function test_excel_export_generates_a_real_local_xlsx_archive(): void
    {
        $binary = Excel::raw(
            new AdvancedReportExport('top-products', [
                'report' => 'top-products',
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'warehouse_id' => null,
            ]),
            \Maatwebsite\Excel\Excel::XLSX,
        );

        $this->assertStringStartsWith('PK', $binary);
    }

    public function test_top_selling_report_allows_an_open_ended_date_range(): void
    {
        $this->get(route('filament.admin.pages.advanced-reports', [
            'report' => 'top-products',
            'from' => now()->toDateString(),
        ]))->assertOk();
    }

    public function test_pdf_export_returns_a_local_pdf_document(): void
    {
        $response = $this->get(route('reports.pdf', [
            'report' => 'warehouse-balances',
            'warehouse_id' => $this->mainWarehouse->id,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_users_without_report_permission_cannot_view_or_export_reports(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('filament.admin.pages.advanced-reports'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('reports.excel', ['report' => 'top-products']))
            ->assertForbidden();
    }

    private function createSale(string $status, Warehouse $warehouse, int $quantity): Sale
    {
        $sale = Sale::create([
            'request_key' => (string) \Illuminate\Support\Str::uuid(),
            'receipt_number' => 'REP-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
            'cashier_id' => $this->admin->id,
            'warehouse_id' => $warehouse->id,
            'status' => $status,
            'payment_method' => 'cash',
            'subtotal' => $quantity * 750.25,
            'total' => $quantity * 750.25,
            'currency' => 'IQD',
        ]);

        $sale->items()->create([
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'barcode' => $this->product->barcode,
            'quantity' => $quantity,
            'unit_price' => '750.25',
            'unit_cost' => '500.00',
            'line_total' => $quantity * 750.25,
        ]);

        return $sale;
    }
}
