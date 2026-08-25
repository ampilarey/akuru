<?php

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalCycle extends Model
{
    protected $fillable = [
        'name',
        'academic_year_id',
        'opens_at',
        'closes_at',
        'template',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'date',
            'closes_at' => 'date',
            'template' => 'array',
        ];
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'cycle_id');
    }
}
