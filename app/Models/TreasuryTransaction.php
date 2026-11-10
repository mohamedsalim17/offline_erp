<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryTransaction extends Model
{
    protected $fillable = [
        'reference', 'treasury_id', 'destination_treasury_id', 'journal_entry_id',
        'type', 'amount', 'transaction_date', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function destinationTreasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'destination_treasury_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
