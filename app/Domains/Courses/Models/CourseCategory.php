<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'order',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }
}
