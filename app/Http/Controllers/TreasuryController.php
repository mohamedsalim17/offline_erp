<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use App\Models\SystemSetting;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TreasuryController extends Controller
{
    public function index(): View
    {
        $cashParentId = ChartAccount::query()->where('system_key', 'cash')->value('id');

        return view('finance.treasuries', [
            'treasuries' => Treasury::query()
                ->with('account')
                ->withCount('transactions')
                ->addSelect([
                    'current_balance' => DB::table('journal_lines')
                        ->selectRaw('COALESCE(SUM(debit - credit), 0)')
                        ->whereColumn('treasury_id', 'treasuries.id'),
                ])
                ->orderBy('name')
                ->get(),
            'accounts' => ChartAccount::query()->where('is_active', true)->where('is_group', false)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'cashAccounts' => ChartAccount::query()
                ->where('parent_id', $cashParentId)
                ->where('is_active', true)
                ->where('is_group', false)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'transactions' => TreasuryTransaction::query()
                ->with(['treasury', 'destinationTreasury', 'journalEntry'])
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('treasuries', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'account_id' => ['required', 'integer', Rule::exists('chart_accounts', 'id')->where('is_active', true)],
            'currency' => ['required', 'in:IQD'],
        ]);
        $account = ChartAccount::query()->whereKey($validated['account_id'])->where('is_group', false)->firstOrFail();
        if ($account->parent_id !== ChartAccount::query()->where('system_key', 'cash')->value('id')) {
            throw ValidationException::withMessages(['account_id' => 'اربط الخزنة بحساب نقدي فرعي ضمن مجموعة النقدية.']);
        }

        DB::transaction(function () use ($validated) {
            $treasury = Treasury::create($validated + ['is_active' => true]);
            if (! SystemSetting::query()->where('key', 'finance.default_treasury_id')->exists()) {
                SystemSetting::updateOrCreate(
                    ['key' => 'finance.default_treasury_id'],
                    ['value' => (string) $treasury->id, 'type' => 'integer', 'group' => 'finance', 'is_public' => false],
                );
            }
        });

        return back()->with('status', 'تم إنشاء الخزنة بنجاح.');
    }

    public function record(Request $request, TreasuryService $service): RedirectResponse
    {
        $data = $request->validate([
            'treasury_id' => ['required', 'integer', 'exists:treasuries,id'],
            'offset_account_id' => ['required', 'integer', Rule::exists('chart_accounts', 'id')->where('is_active', true)],
            'type' => ['required', 'in:cash_in,cash_out,expense'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $service->record(
            (int) $data['treasury_id'],
            (int) $data['offset_account_id'],
            $data['type'],
            (string) $data['amount'],
            $data['transaction_date'],
            $data['description'],
            (int) $request->user()->id,
        );

        return back()->with('status', 'تم ترحيل الحركة والقيد المالي بنجاح.');
    }

    public function transfer(Request $request, TreasuryService $service): RedirectResponse
    {
        $data = $request->validate([
            'treasury_id' => ['required', 'integer', 'exists:treasuries,id'],
            'destination_treasury_id' => ['required', 'integer', 'different:treasury_id', 'exists:treasuries,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $service->transfer(
            (int) $data['treasury_id'],
            (int) $data['destination_treasury_id'],
            (string) $data['amount'],
            $data['transaction_date'],
            $data['description'],
            (int) $request->user()->id,
        );

        return back()->with('status', 'تم تحويل الرصيد بين الخزنتين وترحيل القيد المتوازن.');
    }

    public function toggle(Treasury $treasury): RedirectResponse
    {
        $treasury->update(['is_active' => ! $treasury->is_active]);

        return back()->with('status', $treasury->is_active ? 'تم تفعيل الخزنة.' : 'تم إيقاف الخزنة دون حذف سجلها.');
    }

    public function setDefault(Treasury $treasury): RedirectResponse
    {
        if (! $treasury->is_active) {
            throw ValidationException::withMessages(['treasury_id' => 'يجب تفعيل الخزنة قبل اختيارها افتراضياً.']);
        }

        SystemSetting::updateOrCreate(
            ['key' => 'finance.default_treasury_id'],
            ['value' => (string) $treasury->id, 'type' => 'integer', 'group' => 'finance', 'is_public' => false],
        );

        return back()->with('status', 'تم تحديث الخزنة الافتراضية لنقطة البيع.');
    }
}
