<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use Illuminate\Database\Eloquent\Model;

class FeeItem extends Model
{
    protected $fillable = [
        'name',
        'name_arabic',
        'name_dhivehi',
        'description',
        'default_amount',
        'currency',
        'type',
        'frequency',
        'is_mandatory',
        'is_active',
        'applicable_grades',
    ];

    protected function casts(): array
    {
        return [
            'type' => FeeItemType::class,
            'frequency' => FeeFrequency::class,
            'default_amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'applicable_grades' => 'array',
        ];
    }
}
