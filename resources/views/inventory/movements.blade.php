@extends('layouts.app')
@section('title', 'حركات المخزون')
@section('content')
<main class="mx-auto max-w-7xl space-y-6 p-6">
    <header>
        <h1 class="text-2xl font-bold">المخازن وحركات المخزون</h1>
        <p class="mt-1 text-sm text-gray-600">الاستلام والصرف والتحويل تسجل أولاً كطلبات؛ الرصيد لا يتغير حتى الاعتماد.</p>
        <nav class="mt-2 flex gap-4 text-primary-700">
            <a href="{{ route('stocktakes.index') }}" class="underline">الجرد الآني والسنوي</a>
            <a href="{{ route('finance.reports') }}" class="underline">تقارير قيمة المخزون</a>
        </nav>
    </header>
    @if (session('status')) <div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div> @endif

    <form method="post" action="{{ route('stock-movements.store') }}" class="grid gap-3 rounded-xl bg-white p-5 shadow md:grid-cols-3">
        @csrf
        <h2 class="md:col-span-3 font-semibold">طلب حركة جديدة</h2>
        <select name="type" required class="rounded border p-2">
            <option value="receipt">استلام / إدخال مخزون</option>
            <option value="issue">صرف / إخراج مخزون</option>
            <option value="transfer">تحويل بين المخازن</option>
        </select>
        <select name="source_warehouse_id" class="rounded border p-2">
            <option value="">المخزن المصدر (للصرف والتحويل)</option>
            @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
        </select>
        <select name="destination_warehouse_id" class="rounded border p-2">
            <option value="">المخزن الوجهة (للاستلام والتحويل)</option>
            @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
        </select>
        <div id="movement-items" class="space-y-2 md:col-span-3">
            <div class="grid gap-2 md:grid-cols-3" data-movement-item>
                <select name="items[0][product_id]" required class="rounded border p-2">
                    <option value="">المنتج</option>
                    @foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }} — {{ $product->sku }}</option>@endforeach
                </select>
                <input name="items[0][quantity]" type="number" min="1" step="1" required placeholder="الكمية" class="rounded border p-2">
                <input name="items[0][unit_cost]" type="number" min="0" step="0.01" placeholder="تكلفة الوحدة (للاستلام فقط)" class="rounded border p-2">
            </div>
        </div>
        <button id="add-movement-item" type="button" class="rounded border px-4 py-2">إضافة صنف آخر</button>
        <select name="offset_account_id" class="rounded border p-2">
            <option value="">الحساب المقابل (للاستلام/الصرف)</option>
            @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ $account->type }})</option>@endforeach
        </select>
        <input name="movement_date" type="date" value="{{ now()->toDateString() }}" required class="rounded border p-2">
        <input name="reason" maxlength="255" required placeholder="البيان أو سبب الحركة" class="rounded border p-2">
        <button class="rounded bg-primary-600 px-4 py-2 text-white">إرسال للاعتماد</button>
    </form>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-3 font-semibold">سجل الحركات والاعتمادات</h2>
        <table class="w-full text-right text-sm">
            <thead><tr class="border-b"><th class="p-2">المرجع</th><th class="p-2">النوع</th><th class="p-2">من / إلى</th><th class="p-2">الصنف والكمية</th><th class="p-2">الحالة</th><th class="p-2">المنشئ</th><th class="p-2">الإجراء</th></tr></thead>
            <tbody>
            @forelse ($movements as $movement)
                <tr class="border-b align-top">
                    <td class="p-2">{{ $movement->reference }}<br>{{ $movement->movement_date->format('Y-m-d') }}</td>
                    <td class="p-2">{{ ['receipt'=>'استلام','issue'=>'صرف','transfer'=>'تحويل','stocktake'=>'تسوية جرد'][$movement->type] }}</td>
                    <td class="p-2">{{ $movement->sourceWarehouse?->name ?? '—' }} ← {{ $movement->destinationWarehouse?->name ?? '—' }}</td>
                    <td class="p-2">@foreach ($movement->items as $item){{ $item->product->name }} × {{ $item->quantity }}<br>@endforeach</td>
                    <td class="p-2">{{ ['pending'=>'بانتظار الاعتماد','posted'=>'مرحّلة','rejected'=>'مرفوضة'][$movement->status] }}</td>
                    <td class="p-2">{{ $movement->creator?->name ?? '—' }}</td>
                    <td class="p-2">
                        @if ($movement->status === 'pending')
                            <form method="post" action="{{ route('stock-movements.approve', $movement) }}">@csrf<button class="text-green-700 underline">اعتماد وترحيل</button></form>
                            <form method="post" action="{{ route('stock-movements.reject', $movement) }}">@csrf<input type="hidden" name="reason" value="رفض من الإدارة"><button class="mt-1 text-red-700 underline">رفض</button></form>
                        @else — @endif
                    </td>
                </tr>
            @empty <tr><td colspan="7" class="p-4 text-center">لا توجد حركات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $movements->links() }}</div>
    </section>
</main>
<script>
    document.getElementById('add-movement-item')?.addEventListener('click', () => {
        const container = document.getElementById('movement-items');
        const index = container.querySelectorAll('[data-movement-item]').length;
        const row = container.querySelector('[data-movement-item]').cloneNode(true);
        row.querySelectorAll('[name]').forEach((input) => {
            input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            input.value = '';
        });
        container.appendChild(row);
    });
</script>
@endsection
