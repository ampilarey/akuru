<?php

namespace App\Domains\People\Models;

use App\Domains\People\Enums\SensitiveNoteCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentSensitiveNote extends Model
{
    protected $fillable = [
        'academic_year_id',
        'student_id',
        'author_id',
        'category',
        'summary',
        'body',
        'review_on',
        'archived_at',
        'archived_by',
    ];

    protected $casts = [
        'review_on' => 'date',
        'archived_at' => 'datetime',
        'category' => SensitiveNoteCategory::class,
    ];

    public function scopeInUse(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
