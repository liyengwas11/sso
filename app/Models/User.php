<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements HasMedia
{
    use HasFactory, HasRoles, Notifiable, InteractsWithMedia;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function scannedEntries(): HasMany
    {
        return $this->hasMany(EventEntryLog::class, 'scanned_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Register media collections for user profile photos
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    /**
     * Get the user's profile photo URL
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('profile_photo');
        return $media ? $media->getUrl() : null;
    }

    /**
     * Get the user's profile photo thumbnail URL
     */
    public function getPhotoThumbnailAttribute(): ?string
    {
        $media = $this->getFirstMedia('profile_photo');
        return $media ? $media->getUrl('thumb') : null;
    }

    /**
     * Register media conversions
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10);
    }
}
