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
     * The specific days this pass grants entry for. An empty
     * collection is NOT treated as "all days" anywhere in this
     * codebase — PassService::issuePass always attaches at least one
     * day explicitly, so ambiguity here would indicate a bug, not a
     * valid "unrestricted" state.
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
     * The most recent successful (granted or override-granted) entry
     * ON A GIVEN DAY — anti-passback is scoped per-day for multi-day
     * events, since re-entering on Day 2 isn't a duplicate of Day 1.
     */
    public function lastGrantedEntryForDay(EventDay $day): ?EventEntryLog
    {
        return $this->entryLogs()
            ->where('event_day_id', $day->id)
            ->whereIn('result', ['granted', 'override_granted'])
            ->latest('scanned_at')
            ->first();
    }
}
