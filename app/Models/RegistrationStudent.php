<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationStudent extends Model
{
    protected $table = 'registration_students';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'dob',
        'gender',
        'national_id',
        'passport',
    ];

    protected function casts(): array
    {
        return [
            'dob'         => 'date',
            'national_id' => 'encrypted',
            'passport'    => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_guardians', 'student_id', 'guardian_user_id')
            ->withPivot('relationship', 'is_primary')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'student_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_enrollments', 'student_id', 'course_id')
            ->withTimestamps();
    }

    public function age(): int
    {
        return Carbon::parse($this->dob)->age;
    }

    public function isAdult(): bool
    {
        return $this->age() >= 18;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
