<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoiceType === 'warehouse' ? 'نسخة المخزن' : 'فاتورة العميل' }} {{ $sale->receipt_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: {{ $settings['paper_width_mm'] }}mm auto;
            margin: 0;
        }

        .thermal-receipt {
            width: {{ (int) $settings['paper_width_mm'] - 8 }}mm;
        }
    </style>
</head>
<body class="pos-receipt-page">
    <main class="thermal-receipt">
        @if ($settings['company_logo_path'])
            <img class="receipt-company-logo" src="{{ route('company.logo') }}" alt="شعار {{ $settings['company_name'] }}">
        @endif
        <h1>{{ $settings['company_name'] }}</h1>
        @if ($settings['store_name'] !== $settings['company_name'])
            <p>{{ $settings['store_name'] }}</p>
        @endif
        @if (filled($settings['store_address']))
            <p>{{ $settings['store_address'] }}</p>
        @endif
        @if (filled($settings['store_phone']))
            <p dir="ltr">{{ $settings['store_phone'] }}</p>
        @endif

        @if ($invoiceType === 'warehouse')
            <h2 class="invoice-type">نسخة المخزن — مستند صرف مرتبط بفاتورة بيع</h2>
        @else
            <h2 class="invoice-type">فاتورة بيع — نسخة العميل</h2>
        @endif
        <hr>
        <p><strong>رقم الفاتورة:</strong> {{ $sale->receipt_number }}</p>
        <div class="invoice-barcode">{!! $invoiceBarcodeSvg !!}</div>
        <p class="invoice-barcode-value">باركود الفاتورة: {{ $invoiceBarcodeValue }}</p>
        <p><strong>التاريخ:</strong> {{ $sale->created_at->format('Y-m-d H:i') }}</p>
        <p><strong>الكاشير:</strong> {{ $sale->cashier->name }}</p>
        <p><strong>المخزن:</strong> {{ $sale->warehouse->name }}</p>
        <p><strong>العميل:</strong> {{ $sale->customer_name ?: 'عميل نقدي' }}</p>
        @if ($sale->customer_phone)
            <p><strong>هاتف العميل:</strong> <span dir="ltr">{{ $sale->customer_phone }}</span></p>
        @endif

        <table>
            <thead>
                <tr>
                    <th>الصنف / الباركود</th>
                    <th>عدد</th>
                    <th>سعر الوحدة</th>
                    <th>المجموع</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}<br><small>{{ $item->barcode }}</small></td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="receipt-total">
            <span>إجمالي الفاتورة</span>
            <span>{{ number_format((float) $sale->total, 2) }} {{ $sale->currency }}</span>
        </div>
        <p><strong>طريقة الدفع:</strong>
            {{ ['cash' => 'نقداً', 'bank_app' => 'تطبيق بنكي', 'mixed' => 'نقداً + تطبيق بنكي', 'card' => 'بطاقة', 'transfer' => 'تحويل'][$sale->payment_method] ?? $sale->payment_method }}
        </p>
        @if ((float) $sale->cash_amount > 0)
            <p>المبلغ النقدي: {{ number_format((float) $sale->cash_amount, 2) }} {{ $sale->currency }}</p>
            <p>خزنة النقد: {{ $sale->cashTreasury?->name ?? 'الخزنة المسجلة' }}</p>
        @endif
        @if ((float) $sale->bank_amount > 0)
            <p>المبلغ عبر التطبيق البنكي: {{ number_format((float) $sale->bank_amount, 2) }} {{ $sale->currency }}</p>
            <p>حساب الإيداع: {{ $sale->bankTreasury?->name ?? 'الحساب البنكي' }}</p>
        @endif
        <hr>
        <p>{{ $settings['receipt_footer'] }}</p>
    </main>

    <div class="receipt-actions">
        <button class="pos-button" type="button" onclick="window.print()">طباعة هذه النسخة</button>
        @if ($invoiceType === 'customer')
            <a class="pos-button pos-button-secondary" href="{{ route('pos.warehouse-invoice', $sale) }}">فتح نسخة المخزن</a>
        @else
            <a class="pos-button pos-button-secondary" href="{{ route('pos.receipt', $sale) }}">فتح فاتورة العميل</a>
        @endif
        <a class="pos-button pos-button-secondary" href="{{ route('pos.sales') }}">سجل الفواتير</a>
        <a class="pos-button" href="{{ route('pos.index') }}">العودة إلى نقطة البيع</a>
    </div>

    @if ($settings['auto_print'] === '1')
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
