<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'name', 
        'sku', 
        'barcode', 
        'cost_price', 
        'selling_price', 
        'wholesale_price', 
        'minimum_stock_alert',
        'category_id',
        'unit_name',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (blank($product->sku)) {
                do {
                    $product->sku = 'SKU-'.Str::upper(Str::random(8));
                } while (self::query()->where('sku', $product->sku)->exists());
            }

            if (blank($product->barcode)) {
                do {
                    $body = '20'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
                    $sum = 0;
                    foreach (str_split($body) as $index => $digit) {
                        $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
                    }
                    $checkDigit = (10 - ($sum % 10)) % 10;
                    $product->barcode = $body.$checkDigit;
                } while (self::query()->where('barcode', $product->barcode)->exists());
            }
        });
    }

    // علاقة المنتج بالقسم التابع له
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // علاقة المنتج بالمخازن المتفرعة مع جلب كمية التخزين ورقم الرف من الجدول الوسيط
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'product_warehouse')
            ->withPivot('quantity', 'average_cost', 'shelf_number')
            ->withTimestamps();
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovementItems(): HasMany
    {
        return $this->hasMany(StockMovementItem::class);
    }
}
