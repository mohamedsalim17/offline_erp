<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stocktake extends Model
{
    protected $fillable = [
        'reference', 'warehouse_id', 'type', 'status', 'count_date', 'notes',
        'created_by', 'approved_by', 'stock_movement_id', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['count_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StocktakeItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}
