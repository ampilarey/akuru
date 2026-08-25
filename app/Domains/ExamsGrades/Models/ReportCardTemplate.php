<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCardTemplate extends Model
{
    protected $fillable = [
        'name',
        'applies_to',
        'sections',
        'header',
        'footer',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'applies_to' => 'array',
            'sections' => 'array',
            'active' => 'boolean',
        ];
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class, 'template_id');
    }
}
