<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ChartAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'parent_id', 'system_key', 'is_group', 'is_active'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (ChartAccount $account): void {
            if ($account->exists && $account->isDirty('type') &&
                ($account->journalLines()->exists() || $account->children()->exists() || $account->treasuries()->exists())) {
                throw ValidationException::withMessages([
                    'type' => 'لا يمكن تغيير طبيعة حساب مرتبط بقيود أو خزائن أو حسابات فرعية.',
                ]);
            }
            if ($account->exists && $account->isDirty('is_group') && ! $account->is_group &&
                ($account->children()->exists() || $account->journalLines()->exists() || $account->treasuries()->exists())) {
                throw ValidationException::withMessages([
                    'is_group' => 'لا يمكن تحويل حساب مستخدم أو أب لحسابات أخرى إلى حساب تفصيلي.',
                ]);
            }

            $ancestorId = (int) ($account->parent_id ?? 0);
            $visited = [];
            while ($ancestorId > 0) {
                if ($account->exists && $ancestorId === (int) $account->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'لا يمكن ربط الحساب بنفسه أو بأحد حساباته الفرعية.',
                    ]);
                }
                if (isset($visited[$ancestorId])) {
                    throw ValidationException::withMessages(['parent_id' => 'شجرة الحسابات تحتوي على حلقة غير صالحة.']);
                }
                $visited[$ancestorId] = true;

                $parent = self::query()->find($ancestorId);
                if (! $parent || ! $parent->is_group || $parent->type !== $account->type) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'يجب اختيار حساب أب تجميعي من نفس طبيعة الحساب.',
                    ]);
                }
                $ancestorId = (int) ($parent->parent_id ?? 0);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function treasuries(): HasMany
    {
        return $this->hasMany(Treasury::class, 'account_id');
    }
}
