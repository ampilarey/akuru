<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\StaffContractStatus;
use App\Domains\HR\Enums\StaffContractType;
use Illuminate\Database\Eloquent\Model;

class StaffContract extends Model
{
    protected $fillable = [
        'staff_profile_id',
        'contract_type',
        'start_date',
        'end_date',
        'probation_until',
        'basic_salary',
        'allowances',
        'working_hours_per_week',
        'document_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'contract_type' => StaffContractType::class,
            'status' => StaffContractStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_until' => 'date',
            'basic_salary' => 'decimal:2',
            'allowances' => 'array',
        ];
    }
}
