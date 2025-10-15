<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MediaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'uploaded_by',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'media_type',
        'file_size',
        'mime_type',
        'title',
        'title_arabic',
        'title_dhivehi',
        'description',
        'description_arabic',
        'description_dhivehi',
        'metadata',
        'thumbnail_path',
        'width',
        'height',
        'duration',
        'order',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the gallery this item belongs to
     */
    public function gallery()
    {
        return $this->belongsTo(MediaGallery::class, 'gallery_id');
    }

    /**
     * Get the user who uploaded this item
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope for images
     */
    public function scopeImages($query)
    {
        return $query->where('media_type', 'image');
    }

    /**
     * Scope for videos
     */
    public function scopeVideos($query)
    {
        return $query->where('media_type', 'video');
    }

    /**
     * Scope for featured items
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for active items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by order field
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get the file URL
     */
    public function getUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Get the thumbnail URL
     */
    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail_path) {
            return asset('storage/' . $this->thumbnail_path);
        }
        
        if ($this->media_type === 'image') {
            return $this->url;
        }
        
        return asset('images/default-thumbnail.jpg');
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get formatted duration for videos/audio
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) return null;
        
        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get the aspect ratio for images/videos
     */
    public function getAspectRatioAttribute()
    {
        if (!$this->width || !$this->height) return null;
        
        $gcd = function($a, $b) use (&$gcd) {
            return $b ? $gcd($b, $a % $b) : $a;
        };
        
        $divisor = $gcd($this->width, $this->height);
        return ($this->width / $divisor) . ':' . ($this->height / $divisor);
    }

    /**
     * Check if item is an image
     */
    public function getIsImageAttribute()
    {
        return $this->media_type === 'image';
    }

    /**
     * Check if item is a video
     */
    public function getIsVideoAttribute()
    {
        return $this->media_type === 'video';
    }

    /**
     * Check if item is an audio file
     */
    public function getIsAudioAttribute()
    {
        return $this->media_type === 'audio';
    }

    /**
     * Check if item is a document
     */
    public function getIsDocumentAttribute()
    {
        return $this->media_type === 'document';
    }
}