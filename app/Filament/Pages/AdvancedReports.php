<?php

namespace App\Filament\Pages;

use App\Models\Warehouse;
use App\Services\AdvancedReportQueryService;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AdvancedReports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'التقارير المتقدمة';

    protected static ?string $title = 'التقارير المتقدمة';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.advanced-reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    protected function getViewData(): array
    {
        $filters = request()->validate([
            'report' => ['nullable', Rule::in(['top-products', 'warehouse-balances'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        if (
            filled($filters['from'] ?? null) &&
            filled($filters['to'] ?? null) &&
            $filters['from'] > $filters['to']
        ) {
            throw ValidationException::withMessages([
                'from' => 'يجب أن يكون تاريخ البداية قبل أو مساوياً لتاريخ النهاية.',
            ]);
        }
        $filters['report'] ??= 'top-products';

        $queries = app(AdvancedReportQueryService::class);
        $query = $filters['report'] === 'top-products'
            ? $queries->topSellingProducts($filters)
            : $queries->subWarehouseBalances($filters);

        return [
            'filters' => $filters,
            'rows' => $query->paginate(25)->withQueryString(),
            'summary' => $filters['report'] === 'top-products'
                ? $queries->topSellingSummary($filters)
                : $queries->subWarehouseSummary($filters),
            'warehouses' => Warehouse::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'canExport' => auth()->user()?->can('reports.export') ?? false,
        ];
    }
}
