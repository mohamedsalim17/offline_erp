<?php

namespace App\Services;

use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\ChartAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TreasuryService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function record(
        int $treasuryId,
        int $offsetAccountId,
        string $type,
        string $amount,
        string $date,
        string $description,
        int $userId,
    ): TreasuryTransaction {
        if (! in_array($type, ['cash_in', 'cash_out', 'expense'], true)) {
            throw ValidationException::withMessages(['type' => 'نوع حركة الخزنة غير صالح.']);
        }

        return DB::transaction(function () use ($treasuryId, $offsetAccountId, $type, $amount, $date, $description, $userId) {
            $treasury = Treasury::query()->whereKey($treasuryId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $value = $this->normalizeAmount($amount);
            $offset = ChartAccount::query()
                ->whereKey($offsetAccountId)
                ->where('is_active', true)
                ->where('is_group', false)
                ->firstOrFail();

            if ($type === 'expense' && $offset->type !== 'expense') {
                throw ValidationException::withMessages(['offset_account_id' => 'يجب اختيار حساب مصروف للحركة.']);
            }
            if ($offset->parent_id === \App\Models\ChartAccount::query()->where('system_key', 'cash')->value('id')) {
                throw ValidationException::withMessages([
                    'offset_account_id' => 'استخدم حركة التحويل بين الخزائن عند التعامل بين الأرصدة النقدية.',
                ]);
            }

            if (in_array($type, ['cash_out', 'expense'], true) &&
                $this->toMinorUnits($treasury->balance()) < $this->toMinorUnits($value)) {
                throw ValidationException::withMessages(['amount' => 'رصيد الخزنة غير كافٍ لإتمام الصرف.']);
            }

            $lines = $type === 'cash_in'
                ? [
                    ['account_id' => $treasury->account_id, 'treasury_id' => $treasury->id, 'debit' => $value],
                    ['account_id' => $offset->id, 'credit' => $value],
                ]
                : [
                    ['account_id' => $offset->id, 'debit' => $value],
                    ['account_id' => $treasury->account_id, 'treasury_id' => $treasury->id, 'credit' => $value],
                ];

            $entry = $this->accounting->post($date, $description, $lines, userId: $userId);
            $transaction = TreasuryTransaction::create([
                'reference' => $this->reference('TR'),
                'treasury_id' => $treasury->id,
                'journal_entry_id' => $entry->id,
                'type' => $type,
                'amount' => $value,
                'transaction_date' => $date,
                'description' => $description,
                'created_by' => $userId,
            ]);
            $entry->update(['source_type' => TreasuryTransaction::class, 'source_id' => $transaction->id]);

            return $transaction;
        });
    }

    public function transfer(
        int $sourceId,
        int $destinationId,
        string $amount,
        string $date,
        string $description,
        int $userId,
    ): TreasuryTransaction {
        if ($sourceId === $destinationId) {
            throw ValidationException::withMessages(['destination_treasury_id' => 'يجب اختيار خزنتين مختلفتين.']);
        }

        return DB::transaction(function () use ($sourceId, $destinationId, $amount, $date, $description, $userId) {
            $treasuries = Treasury::query()
                ->whereIn('id', [$sourceId, $destinationId])
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $source = $treasuries->get($sourceId);
            $destination = $treasuries->get($destinationId);
            if (! $source || ! $destination) {
                throw ValidationException::withMessages(['treasury_id' => 'إحدى الخزنتين غير موجودة أو غير نشطة.']);
            }
            if ($source->currency !== $destination->currency) {
                throw ValidationException::withMessages(['destination_treasury_id' => 'لا يمكن التحويل بين عملتين مختلفتين.']);
            }

            $value = $this->normalizeAmount($amount);
            if ($this->toMinorUnits($source->balance()) < $this->toMinorUnits($value)) {
                throw ValidationException::withMessages(['amount' => 'رصيد الخزنة المصدر غير كافٍ للتحويل.']);
            }

            $entry = $this->accounting->post($date, $description, [
                ['account_id' => $destination->account_id, 'treasury_id' => $destination->id, 'debit' => $value],
                ['account_id' => $source->account_id, 'treasury_id' => $source->id, 'credit' => $value],
            ], userId: $userId);
            $transaction = TreasuryTransaction::create([
                'reference' => $this->reference('TX'),
                'treasury_id' => $source->id,
                'destination_treasury_id' => $destination->id,
                'journal_entry_id' => $entry->id,
                'type' => 'transfer',
                'amount' => $value,
                'transaction_date' => $date,
                'description' => $description,
                'created_by' => $userId,
            ]);
            $entry->update(['source_type' => TreasuryTransaction::class, 'source_id' => $transaction->id]);

            return $transaction;
        });
    }

    private function normalizeAmount(string $amount): string
    {
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $amount) || $this->toMinorUnits($amount) <= 0) {
            throw ValidationException::withMessages(['amount' => 'أدخل مبلغاً موجباً صحيحاً وبحد أقصى منزلتين عشريتين.']);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return $whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    private function toMinorUnits(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $value = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$value : $value;
    }
}
