<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'request_key',
        'receipt_number',
        'cashier_id',
        'warehouse_id',
        'cash_treasury_id',
        'bank_treasury_id',
        'customer_name',
        'customer_phone',
        'status',
        'payment_method',
        'cash_amount',
        'bank_amount',
        'subtotal',
        'total',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'bank_amount' => 'decimal:2',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bankTreasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'bank_treasury_id');
    }

    public function cashTreasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class, 'cash_treasury_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
