<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px; }
        body {
            direction: rtl;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #172033;
        }
        h1 { margin: 0 0 8px; color: #0f766e; font-size: 19px; }
        .meta { margin-bottom: 16px; color: #475569; }
        .summary { width: 100%; margin-bottom: 16px; }
        .summary td {
            width: 25%;
            border: 1px solid #dbe3eb;
            padding: 8px;
            background: #f8fafc;
        }
        .summary strong { display: block; margin-top: 5px; font-size: 12px; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th { background: #0f766e; color: #fff; }
        .data th, .data td { border: 1px solid #dbe3eb; padding: 6px; text-align: right; }
        .data tr:nth-child(even) td { background: #f8fafc; }
        .footer { margin-top: 14px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        <span>تاريخ استخراج التقرير: {{ now()->format('Y-m-d H:i') }}</span>
        @if ($filters['report'] === 'top-products')
            <span>
                — الفترة:
                {{ $filters['from'] ?: 'بداية السجل' }}
                إلى
                {{ $filters['to'] ?: 'اليوم' }}
            </span>
        @else
            <span>— أرصدة المخزون الحالية</span>
        @endif
        @if ($warehouseName)
            <span>— المخزن: {{ $warehouseName }}</span>
        @endif
    </div>

    @if ($filters['report'] === 'top-products')
        <table class="summary">
            <tr>
                <td>عدد المنتجات<strong>{{ number_format((int) $summary->products_sold) }}</strong></td>
                <td>الوحدات المباعة<strong>{{ number_format((int) $summary->units_sold) }}</strong></td>
                <td>إجمالي المبيعات<strong>{{ number_format((float) $summary->gross_sales, 2) }} د.ع</strong></td>
                <td>الربح الإجمالي<strong>{{ number_format((float) $summary->gross_profit, 2) }} د.ع</strong></td>
            </tr>
        </table>
        <table class="data">
            <thead>
                <tr>
                    <th>#</th><th>المنتج</th><th>SKU</th><th>الباركود</th>
                    <th>الوحدات</th><th>المبيعات (د.ع)</th><th>التكلفة (د.ع)</th><th>الربح (د.ع)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row->product_name }}</td>
                        <td dir="ltr">{{ $row->sku }}</td>
                        <td dir="ltr">{{ $row->barcode }}</td>
                        <td>{{ number_format((int) $row->units_sold) }}</td>
                        <td>{{ number_format((float) $row->gross_sales, 2) }}</td>
                        <td>{{ number_format((float) $row->cost_total, 2) }}</td>
                        <td>{{ number_format((float) $row->gross_profit, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table class="summary">
            <tr>
                <td>المخازن<strong>{{ number_format((int) $summary->warehouses_count) }}</strong></td>
                <td>الأصناف<strong>{{ number_format((int) $summary->products_in_stock) }}</strong></td>
                <td>الوحدات<strong>{{ number_format((int) $summary->total_units) }}</strong></td>
                <td>القيمة بالتكلفة<strong>{{ number_format((float) $summary->cost_value, 2) }} د.ع</strong></td>
            </tr>
        </table>
        <table class="data">
            <thead>
                <tr>
                    <th>#</th><th>المخزن الفرعي</th><th>المخزن الرئيسي</th><th>الموقع</th>
                    <th>الأصناف</th><th>الوحدات</th><th>التكلفة (د.ع)</th><th>قيمة البيع (د.ع)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row->warehouse_name }}</td>
                        <td>{{ $row->parent_name }}</td>
                        <td>{{ $row->location ?: '—' }}</td>
                        <td>{{ number_format((int) $row->products_in_stock) }}</td>
                        <td>{{ number_format((int) $row->total_units) }}</td>
                        <td>{{ number_format((float) $row->cost_value, 2) }}</td>
                        <td>{{ number_format((float) $row->retail_value, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">تم إنشاء هذا التقرير محلياً بواسطة نظام إدارة المخزون.</p>
</body>
</html>
