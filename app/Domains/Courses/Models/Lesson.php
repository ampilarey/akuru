<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\LessonStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'course_module_id',
        'title',
        'title_dv',
        'title_ar',
        'slug',
        'description',
        'position',
        'estimated_minutes',
        'status',
        'current_revision_id',
        'is_preview',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_preview' => 'boolean',
            'published_at' => 'datetime',
            'status' => LessonStatus::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('position');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LessonRevision::class);
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(LessonRevision::class, 'current_revision_id');
    }
}
