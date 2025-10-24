<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class GalleryAlbum extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_image',
        'type',
        'status',
        'is_featured',
        'is_public',
        'sort_order',
        'item_count',
        'meta',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_public' => 'boolean',
        'meta' => 'array',
    ];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }

    public function publicItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->where('is_public', true);
    }

    public function featuredItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->where('is_featured', true);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

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
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    // Accessors
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }

    public function getCoverImageUrlAttribute()
    {
        if (!$this->cover_image) {
            // Return first item's image as cover
            $firstItem = $this->items()->where('file_type', 'image')->first();
            return $firstItem ? $firstItem->file_path : asset('images/gallery-placeholder.jpg');
        }
        
        return asset('storage/' . $this->cover_image);
    }

    public function getItemCountAttribute()
    {
        return $this->publicItems()->count();
    }

    public function getTypeIconAttribute()
    {
        return match($this->type) {
            'photos' => '📸',
            'videos' => '🎥',
            'mixed' => '📁',
            default => '📁',
        };
    }

    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            'published' => 'green',
            'draft' => 'gray',
            'archived' => 'red',
            default => 'gray',
        };
    }

    // Methods
    public function updateItemCount()
    {
        $this->item_count = $this->publicItems()->count();
        $this->save();
    }

    public function getFeaturedAlbums($limit = 6)
    {
        return static::published()
                    ->public()
                    ->featured()
                    ->ordered()
                    ->limit($limit)
                    ->get();
    }

    public function getRecentAlbums($limit = 6)
    {
        return static::published()
                    ->public()
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    public function getAlbumsByType($type, $limit = 12)
    {
        return static::published()
                    ->public()
                    ->byType($type)
                    ->ordered()
                    ->limit($limit)
                    ->get();
    }
}