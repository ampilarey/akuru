<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Course extends Model
{
    protected $fillable = [
        'course_category_id',
        'title',
        'slug',
        'short_desc',
        'body',
        'cover_image',
        'language',
        'level',
        'schedule',
        'fee',
        'status',
        'seats',
        'meta',
    ];

    protected $casts = [
        'schedule' => 'array',
        'fee' => 'decimal:2',
        'meta' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    public function admissionApplications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language)->orWhere('language', 'mixed');
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level)->orWhere('level', 'all');
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }
}
