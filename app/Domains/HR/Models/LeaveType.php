<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\LeaveTypeCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_arabic',
        'name_dhivehi',
        'days_per_year',
        'carry_over_max',
        'requires_document',
        'paid',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'code' => LeaveTypeCode::class,
            'days_per_year' => 'decimal:1',
            'carry_over_max' => 'decimal:1',
            'requires_document' => 'boolean',
            'paid' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }
}
