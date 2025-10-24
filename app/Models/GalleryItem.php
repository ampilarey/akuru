<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryItem extends Model
{
    protected $fillable = [
        'gallery_album_id',
        'title',
        'description',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'dimensions',
        'thumbnail_path',
        'alt_text',
        'caption',
        'tags',
        'sort_order',
        'view_count',
        'download_count',
        'is_featured',
        'is_public',
        'meta',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'tags' => 'array',
        'is_featured' => 'boolean',
        'is_public' => 'boolean',
        'meta' => 'array',
    ];

    // Relationships
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    public function scopeByAlbum($query, $albumId)
    {
        return $query->where('gallery_album_id', $albumId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at', 'desc');
    }

    public function scopePopular($query)
    {
        return $query->orderBy('view_count', 'desc');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail_path) {
            return asset('storage/' . $this->thumbnail_path);
        }
        
        // For images, return the file path as thumbnail
        if ($this->file_type === 'image') {
            return $this->file_url;
        }
        
        // For videos, return a video thumbnail placeholder
        if ($this->file_type === 'video') {
            return asset('images/video-thumbnail.jpg');
        }
        
        return asset('images/file-thumbnail.jpg');
    }

    public function getFormattedFileSizeAttribute()
    {
        if (!$this->file_size) return 'Unknown';
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileTypeIconAttribute()
    {
        return match($this->file_type) {
            'image' => '🖼️',
            'video' => '🎥',
            'document' => '📄',
            'audio' => '🎵',
            default => '📁',
        };
    }

    public function getIsImageAttribute()
    {
        return $this->file_type === 'image';
    }

    public function getIsVideoAttribute()
    {
        return $this->file_type === 'video';
    }

    public function getIsDocumentAttribute()
    {
        return $this->file_type === 'document';
    }

    public function getAspectRatioAttribute()
    {
        if (!$this->dimensions || !isset($this->dimensions['width']) || !isset($this->dimensions['height'])) {
            return '16:9'; // Default aspect ratio
        }
        
        $width = $this->dimensions['width'];
        $height = $this->dimensions['height'];
        
        // Calculate aspect ratio
        $gcd = $this->gcd($width, $height);
        $ratioWidth = $width / $gcd;
        $ratioHeight = $height / $gcd;
        
        return $ratioWidth . ':' . $ratioHeight;
    }

    // Methods
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }

    public function getRelatedItems($limit = 4)
    {
        return static::public()
                    ->where('gallery_album_id', $this->gallery_album_id)
                    ->where('id', '!=', $this->id)
                    ->ordered()
                    ->limit($limit)
                    ->get();
    }

    public function getFeaturedItems($limit = 8)
    {
        return static::public()
                    ->featured()
                    ->ordered()
                    ->limit($limit)
                    ->get();
    }

    public function getRecentItems($limit = 8)
    {
        return static::public()
                    ->recent()
                    ->limit($limit)
                    ->get();
    }

    private function gcd($a, $b)
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}