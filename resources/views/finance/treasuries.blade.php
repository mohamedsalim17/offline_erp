@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl space-y-6 p-6" dir="rtl">
    <header>
        <h1 class="text-2xl font-bold">الخزائن والحركات المالية</h1>
        <p class="mt-1 text-sm text-gray-600">كل حركة تُرحّل إلى دفتر الأستاذ، ورصيد الخزنة محسوب مباشرة من القيود.</p>
        <a class="text-primary-600 underline" href="{{ route('finance.reports') }}">التقارير المالية</a>
    </header>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 p-3 text-green-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div>
    @endif

    <section class="grid gap-6 lg:grid-cols-3">
        <form method="post" action="{{ route('treasuries.store') }}" class="space-y-3 rounded-xl bg-white p-5 shadow">
            @csrf
            <h2 class="font-semibold">إنشاء خزنة</h2>
            <input name="name" required maxlength="120" placeholder="اسم الخزنة" class="w-full rounded border p-2">
            <input name="code" required maxlength="32" placeholder="رمز مثل CASH-02" class="w-full rounded border p-2">
            <select name="account_id" required class="w-full rounded border p-2">
                <option value="">الحساب المحاسبي</option>
                @foreach ($cashAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                @endforeach
            </select>
            <input type="hidden" name="currency" value="IQD">
            <button class="rounded bg-primary-600 px-4 py-2 text-white">حفظ الخزنة</button>
        </form>

        <form method="post" action="{{ route('treasuries.transactions.store') }}" class="space-y-3 rounded-xl bg-white p-5 shadow">
            @csrf
            <h2 class="font-semibold">قبض أو صرف أو مصروف</h2>
            <select name="treasury_id" required class="w-full rounded border p-2">
                @foreach ($treasuries as $treasury)
                    <option value="{{ $treasury->id }}">{{ $treasury->name }} — الرصيد {{ number_format((float) $treasury->current_balance, 2) }} د.ع</option>
                @endforeach
            </select>
            <select name="type" required class="w-full rounded border p-2">
                <option value="cash_in">قبض</option>
                <option value="cash_out">صرف</option>
                <option value="expense">مصروف</option>
            </select>
            <select name="offset_account_id" required class="w-full rounded border p-2">
                <option value="">الحساب المقابل</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ $account->type }})</option>
                @endforeach
            </select>
            <input name="amount" type="number" step="0.01" min="0.01" required placeholder="المبلغ بالدينار" class="w-full rounded border p-2">
            <input name="transaction_date" type="date" value="{{ now()->toDateString() }}" required class="w-full rounded border p-2">
            <input name="description" required maxlength="255" placeholder="البيان" class="w-full rounded border p-2">
            <button class="rounded bg-primary-600 px-4 py-2 text-white">ترحيل الحركة</button>
        </form>

        <form method="post" action="{{ route('treasuries.transfer') }}" class="space-y-3 rounded-xl bg-white p-5 shadow">
            @csrf
            <h2 class="font-semibold">تحويل بين خزنتين</h2>
            <select name="treasury_id" required class="w-full rounded border p-2">
                @foreach ($treasuries as $treasury)
                    <option value="{{ $treasury->id }}">{{ $treasury->name }}</option>
                @endforeach
            </select>
            <select name="destination_treasury_id" required class="w-full rounded border p-2">
                @foreach ($treasuries as $treasury)
                    <option value="{{ $treasury->id }}">{{ $treasury->name }}</option>
                @endforeach
            </select>
            <input name="amount" type="number" step="0.01" min="0.01" required placeholder="المبلغ" class="w-full rounded border p-2">
            <input name="transaction_date" type="date" value="{{ now()->toDateString() }}" required class="w-full rounded border p-2">
            <input name="description" required maxlength="255" placeholder="سبب التحويل" class="w-full rounded border p-2">
            <button class="rounded bg-primary-600 px-4 py-2 text-white">تنفيذ التحويل</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-3 font-semibold">أرصدة الخزائن</h2>
        <table class="w-full text-right text-sm">
            <thead><tr class="border-b"><th class="p-2">الرمز</th><th class="p-2">الخزنة</th><th class="p-2">الحساب</th><th class="p-2">الرصيد الحالي</th><th class="p-2">الحالة</th><th class="p-2">إجراء</th></tr></thead>
            <tbody>
                @forelse ($treasuries as $treasury)
                    <tr class="border-b">
                        <td class="p-2">{{ $treasury->code }}</td><td class="p-2">{{ $treasury->name }}</td>
                        <td class="p-2">{{ $treasury->account->name }}</td>
                        <td class="p-2 font-semibold">{{ number_format((float) $treasury->current_balance, 2) }} {{ $treasury->currency }}</td>
                        <td class="p-2">{{ $treasury->is_active ? 'نشطة' : 'متوقفة' }}</td>
                        <td class="space-y-1 p-2">
                            <form method="post" action="{{ route('treasuries.toggle', $treasury) }}">@csrf<button class="underline">{{ $treasury->is_active ? 'إيقاف' : 'تفعيل' }}</button></form>
                            @if ($treasury->is_active)
                                <form method="post" action="{{ route('treasuries.default', $treasury) }}">@csrf<button class="text-primary-700 underline">جعلها افتراضية</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-3 text-center">لا توجد خزائن.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="overflow-x-auto rounded-xl bg-white p-5 shadow">
        <h2 class="mb-3 font-semibold">آخر الحركات المرحلة</h2>
        <table class="w-full text-right text-sm">
            <thead><tr class="border-b"><th class="p-2">التاريخ</th><th class="p-2">المرجع</th><th class="p-2">النوع</th><th class="p-2">الخزنة</th><th class="p-2">البيان</th><th class="p-2">المبلغ</th><th class="p-2">القيد</th></tr></thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr class="border-b">
                        <td class="p-2">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                        <td class="p-2">{{ $transaction->reference }}</td>
                        <td class="p-2">{{ ['cash_in'=>'قبض','cash_out'=>'صرف','expense'=>'مصروف','transfer'=>'تحويل'][$transaction->type] }}</td>
                        <td class="p-2">{{ $transaction->treasury->name }} @if($transaction->destinationTreasury) ← {{ $transaction->destinationTreasury->name }} @endif</td>
                        <td class="p-2">{{ $transaction->description }}</td>
                        <td class="p-2">{{ number_format((float) $transaction->amount, 2) }}</td>
                        <td class="p-2">{{ $transaction->journalEntry->entry_number }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-3 text-center">لا توجد حركات حتى الآن.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </section>
</main>
@endsection
