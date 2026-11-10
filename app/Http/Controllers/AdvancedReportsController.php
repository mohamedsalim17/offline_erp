<?php

namespace App\Http\Controllers;

use App\Exports\AdvancedReportExport;
use App\Models\Warehouse;
use App\Services\AdvancedReportQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AdvancedReportsController extends Controller
{
    public function excel(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $filename = $this->filename($filters).'.xlsx';

        return Excel::download(new AdvancedReportExport($filters['report'], $filters), $filename);
    }

    public function pdf(Request $request, AdvancedReportQueryService $queries)
    {
        $filters = $this->validatedFilters($request);
        $query = $filters['report'] === 'top-products'
            ? $queries->topSellingProducts($filters)
            : $queries->subWarehouseBalances($filters);

        $rowCount = DB::query()->fromSub((clone $query)->reorder(), 'report_rows')->count();

        abort_if(
            $rowCount > 5000,
            422,
            'يتجاوز التقرير 5000 صفاً؛ استخدم Excel أو حدّد فترة زمنية أصغر.',
        );

        $rows = $query->get();
        $title = $filters['report'] === 'top-products'
            ? 'تقرير المنتجات الأعلى مبيعاً'
            : 'تقرير أرصدة المخازن الفرعية';
        $summary = $filters['report'] === 'top-products'
            ? $queries->topSellingSummary($filters)
            : $queries->subWarehouseSummary($filters);

        return Pdf::loadView('reports.advanced-pdf', [
            'rows' => $rows,
            'filters' => $filters,
            'warehouseName' => filled($filters['warehouse_id'] ?? null)
                ? Warehouse::query()->whereKey($filters['warehouse_id'])->value('name')
                : null,
            'title' => $title,
            'summary' => $summary,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->download($this->filename($filters).'.pdf');
    }

    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'report' => ['required', 'in:top-products,warehouse-balances'],
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

        return [
            'report' => $filters['report'],
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'warehouse_id' => $filters['warehouse_id'] ?? null,
        ];
    }

    private function filename(array $filters): string
    {
        $prefix = $filters['report'] === 'top-products' ? 'top-selling-products' : 'sub-warehouse-balances';
        $range = implode('_', array_filter([$filters['from'] ?? null, $filters['to'] ?? null]));

        return $prefix.'-'.($range ?: now()->format('Y-m-d'));
    }
}
