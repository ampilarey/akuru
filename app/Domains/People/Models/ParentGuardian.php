<?php

namespace App\Domains\People\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentGuardian extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'first_name_arabic',
        'first_name_dhivehi',
        'last_name',
        'last_name_arabic',
        'last_name_dhivehi',
        'phone',
        'email',
        'address',
        'occupation',
        'occupation_arabic',
        'occupation_dhivehi',
        'national_id',
        'relationship',
        'photo',
        'is_emergency_contact',
    ];

    protected $casts = [
        'is_emergency_contact' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_parent')
            ->withPivot('relationship', 'is_primary_contact')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** Deploy 2 read compatibility: enrollment views used User.name on RS guardians. */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }
}
