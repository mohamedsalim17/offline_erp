<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementItem extends Model
{
    protected $fillable = ['stock_movement_id', 'product_id', 'quantity', 'direction', 'unit_cost', 'value'];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2', 'value' => 'decimal:2'];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
