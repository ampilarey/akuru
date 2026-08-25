<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    protected $fillable = [
        'academic_year_id',
        'name',
        'applies_to',
        'class_ids',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'applies_to' => FeeStructureAppliesTo::class,
            'status' => FeeStructureStatus::class,
            'class_ids' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class);
    }
}
