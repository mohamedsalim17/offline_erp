<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

class Warehouse extends Model
{
    protected $fillable = ['name', 'parent_id', 'location', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (Warehouse $warehouse): void {
            if (! $warehouse->parent_id) {
                return;
            }

            $ancestorId = (int) $warehouse->parent_id;
            $visited = [];
            while ($ancestorId > 0) {
                if ($warehouse->exists && $ancestorId === (int) $warehouse->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'لا يمكن ربط المخزن بنفسه أو بأحد فروعه.',
                    ]);
                }
                if (isset($visited[$ancestorId])) {
                    throw ValidationException::withMessages(['parent_id' => 'شجرة المخازن تحتوي على حلقة غير صالحة.']);
                }
                $visited[$ancestorId] = true;

                $ancestorId = (int) self::query()->whereKey($ancestorId)->value('parent_id');
            }
        });
    }

    // علاقة المخزن الأب (المخزن الحالي ينتمي إلى مخزن رئيسي أعلى منه)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'parent_id');
    }

    // علاقة المخازن المتفرعة (المخزن الحالي يحتوي على عدة مخازن فرعية تابعة له)
    public function children(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'parent_id');
    }

    // علاقة المخزن بالمنتجات المخزنة بداخله مع جلب كمية التخزين ورقم الرف
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_warehouse')
            ->withPivot('quantity', 'average_cost', 'shelf_number')
            ->withTimestamps();
    }

    public function stocktakes(): HasMany
    {
        return $this->hasMany(Stocktake::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
