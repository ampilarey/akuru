<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function publishedPosts(): HasMany
    {
        return $this->hasMany(Post::class)->published();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Accessors
    public function getPostCountAttribute()
    {
        return $this->publishedPosts()->count();
    }

    public function getSlugAttribute()
    {
        return \Str::slug($this->name);
    }

    // Methods
    public function getColorClassAttribute()
    {
        return match($this->color) {
            'red' => 'bg-red-100 text-red-800',
            'blue' => 'bg-blue-100 text-blue-800',
            'green' => 'bg-green-100 text-green-800',
            'yellow' => 'bg-yellow-100 text-yellow-800',
            'purple' => 'bg-purple-100 text-purple-800',
            'pink' => 'bg-pink-100 text-pink-800',
            'indigo' => 'bg-indigo-100 text-indigo-800',
            'gray' => 'bg-gray-100 text-gray-800',
            default => 'bg-brandMaroon-100 text-brandMaroon-800',
        };
    }

    public function getIconHtmlAttribute()
    {
        return match($this->icon) {
            'news' => '📰',
            'announcement' => '📢',
            'event' => '📅',
            'academic' => '🎓',
            'quran' => '📖',
            'islamic' => '🕌',
            'community' => '👥',
            'education' => '📚',
            'general' => '📝',
            default => '📄',
        };
    }
}