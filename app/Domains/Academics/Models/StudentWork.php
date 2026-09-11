<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentWork extends Model
{
    protected $table = 'student_work';

    protected $fillable = [
        'academic_year_id',
        'student_id',
        'photo_media_id',
        'uploaded_by',
        'title',
        'note',
        'done_on',
        'hidden_at',
        'hidden_by',
    ];

    protected $casts = [
        'done_on' => 'date',
        'hidden_at' => 'datetime',
    ];

    /** What a family may see: not hidden. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }
}
