<?php

namespace App\Domains\People\Models;

use App\Domains\People\Enums\EmploymentType;
use App\Domains\People\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffProfile extends Model
{
    protected $fillable = [
        'user_id',
        'staff_number',
        'first_name',
        'first_name_arabic',
        'first_name_dhivehi',
        'last_name',
        'last_name_arabic',
        'last_name_dhivehi',
        'date_of_birth',
        'gender',
        'national_id',
        'passport',
        'nationality',
        'phone',
        'address',
        'photo',
        'joined_date',
        'employment_type',
        'status',
        'department',
        'designation',
    ];

    protected $attributes = [
        'status' => 'active',
        'nationality' => 'MV',
        'employment_type' => 'full_time',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joined_date' => 'date',
            'employment_type' => EmploymentType::class,
            'status' => StaffStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('domain-models.user'));
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(StaffQualification::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
