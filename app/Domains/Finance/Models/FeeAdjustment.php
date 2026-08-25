<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\FeeAdjustmentAppliesTo;
use App\Domains\Finance\Enums\FeeAdjustmentBasis;
use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Enums\FeeAdjustmentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Administrative school-fee reduction. Not a Commerce discount code (L4).
 */
class FeeAdjustment extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'type',
        'basis',
        'value',
        'applies_to',
        'item_types',
        'approved_by',
        'valid_from',
        'valid_until',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => FeeAdjustmentType::class,
            'basis' => FeeAdjustmentBasis::class,
            'applies_to' => FeeAdjustmentAppliesTo::class,
            'status' => FeeAdjustmentStatus::class,
            'value' => 'decimal:2',
            'item_types' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }
}
