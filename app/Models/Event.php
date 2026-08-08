<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'venue',
        'start_date',
        'end_date',
        'is_recurring',
        'parent_event_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'parent_event_id');
    }

    public function nextYearInstance(): HasMany
    {
        return $this->hasMany(Event::class, 'parent_event_id');
    }

    public function passes(): HasMany
    {
        return $this->hasMany(EventPass::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(EventDay::class)->orderBy('day_number');
    }

    public function isMultiDay(): bool
    {
        return ! $this->start_date->isSameDay($this->end_date);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }
}
