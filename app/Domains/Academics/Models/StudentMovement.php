<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\MovementDirection;
use App\Domains\Academics\Enums\MovementSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentMovement extends Model
{
    protected $fillable = [
        'academic_year_id',
        'student_id',
        'direction',
        'at',
        'recorded_by',
        'source',
        'note',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'at' => 'datetime',
        'voided_at' => 'datetime',
        'direction' => MovementDirection::class,
        'source' => MovementSource::class,
    ];

    /** A voided row stays on the record but is not a movement any more. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
