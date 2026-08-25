<?php

namespace App\Domains\ExamsGrades\Models;

use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    protected $fillable = [
        'name',
        'name_arabic',
        'name_dhivehi',
        'code',
        'default_weight',
        'counts_toward_final',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'code' => ExamTypeCode::class,
            'default_weight' => 'integer',
            'counts_toward_final' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
