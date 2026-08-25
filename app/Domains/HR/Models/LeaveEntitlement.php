<?php

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveEntitlement extends Model
{
    protected $fillable = [
        'staff_profile_id',
        'leave_type_id',
        'academic_year_id',
        'entitled_days',
        'carried_over_days',
        'adjusted_days',
    ];

    protected function casts(): array
    {
        return [
            'entitled_days' => 'decimal:1',
            'carried_over_days' => 'decimal:1',
            'adjusted_days' => 'decimal:1',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(LeaveLedger::class, 'entitlement_id');
    }
}
