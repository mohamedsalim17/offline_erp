<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Treasury extends Model
{
    protected $fillable = ['code', 'name', 'account_id', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class);
    }

    public function balance(): string
    {
        $balance = DB::table('journal_lines')
            ->where('treasury_id', $this->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $balance = (string) ($balance ?? '0');
        $negative = str_starts_with($balance, '-');
        $balance = ltrim($balance, '-');
        [$whole, $fraction] = array_pad(explode('.', $balance, 2), 2, '0');

        return ($negative ? '-' : '').(ltrim($whole, '0') ?: '0').'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
