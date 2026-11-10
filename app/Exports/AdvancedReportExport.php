<?php

namespace App\Exports;

use App\Services\AdvancedReportQueryService;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AdvancedReportExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles, WithEvents
{
    public function __construct(
        private readonly string $report,
        private readonly array $filters,
    ) {}

    public function query(): Builder
    {
        $queries = app(AdvancedReportQueryService::class);

        return $this->report === 'top-products'
            ? $queries->topSellingProducts($this->filters)
            : $queries->subWarehouseBalances($this->filters);
    }

    public function headings(): array
    {
        return $this->report === 'top-products'
            ? ['رقم المنتج', 'اسم المنتج', 'SKU', 'الباركود', 'الكمية المباعة', 'إجمالي المبيعات (د.ع)', 'التكلفة (د.ع)', 'الربح الإجمالي (د.ع)']
            : ['رقم المخزن الفرعي', 'المخزن الفرعي', 'المخزن الرئيسي', 'الموقع', 'عدد المنتجات المتوفرة', 'إجمالي الوحدات', 'قيمة التكلفة (د.ع)', 'قيمة البيع (د.ع)'];
    }

    public function map($row): array
    {
        return $this->report === 'top-products'
            ? [
                $row->product_id,
                $this->textCell($row->product_name),
                $this->textCell($row->sku),
                $this->textCell($row->barcode),
                (int) $row->units_sold,
                (float) $row->gross_sales,
                (float) $row->cost_total,
                (float) $row->gross_profit,
            ]
            : [
                $row->warehouse_id,
                $this->textCell($row->warehouse_name),
                $this->textCell($row->parent_name),
                $this->textCell($row->location),
                (int) $row->products_in_stock,
                (int) $row->total_units,
                (float) $row->cost_value,
                (float) $row->retail_value,
            ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F766E']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return $this->report === 'top-products'
            ? ['C' => NumberFormat::FORMAT_TEXT, 'D' => NumberFormat::FORMAT_TEXT]
            : ['B' => NumberFormat::FORMAT_TEXT, 'C' => NumberFormat::FORMAT_TEXT, 'D' => NumberFormat::FORMAT_TEXT];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:H1');
            },
        ];
    }

    private function textCell(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
