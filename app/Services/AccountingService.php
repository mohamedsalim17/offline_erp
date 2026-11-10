<?php

namespace App\Services;

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function post(
        string $date,
        string $description,
        array $lines,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $userId = null,
    ): JournalEntry {
        $debits = 0;
        $credits = 0;
        $accountIds = collect($lines)->pluck('account_id')->unique();
        $accounts = ChartAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        foreach ($lines as $line) {
            $account = $accounts->get((int) $line['account_id']);
            if (! $account || ! $account->is_active || $account->is_group) {
                throw ValidationException::withMessages([
                    'account_id' => 'لا يمكن الترحيل إلى حساب غير نشط أو حساب تجميعي.',
                ]);
            }

            if (! empty($line['treasury_id']) && (int) $line['treasury_id'] < 1) {
                throw ValidationException::withMessages(['treasury_id' => 'الخزنة المحددة غير صالحة.']);
            }
            if (! empty($line['treasury_id'])) {
                $treasury = \App\Models\Treasury::query()->find((int) $line['treasury_id']);
                if (! $treasury || (int) $treasury->account_id !== (int) $account->id) {
                    throw ValidationException::withMessages(['treasury_id' => 'الحساب المحاسبي لا يتبع الخزنة المحددة.']);
                }
            }

            $debit = $this->toMinorUnits((string) ($line['debit'] ?? '0'));
            $credit = $this->toMinorUnits((string) ($line['credit'] ?? '0'));
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages([
                    'amount' => 'يجب أن يحتوي كل سطر قيد على مدين أو دائن فقط.',
                ]);
            }
            $debits += $debit;
            $credits += $credit;
        }

        if ($debits < 1 || $debits !== $credits) {
            throw ValidationException::withMessages([
                'journal' => 'القيد غير متوازن؛ يجب أن يتساوى إجمالي المدين والدائن.',
            ]);
        }

        $entry = JournalEntry::create([
            'entry_number' => 'TMP-'.Str::uuid(),
            'entry_date' => $date,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => $userId,
            'posted_at' => now(),
        ]);
        $entry->update(['entry_number' => 'JE-'.now()->format('Ymd').'-'.str_pad((string) $entry->id, 8, '0', STR_PAD_LEFT)]);

        foreach ($lines as $line) {
            $entry->lines()->create([
                'account_id' => $line['account_id'],
                'treasury_id' => $line['treasury_id'] ?? null,
                'description' => $line['description'] ?? null,
                'debit' => $this->fromMinorUnits($this->toMinorUnits((string) ($line['debit'] ?? '0'))),
                'credit' => $this->fromMinorUnits($this->toMinorUnits((string) ($line['credit'] ?? '0'))),
            ]);
        }

        return $entry->load('lines');
    }

    public function systemAccount(string $key): ChartAccount
    {
        return ChartAccount::query()
            ->where('system_key', $key)
            ->where('is_active', true)
            ->where('is_group', false)
            ->firstOrFail();
    }

    private function toMinorUnits(string $amount): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages(['amount' => 'المبلغ يجب أن يكون رقماً موجباً وبحد أقصى منزلتين عشريتين.']);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function fromMinorUnits(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
