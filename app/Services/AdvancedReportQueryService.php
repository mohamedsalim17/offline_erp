<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AdvancedReportQueryService
{
    /**
     * Return completed sales grouped by the product details captured at sale time.
     */
    public function topSellingProducts(array $filters): Builder
    {
        return $this->filteredSaleItems($filters)
            ->select([
                'sale_items.product_id',
                'sale_items.product_name',
                'sale_items.sku',
                'sale_items.barcode',
            ])
            ->selectRaw('SUM(sale_items.quantity) as units_sold')
            ->selectRaw('SUM(sale_items.line_total) as gross_sales')
            ->selectRaw('SUM(sale_items.quantity * sale_items.unit_cost) as cost_total')
            ->selectRaw('SUM(sale_items.line_total - (sale_items.quantity * sale_items.unit_cost)) as gross_profit')
            ->groupBy([
                'sale_items.product_id',
                'sale_items.product_name',
                'sale_items.sku',
                'sale_items.barcode',
            ])
            ->orderByDesc('units_sold')
            ->orderByDesc('gross_sales')
            ->orderBy('sale_items.product_name');
    }

    public function topSellingSummary(array $filters): object
    {
        return $this->filteredSaleItems($filters)
            ->selectRaw('COUNT(DISTINCT sale_items.product_id) as products_sold')
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as units_sold')
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(sale_items.line_total - (sale_items.quantity * sale_items.unit_cost)), 0) as gross_profit')
            ->first();
    }

    /**
     * Return each child warehouse, including empty warehouses, with current stock value.
     */
    public function subWarehouseBalances(array $filters): Builder
    {
        $query = DB::table('warehouses as child')
            ->leftJoin('warehouses as parent', 'parent.id', '=', 'child.parent_id')
            ->leftJoin('product_warehouse as stock', 'stock.warehouse_id', '=', 'child.id')
            ->leftJoin('products', 'products.id', '=', 'stock.product_id')
            ->whereNotNull('child.parent_id')
            ->select([
                'child.id as warehouse_id',
                'child.name as warehouse_name',
                'child.location',
                'parent.id as parent_id',
                'parent.name as parent_name',
            ])
            ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(stock.quantity, 0) > 0 THEN stock.product_id END) as products_in_stock')
            ->selectRaw('COALESCE(SUM(stock.quantity), 0) as total_units')
            ->selectRaw('COALESCE(SUM(stock.quantity * COALESCE(NULLIF(stock.average_cost, 0), products.cost_price)), 0) as cost_value')
            ->selectRaw('COALESCE(SUM(stock.quantity * products.selling_price), 0) as retail_value')
            ->groupBy([
                'child.id',
                'child.name',
                'child.location',
                'parent.id',
                'parent.name',
            ]);

        if (filled($filters['warehouse_id'] ?? null)) {
            $query->where('child.parent_id', (int) $filters['warehouse_id']);
        }

        return $query
            ->orderBy('parent.name')
            ->orderBy('child.name');
    }

    public function subWarehouseSummary(array $filters): object
    {
        return DB::query()
            ->fromSub($this->subWarehouseBalances($filters), 'balances')
            ->selectRaw('COUNT(*) as warehouses_count')
            ->selectRaw('COALESCE(SUM(products_in_stock), 0) as products_in_stock')
            ->selectRaw('COALESCE(SUM(total_units), 0) as total_units')
            ->selectRaw('COALESCE(SUM(cost_value), 0) as cost_value')
            ->selectRaw('COALESCE(SUM(retail_value), 0) as retail_value')
            ->first();
    }

    private function filteredSaleItems(array $filters): Builder
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed');

        if (filled($filters['from'] ?? null)) {
            $query->where('sales.created_at', '>=', $filters['from'].' 00:00:00');
        }

        if (filled($filters['to'] ?? null)) {
            $query->where('sales.created_at', '<=', $filters['to'].' 23:59:59');
        }

        if (filled($filters['warehouse_id'] ?? null)) {
            $query->where('sales.warehouse_id', (int) $filters['warehouse_id']);
        }

        return $query;
    }
}
