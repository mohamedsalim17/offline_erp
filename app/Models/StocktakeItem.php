<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StocktakeItem extends Model
{
    protected $fillable = ['stocktake_id', 'product_id', 'system_quantity', 'counted_quantity', 'unit_cost'];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
