<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\OnboardingKind;
use Illuminate\Database\Eloquent\Model;

class StaffOnboardingItem extends Model
{
    protected $fillable = [
        'staff_profile_id',
        'kind',
        'item',
        'done',
        'done_by',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => OnboardingKind::class,
            'done' => 'boolean',
            'done_at' => 'datetime',
        ];
    }
}
