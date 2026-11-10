@extends('layouts.app')
@section('title', 'التقارير المالية')
@section('content')
<main class="mx-auto max-w-7xl space-y-6 p-6">
    <header>
        <h1 class="text-2xl font-bold">التقارير المالية والمخزون</h1>
        <p class="mt-1 text-sm text-gray-600">التقارير مستخرجة من القيود المرحلة؛ تقييم المخزون يستخدم متوسط التكلفة المتحرك لكل مخزن.</p>
        <a href="{{ route('treasuries.index') }}" class="text-primary-700 underline">إدارة الخزائن والحركات</a>
    </header>
    @if ($errors->any()) <div class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div> @endif
    <form method="get" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <label>من تاريخ<input name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 block rounded border p-2"></label>
        <label>إلى تاريخ<input name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 block rounded border p-2"></label>
        <button class="rounded bg-primary-600 px-4 py-2 text-white">تحديث التقارير</button>
        <a href="{{ route('finance.reports') }}" class="rounded border px-4 py-2">مسح الفلاتر</a>
    </form>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['إجمالي الإيرادات', $incomeStatement['revenue']],
            ['إجمالي المصروفات', $incomeStatement['expenses']],
            ['صافي الربح التقديري', $incomeStatement['net_profit']],
        ] as [$label, $amount])
            <article class="rounded-xl bg-white p-5 shadow"><h2 class="text-sm text-gray-600">{{ $label }}</h2><p class="mt-2 text-2xl font-bold">{{ number_format($amount, 2) }} د.ع</p></article>
        @endforeach
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-1 font-semibold">ميزان المراجعة حتى نهاية الفترة</h2><p class="mb-3 text-sm text-gray-600">الرصيد تراكمي حتى تاريخ النهاية ويتضمن الأرصدة الافتتاحية؛ يجب تساوي إجمالي المدين والدائن.</p>
        <table class="w-full text-right text-sm">
            <thead><tr class="border-b"><th class="p-2">رمز الحساب</th><th class="p-2">الحساب</th><th class="p-2">النوع</th><th class="p-2">مدين</th><th class="p-2">دائن</th></tr></thead>
            <tbody>
            @foreach ($trialBalance['rows'] as $row)
                <tr class="border-b"><td class="p-2">{{ $row->code }}</td><td class="p-2">{{ $row->name }}</td><td class="p-2">{{ $row->type }}</td><td class="p-2">{{ number_format($row->balance_debit, 2) }}</td><td class="p-2">{{ number_format($row->balance_credit, 2) }}</td></tr>
            @endforeach
            <tr class="font-bold"><td colspan="3" class="p-2">الإجمالي</td><td class="p-2">{{ number_format($trialBalance['debit_total'], 2) }}</td><td class="p-2">{{ number_format($trialBalance['credit_total'], 2) }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-1 font-semibold">الميزانية العمومية حتى تاريخ النهاية</h2>
        <p class="mb-3 text-sm text-gray-600">تتضمن النتيجة المتراكمة ضمن حقوق الملكية لعرض المعادلة المحاسبية دون إقفال سنوي.</p>
        <table class="w-full text-right text-sm">
            <thead><tr class="border-b"><th class="p-2">رمز الحساب</th><th class="p-2">الحساب</th><th class="p-2">التصنيف</th><th class="p-2">الرصيد</th></tr></thead>
            <tbody>
            @foreach ($balanceSheet['rows'] as $row)
                <tr class="border-b"><td class="p-2">{{ $row->code }}</td><td class="p-2">{{ $row->name }}</td><td class="p-2">{{ ['asset'=>'أصل','liability'=>'التزام','equity'=>'حقوق ملكية'][$row->type] }}</td><td class="p-2">{{ number_format($row->balance, 2) }}</td></tr>
            @endforeach
            <tr class="font-bold"><td colspan="3" class="p-2">إجمالي الأصول</td><td class="p-2">{{ number_format($balanceSheet['assets'], 2) }}</td></tr>
            <tr class="font-bold"><td colspan="3" class="p-2">الالتزامات وحقوق الملكية والنتيجة</td><td class="p-2">{{ number_format($balanceSheet['liabilities_and_equity'], 2) }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-3 font-semibold">قائمة الدخل</h2>
        <table class="w-full text-right text-sm">
            <thead><tr class="border-b"><th class="p-2">رمز الحساب</th><th class="p-2">الحساب</th><th class="p-2">التصنيف</th><th class="p-2">المبلغ</th></tr></thead>
            <tbody>
            @foreach ($incomeStatement['rows'] as $row)
                <tr class="border-b"><td class="p-2">{{ $row->code }}</td><td class="p-2">{{ $row->name }}</td><td class="p-2">{{ $row->type === 'revenue' ? 'إيراد' : 'مصروف' }}</td><td class="p-2">{{ number_format($row->amount, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-3 font-semibold">أرصدة الخزائن الحالية</h2>
        <table class="w-full text-right text-sm"><thead><tr class="border-b"><th class="p-2">الخزنة</th><th class="p-2">الحساب</th><th class="p-2">الرصيد</th></tr></thead>
            <tbody>@foreach ($treasuries as $treasury)<tr class="border-b"><td class="p-2">{{ $treasury->name }}</td><td class="p-2">{{ $treasury->account_name }}</td><td class="p-2">{{ number_format((float) $treasury->balance, 2) }} {{ $treasury->currency }}</td></tr>@endforeach</tbody>
        </table>
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-1 font-semibold">تقييم المخزون الحالي</h2><p class="mb-3 text-sm text-gray-600">القيمة = الكمية الحالية × متوسط التكلفة المتحرك؛ هذا التقرير لحظي وليس تاريخياً.</p>
        <table class="w-full text-right text-sm"><thead><tr class="border-b"><th class="p-2">المخزن</th><th class="p-2">المنتج</th><th class="p-2">SKU</th><th class="p-2">الكمية</th><th class="p-2">متوسط التكلفة</th><th class="p-2">قيمة الرصيد</th></tr></thead>
            <tbody>
            @foreach ($stockRows as $row)
                <tr class="border-b"><td class="p-2">{{ $row->parent_name ? $row->parent_name.' / ' : '' }}{{ $row->warehouse_name }}</td><td class="p-2">{{ $row->product_name }}</td><td class="p-2">{{ $row->sku }}</td><td class="p-2">{{ $row->quantity }} {{ $row->unit_name }}</td><td class="p-2">{{ number_format((float) $row->average_cost, 2) }}</td><td class="p-2">{{ number_format((float) $row->stock_value, 2) }}</td></tr>
            @endforeach
            <tr class="font-bold"><td colspan="5" class="p-2">إجمالي قيمة المخزون الحالي</td><td class="p-2">{{ number_format((float) $stockValueTotal, 2) }} د.ع</td></tr>
            </tbody>
        </table>
        <div class="mt-4">{{ $stockRows->links() }}</div>
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-1 font-semibold">تنبيهات الحد الأدنى للمخزون</h2>
        <p class="mb-3 text-sm text-gray-600">يعرض الأصناف التي وصل رصيدها إلى حد التنبيه أو أصبح أقل منه؛ النتائج محدودة بأول 500 تنبيه.</p>
        <table class="w-full text-right text-sm"><thead><tr class="border-b"><th class="p-2">المخزن</th><th class="p-2">المنتج</th><th class="p-2">SKU / باركود</th><th class="p-2">الرصيد</th><th class="p-2">حد التنبيه</th></tr></thead>
            <tbody>
            @forelse ($lowStockRows as $row)
                <tr class="border-b"><td class="p-2">{{ $row->warehouse_name }}</td><td class="p-2">{{ $row->product_name }}</td><td class="p-2">{{ $row->sku }} / {{ $row->barcode }}</td><td class="p-2 text-red-700">{{ $row->quantity }} {{ $row->unit_name }}</td><td class="p-2">{{ $row->minimum_stock_alert }}</td></tr>
            @empty <tr><td colspan="5" class="p-3 text-center">لا توجد تنبيهات مخزون حالياً.</td></tr> @endforelse
            </tbody>
        </table>
    </section>
</main>
@endsection
