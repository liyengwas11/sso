<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceQrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'expires_at',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function generatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'qr_code_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
