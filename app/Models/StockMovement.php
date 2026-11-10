<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    protected $fillable = [
        'reference', 'type', 'status', 'source_warehouse_id', 'destination_warehouse_id',
        'movement_date', 'reason', 'offset_account_id', 'created_by', 'approved_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return ['movement_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockMovementItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
