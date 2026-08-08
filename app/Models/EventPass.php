<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPass extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'attendee_id',
        'token',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function entryLogs(): HasMany
    {
        return $this->hasMany(EventEntryLog::class);
    }

    /**
     * The specific days this pass grants entry for.
     */
    public function days(): BelongsToMany
    {
        return $this->belongsToMany(EventDay::class, 'event_pass_days');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function isAccreditedFor(EventDay $day): bool
    {
        return $this->days->contains('id', $day->id);
    }

    /**
     * The most recent successful entry ON A GIVEN DAY
     */
    public function lastGrantedEntryForDay(EventDay $day): ?EventEntryLog
    {
        return $this->entryLogs()
            ->where('event_day_id', $day->id)
            ->whereIn('result', ['granted', 'override_granted'])
            ->latest('scanned_at')
            ->first();
    }

    /**
     * Get the current attendance status for this pass.
     * Returns: 'not_checked_in' | 'checked_in' | 'checked_out'
     */
    public function getCurrentStatusAttribute(): string
    {
        $latest = $this->entryLogs()
            ->whereIn('result', ['granted', 'override_granted'])
            ->latest('scanned_at')
            ->first();

        if (!$latest) {
            return 'not_checked_in';
        }

        return $latest->type; // 'check-in' or 'check-out'
    }

    /**
     * Get the current attendance status for a specific day.
     */
    public function getCurrentStatusForDay(EventDay $day): string
    {
        $latest = $this->entryLogs()
            ->where('event_day_id', $day->id)
            ->whereIn('result', ['granted', 'override_granted'])
            ->latest('scanned_at')
            ->first();

        if (!$latest) {
            return 'not_checked_in';
        }

        return $latest->type; // 'check-in' or 'check-out'
    }

    /**
     * Get the most recent successful check-in log.
     */
    public function getLatestCheckInAttribute(): ?EventEntryLog
    {
        return $this->entryLogs()
            ->where('type', 'check-in')
            ->whereIn('result', ['granted', 'override_granted'])
            ->latest('scanned_at')
            ->first();
    }

    /**
     * Get the most recent successful check-out log.
     */
    public function getLatestCheckOutAttribute(): ?EventEntryLog
    {
        return $this->entryLogs()
            ->where('type', 'check-out')
            ->whereIn('result', ['granted', 'override_granted'])
            ->latest('scanned_at')
            ->first();
    }

    /**
     * Check if the attendee is currently checked in (for today).
     */
    public function isCheckedInToday(): bool
    {
        $today = $this->event->days()
            ->whereDate('date', now()->toDateString())
            ->first();

        if (!$today) {
            return false;
        }

        return $this->getCurrentStatusForDay($today) === 'checked_in';
    }

    /**
     * Check if the attendee is currently checked out (for today).
     */
    public function isCheckedOutToday(): bool
    {
        $today = $this->event->days()
            ->whereDate('date', now()->toDateString())
            ->first();

        if (!$today) {
            return false;
        }

        return $this->getCurrentStatusForDay($today) === 'checked_out';
    }
}
