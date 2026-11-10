<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فواتير البيع | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <header class="pos-header">
        <div>
            <h1>فواتير البيع والعملاء</h1>
            <small>البحث وإعادة طباعة فاتورة العميل أو نسخة المخزن</small>
        </div>
        <nav class="pos-header-links">
            <a href="{{ route('pos.index') }}">نقطة البيع</a>
            <a href="{{ url('/admin') }}">لوحة الإدارة</a>
        </nav>
    </header>

    <main class="mx-auto max-w-7xl space-y-5 p-4 md:p-6">
        <form method="get" class="flex flex-wrap gap-3 rounded-xl bg-white p-4 shadow">
            <label class="sr-only" for="invoice-search">البحث برقم الفاتورة أو اسم العميل أو الهاتف</label>
            <input id="invoice-search" name="search" value="{{ $search }}" maxlength="100"
                placeholder="رقم الفاتورة، اسم العميل، أو رقم الهاتف"
                class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
            <button class="rounded-lg bg-teal-700 px-5 py-2 font-semibold text-white">بحث</button>
            @if ($search !== '')
                <a class="rounded-lg bg-slate-200 px-5 py-2 font-semibold" href="{{ route('pos.sales') }}">مسح</a>
            @endif
        </form>

        <section class="overflow-x-auto rounded-xl bg-white p-4 shadow">
            <table class="w-full min-w-[850px] text-right text-sm">
                <thead>
                    <tr class="border-b text-slate-600">
                        <th class="p-3">رقم الفاتورة / التاريخ</th>
                        <th class="p-3">العميل</th>
                        <th class="p-3">المخزن</th>
                        <th class="p-3">طريقة الدفع</th>
                        <th class="p-3">الإجمالي</th>
                        <th class="p-3">النسخ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr class="border-b align-top">
                            <td class="p-3">
                                <strong>{{ $sale->receipt_number }}</strong>
                                <div class="mt-1 text-slate-500">{{ $sale->created_at->format('Y-m-d H:i') }}</div>
                            </td>
                            <td class="p-3">
                                {{ $sale->customer_name ?: 'عميل نقدي' }}
                                @if ($sale->customer_phone)
                                    <div class="mt-1 text-slate-500" dir="ltr">{{ $sale->customer_phone }}</div>
                                @endif
                            </td>
                            <td class="p-3">{{ $sale->warehouse->name }}</td>
                            <td class="p-3">
                                {{ ['cash' => 'نقداً', 'bank_app' => 'تطبيق بنكي', 'mixed' => 'نقداً + تطبيق بنكي', 'card' => 'بطاقة', 'transfer' => 'تحويل'][$sale->payment_method] ?? $sale->payment_method }}
                            </td>
                            <td class="p-3 font-semibold">{{ number_format((float) $sale->total, 2) }} {{ $sale->currency }}</td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    <a class="text-teal-700 underline" href="{{ route('pos.receipt', $sale) }}">فاتورة العميل</a>
                                    <a class="text-teal-700 underline" href="{{ route('pos.warehouse-invoice', $sale) }}">نسخة المخزن</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-slate-500">لا توجد فواتير مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $sales->links() }}</div>
        </section>
    </main>
</body>
</html>
