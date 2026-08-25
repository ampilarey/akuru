<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\FeeFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructureItem extends Model
{
    protected $fillable = [
        'fee_structure_id',
        'fee_item_id',
        'amount',
        'frequency',
        'due_day',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'frequency' => FeeFrequency::class,
            'due_day' => 'integer',
            'is_mandatory' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }
}
