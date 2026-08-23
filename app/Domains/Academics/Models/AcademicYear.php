<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'description',
        'terms',
        'status',
    ];

    protected $attributes = [
        'status' => 'upcoming',
        'is_current' => false,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'terms' => 'array',
            'status' => AcademicYearStatus::class,
        ];
    }

    public function termRecords(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }
}
