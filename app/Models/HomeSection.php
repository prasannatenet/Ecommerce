<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomeSection extends Model
{
    protected $fillable = [
        'section_key',
        'title',
        'category_name',
        'subtext',
        'cta_text',
        'cta_link',
        'video_path',
        'poster_image_path',
        'large_image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scoped query for a specific section key.
     */
    public function scopeForSection($query, string $key)
    {
        return $query->where('section_key', $key);
    }

    /**
     * Scoped query for active sections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Return the full URL for the video, or null.
     */
    public function getVideoUrlAttribute(): ?string
    {
        if (empty($this->video_path)) {
            return null;
        }

        return Storage::url($this->video_path);
    }

    /**
     * Return the full URL for the poster image, or null.
     */
    public function getPosterImageUrlAttribute(): ?string
    {
        if (empty($this->poster_image_path)) {
            return null;
        }

        return Storage::url($this->poster_image_path);
    }

    /**
     * Return the full URL for the large image, or null.
     */
    public function getLargeImageUrlAttribute(): ?string
    {
        if (empty($this->large_image_path)) {
            return null;
        }

        return Storage::url($this->large_image_path);
    }

    /**
     * Get the storage disk path prefix used for this model's files.
     */
    public static function storageBasePath(): string
    {
        return 'home-sections';
    }

    public function isFeaturedSection(): bool
    {
        return $this->section_key === 'featured';
    }

    public function isLargeImageSection(): bool
    {
        return $this->section_key === 'large-image';
    }

    /**
     * Ensure the two default home section records exist.
     *
     * Called on homepage load so the view always has records to fall back to.
     * Only creates records if they don't exist - does NOT overwrite admin changes.
     */
    public static function ensureDefaultRecords(): void
    {
        $defaults = [
            [
                'section_key' => 'featured',
                'title' => 'Earring Collection',
                'category_name' => 'Earrings',
                'subtext' => 'Discover our latest collection of handcrafted jewellery.',
                'cta_text' => 'Explore Collection',
                'cta_link' => '/categories',
                'is_active' => true,
            ],
            [
                'section_key' => 'large-image',
                'title' => null,
                'category_name' => null,
                'subtext' => null,
                'cta_text' => null,
                'cta_link' => null,
                'is_active' => true,
            ],
        ];

        foreach ($defaults as $defaultsRow) {
            // Use firstOrCreate to only create if not exists - preserves admin changes
            self::firstOrCreate(
                ['section_key' => $defaultsRow['section_key']],
                $defaultsRow,
            );
        }
    }
}
