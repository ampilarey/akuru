<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Post extends Model
{
    protected $fillable = [
        'post_category_id',
        'title',
        'slug',
        'summary',
        'body',
        'cover_image',
        'is_published',
        'published_at',
        'author_id',
        'is_featured',
        'is_pinned',
        'view_count',
        'like_count',
        'share_count',
        'reading_time',
        'tags',
        'meta_description',
        'meta_keywords',
        'meta',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_pinned' => 'boolean',
        'tags' => 'array',
        'meta' => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                    ->where('published_at', '<=', now());
    }

    public function scopePublic($query)
    {
        return $query->where('is_published', true)
                    ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('post_category_id', $categoryId);
    }

    public function scopeByTag($query, $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function scopePopular($query)
    {
        return $query->orderBy('view_count', 'desc');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('summary', 'like', "%{$term}%")
              ->orWhere('body', 'like', "%{$term}%")
              ->orWhere('meta_keywords', 'like', "%{$term}%");
        });
    }

    // Accessors
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }

    public function getExcerptAttribute()
    {
        return $this->summary ?: \Str::limit(strip_tags($this->body), 150);
    }

    public function getReadingTimeAttribute()
    {
        if ($this->reading_time) {
            return $this->reading_time;
        }
        
        $wordCount = str_word_count(strip_tags($this->body));
        $minutes = ceil($wordCount / 200); // Average reading speed: 200 words per minute
        
        return $minutes . ' min read';
    }

    public function getFormattedPublishedAtAttribute()
    {
        return $this->published_at->format('M j, Y');
    }

    public function getShortPublishedAtAttribute()
    {
        return $this->published_at->diffForHumans();
    }

    public function getStatusBadgeColorAttribute()
    {
        if (!$this->is_published) return 'gray';
        if ($this->published_at > now()) return 'yellow';
        return 'green';
    }

    // Methods
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    public function incrementLikeCount()
    {
        $this->increment('like_count');
    }

    public function incrementShareCount()
    {
        $this->increment('share_count');
    }

    public function getRelatedPosts($limit = 3)
    {
        return static::published()
                    ->where('id', '!=', $this->id)
                    ->where('post_category_id', $this->post_category_id)
                    ->orderBy('published_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    public function getFeaturedPosts($limit = 5)
    {
        return static::published()
                    ->featured()
                    ->orderBy('published_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    public function getRecentPosts($limit = 5)
    {
        return static::published()
                    ->orderBy('published_at', 'desc')
                    ->limit($limit)
                    ->get();
    }
}
