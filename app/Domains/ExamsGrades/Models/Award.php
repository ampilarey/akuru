<?php

namespace App\Domains\ExamsGrades\Models;

use App\Domains\ExamsGrades\Enums\AwardLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Award extends Model
{
    protected $fillable = [
        'title',
        'title_arabic',
        'title_dhivehi',
        'description',
        'level',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'level' => AwardLevel::class,
            'active' => 'boolean',
        ];
    }

    public function studentAwards(): HasMany
    {
        return $this->hasMany(StudentAward::class);
    }
}
