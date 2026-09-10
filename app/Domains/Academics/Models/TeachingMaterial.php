<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable teaching material.
 *
 * Owned by whoever wrote it and **visible to all staff** — a library one
 * teacher can see is a notebook. Only the owner may edit, so sharing never
 * means losing control of your own wording.
 */
class TeachingMaterial extends Model
{
    protected $fillable = [
        'created_by',
        'subject_id',
        'title',
        'body',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(TeachingMaterialFile::class);
    }

    public function lessonLogs(): BelongsToMany
    {
        return $this->belongsToMany(LessonLog::class, 'lesson_log_material');
    }
}
