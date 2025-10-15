<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MediaGallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'created_by',
        'title',
        'title_arabic',
        'title_dhivehi',
        'description',
        'description_arabic',
        'description_dhivehi',
        'type',
        'visibility',
        'tags',
        'cover_image',
        'event_date',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'tags' => 'array',
        'event_date' => 'date',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the school this gallery belongs to
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user who created this gallery
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all media items in this gallery
     */
    public function mediaItems()
    {
        return $this->hasMany(MediaItem::class, 'gallery_id');
    }

    /**
     * Get featured media items
     */
    public function featuredItems()
    {
        return $this->hasMany(MediaItem::class, 'gallery_id')->where('is_featured', true);
    }

    /**
     * Get the first media item as cover
     */
    public function getCoverItemAttribute()
    {
        return $this->mediaItems()->orderBy('order')->first();
    }

    /**
     * Get the total number of items in the gallery
     */
    public function getItemCountAttribute()
    {
        return $this->mediaItems()->count();
    }

    /**
     * Get the total file size of all items
     */
    public function getTotalSizeAttribute()
    {
        return $this->mediaItems()->sum('file_size');
    }

    /**
     * Get formatted file size
     */
    public function getFormattedTotalSizeAttribute()
    {
        $bytes = $this->total_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope for public galleries
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope for featured galleries
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for active galleries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for galleries by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for galleries with tags
     */
    public function scopeWithTags($query, $tags)
    {
        return $query->whereJsonContains('tags', $tags);
    }

    /**
     * Get the gallery URL
     */
    public function getUrlAttribute()
    {
        return route('media-galleries.show', $this->id);
    }

    /**
     * Get the cover image URL
     */
    public function getCoverImageUrlAttribute()
    {
        if ($this->cover_image) {
            return asset('storage/' . $this->cover_image);
        }
        
        $coverItem = $this->cover_item;
        if ($coverItem && $coverItem->media_type === 'image') {
            return asset('storage/' . $coverItem->file_path);
        }
        
        return asset('images/default-gallery-cover.jpg');
    }
}