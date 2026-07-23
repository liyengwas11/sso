<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEntryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_pass_id',
        'event_day_id',
        'scanned_by',
        'scanned_at',
        'result',
        'reason',
        'device_type',
        'flagged',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'flagged' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function eventPass(): BelongsTo
    {
        return $this->belongsTo(EventPass::class);
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('flagged', true);
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->whereNull('reviewed_at');
    }
}
