<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    protected $fillable = [
        'name',
        'disk',
        'path',
        'status',
        'size_bytes',
        'checksum',
        'started_at',
        'completed_at',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
