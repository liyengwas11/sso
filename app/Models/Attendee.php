<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Attendee extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'organisation',
        'email',
        'role_title',
    ];

    public function eventPasses(): HasMany
    {
        return $this->hasMany(EventPass::class);
    }

    /**
     * Register media collections for attendee photos
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile() // uploading a new photo replaces the old one
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Register media conversions for thumbnails
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10);
    }

    /**
     * Get the attendee's photo URL
     * This is the method that's being called in the scan result
     */
    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photo') ?: null;
    }

    /**
     * Get the attendee's photo thumbnail URL
     */
    public function photoThumbnail(): ?string
    {
        $media = $this->getFirstMedia('photo');
        return $media ? $media->getUrl('thumb') : null;
    }

    /**
     * Alias for photoUrl() - for consistency
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photoUrl();
    }

    /**
     * Get the attendee's photo as a base64 data URL (for QR codes/pass previews)
     */
    public function photoBase64(): ?string
    {
        $media = $this->getFirstMedia('photo');
        if (!$media) return null;

        $path = $media->getPath();
        if (!file_exists($path)) return null;

        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}
