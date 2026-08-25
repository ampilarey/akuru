<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    protected $fillable = [
        'subject_id',
        'name',
        'name_arabic',
        'name_dhivehi',
        'description',
        'sort_order',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(CompetencyAssessment::class);
    }
}
