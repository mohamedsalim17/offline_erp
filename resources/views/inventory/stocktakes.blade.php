@extends('layouts.app')
@section('title', 'الجرد')
@section('content')
<main class="mx-auto max-w-7xl space-y-6 p-6">
    <header>
        <h1 class="text-2xl font-bold">الجرد الآني والسنوي</h1>
        <p class="mt-1 text-sm text-gray-600">تُلتقط أرصدة النظام عند بدء الجرد؛ تُحفظ الفروقات كمسودة وتُرحّل بعد الاعتماد.</p>
        <a href="{{ route('stock-movements.index') }}" class="text-primary-700 underline">العودة إلى حركات المخزون</a>
    </header>
    @if (session('status')) <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div> @endif

    <form method="post" action="{{ route('stocktakes.store') }}" class="grid gap-3 rounded-xl bg-white p-5 shadow md:grid-cols-4">
        @csrf
        <h2 class="md:col-span-4 font-semibold">بدء جلسة جرد</h2>
        <select name="warehouse_id" required class="rounded border p-2">
            <option value="">المخزن</option>
            @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
        </select>
        <select name="type" required class="rounded border p-2">
            <option value="spot">جرد آني / دوري</option>
            <option value="annual">جرد سنوي</option>
        </select>
        <input name="count_date" type="date" value="{{ now()->toDateString() }}" required class="rounded border p-2">
        <input name="notes" maxlength="255" placeholder="ملاحظات اختيارية" class="rounded border p-2">
        <button class="rounded bg-primary-600 px-4 py-2 text-white">بدء وأخذ لقطة الأرصدة</button>
    </form>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-3 font-semibold">جلسات الجرد</h2>
        <table class="w-full text-right text-sm">
            <thead><tr class="border-b"><th class="p-2">المرجع</th><th class="p-2">التاريخ</th><th class="p-2">المخزن</th><th class="p-2">النوع</th><th class="p-2">الحالة</th><th class="p-2">المنشئ</th><th class="p-2"></th></tr></thead>
            <tbody>
            @forelse ($stocktakes as $stocktake)
                <tr class="border-b">
                    <td class="p-2">{{ $stocktake->reference }}</td><td class="p-2">{{ $stocktake->count_date->format('Y-m-d') }}</td>
                    <td class="p-2">{{ $stocktake->warehouse->name }}</td><td class="p-2">{{ $stocktake->type === 'annual' ? 'سنوي' : 'آني' }}</td>
                    <td class="p-2">{{ ['counting'=>'قيد العد','submitted'=>'بانتظار الاعتماد','approved'=>'معتمد','cancelled'=>'ملغى'][$stocktake->status] }}</td>
                    <td class="p-2">{{ $stocktake->creator?->name ?? '—' }}</td>
                    <td class="p-2"><a href="{{ route('stocktakes.edit', $stocktake) }}" class="text-primary-700 underline">فتح</a></td>
                </tr>
            @empty <tr><td colspan="7" class="p-4 text-center">لا توجد جلسات جرد.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $stocktakes->links() }}</div>
    </section>
</main>
@endsection
