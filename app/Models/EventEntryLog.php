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
        'type',
        'gate_location',
        'original_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'flagged' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    // ========== RELATIONSHIPS ==========

    /**
     * Get the event pass associated with this log
     */
    public function eventPass(): BelongsTo
    {
        return $this->belongsTo(EventPass::class, 'event_pass_id');
    }

    /**
     * Get the event day associated with this log
     */
    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class, 'event_day_id');
    }

    /**
     * Get the staff member who scanned this entry
     */
    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /**
     * Get the staff member who reviewed this flag
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the original staff member (for duplicate check-ins)
     */
    public function originalStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_staff_id');
    }

    // ========== SCOPES ==========

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('flagged', true);
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->whereNull('reviewed_at');
    }

    public function scopeCheckIns(Builder $query): Builder
    {
        return $query->where('type', 'check-in');
    }

    public function scopeCheckOuts(Builder $query): Builder
    {
        return $query->where('type', 'check-out');
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('result', ['granted', 'override_granted']);
    }

    // ========== HELPER METHODS ==========

    public function isCheckIn(): bool
    {
        return $this->type === 'check-in';
    }

    public function isCheckOut(): bool
    {
        return $this->type === 'check-out';
    }

    public function isSuccessful(): bool
    {
        return in_array($this->result, ['granted', 'override_granted']);
    }

    public function isDuplicate(): bool
    {
        return $this->result === 'duplicate_attempt';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->type) {
            'check-in' => 'Check-In',
            'check-out' => 'Check-Out',
            default => 'Unknown',
        };
    }

    public function getResultLabelAttribute(): string
    {
        return match ($this->result) {
            'granted' => 'Granted',
            'override_granted' => 'Overridden',
            'denied' => 'Denied',
            'duplicate_attempt' => 'Duplicate Attempt',
            default => 'Unknown',
        };
    }
}
