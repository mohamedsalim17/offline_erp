@extends('layouts.app')
@section('title', 'تفاصيل الجرد')
@section('content')
<main class="mx-auto max-w-7xl space-y-5 p-6">
    <header>
        <h1 class="text-2xl font-bold">الجرد {{ $stocktake->reference }}</h1>
        <p class="mt-1 text-gray-600">{{ $stocktake->warehouse->name }} — {{ $stocktake->count_date->format('Y-m-d') }} — {{ $stocktake->type === 'annual' ? 'سنوي' : 'آني' }}</p>
        <a href="{{ route('stocktakes.index') }}" class="text-primary-700 underline">قائمة الجرد</a>
    </header>
    @if (session('status')) <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div> @endif
    <p class="rounded bg-amber-50 p-3 text-amber-900">لقطة النظام ثابتة عند بداية الجرد. تُطبّق فروقات العد على الرصيد الحالي للحفاظ على الحركات التي تمت بعد أخذ اللقطة؛ راجع الكميات قبل الاعتماد.</p>

    @if ($stocktake->status === 'counting')
        <form method="post" action="{{ route('stocktakes.counts', $stocktake) }}" class="overflow-x-auto rounded-xl bg-white p-5 shadow">
            @csrf
            <table class="w-full text-right text-sm">
                <thead><tr class="border-b"><th class="p-2">المنتج</th><th class="p-2">SKU / باركود</th><th class="p-2">وحدة</th><th class="p-2">لقطة النظام</th><th class="p-2">الكمية المعدودة فعلياً</th><th class="p-2">فرق اللقطة</th></tr></thead>
                <tbody>
                @foreach ($stocktake->items as $item)
                    @php($counted = old("counts.{$item->id}", $item->counted_quantity))
                    <tr class="border-b">
                        <td class="p-2">{{ $item->product->name }}</td>
                        <td class="p-2">{{ $item->product->sku }} / {{ $item->product->barcode }}</td>
                        <td class="p-2">{{ $item->product->unit_name }}</td>
                        <td class="p-2">{{ $item->system_quantity }}</td>
                        <td class="p-2"><input name="counts[{{ $item->id }}]" type="number" min="0" step="1" value="{{ $counted }}" class="w-32 rounded border p-2"></td>
                        <td class="p-2">{{ $counted === null || $counted === '' ? '—' : (int) $counted - (int) $item->system_quantity }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="mt-4 flex gap-3">
                <button name="action" value="save" class="rounded border px-4 py-2">حفظ المسودة</button>
                <button name="action" value="submit" class="rounded bg-primary-600 px-4 py-2 text-white">حفظ وإرسال للاعتماد</button>
            </div>
        </form>
    @elseif ($stocktake->status === 'submitted')
        <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">مراجعة الفروقات قبل الترحيل</h2>
            <table class="w-full text-right text-sm">
                <thead><tr class="border-b"><th class="p-2">المنتج</th><th class="p-2">لقطة النظام</th><th class="p-2">المعدود</th><th class="p-2">الفرق</th><th class="p-2">قيمة الفرق التقديرية</th></tr></thead>
                <tbody>
                @foreach ($stocktake->items as $item)
                    <tr class="border-b">
                        <td class="p-2">{{ $item->product->name }}</td><td class="p-2">{{ $item->system_quantity }}</td>
                        <td class="p-2">{{ $item->counted_quantity }}</td>
                        <td class="p-2">{{ (int) $item->counted_quantity - (int) $item->system_quantity }}</td>
                        <td class="p-2">{{ number_format(((int) $item->counted_quantity - (int) $item->system_quantity) * (float) $item->unit_cost, 2) }} د.ع</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <form method="post" action="{{ route('stocktakes.approve', $stocktake) }}" class="mt-4" onsubmit="return confirm('سيتم تعديل الأرصدة وترحيل القيد المالي. هل تريد الاعتماد؟')">
                @csrf
                <button class="rounded bg-green-700 px-4 py-2 text-white">اعتماد وترحيل فروقات الجرد</button>
            </form>
        </section>
    @else
        <section class="rounded-xl bg-white p-5 shadow">
            <p>حالة الجرد: {{ $stocktake->status === 'approved' ? 'معتمد ومرحّل' : 'ملغى' }}</p>
            @if ($stocktake->movement)
                <p>حركة التسوية: <a class="text-primary-700 underline" href="{{ route('stock-movements.index') }}">{{ $stocktake->movement->reference }}</a></p>
            @endif
        </section>
    @endif
</main>
@endsection
