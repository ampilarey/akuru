<?php

namespace App\Domains\People\Models;

use App\Domains\Academics\Models\Attendance;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Grade;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Models\QuranProgress;
use App\Domains\Identity\Models\User;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\Settings\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'class_id',
        'student_id',
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
        'email',
        'nationality',
        'place_of_birth',
        'phone',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo',
        'admission_date',
        'notes',
        'medical_conditions',
        'allergies',
        'doctor_name',
        'doctor_phone',
        'legacy_registration_student_id',
    ];

    protected $attributes = [
        'status' => 'active',
        'nationality' => 'MV',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
        'status' => StudentStatus::class,
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function parentGuardians()
    {
        return $this->belongsToMany(ParentGuardian::class, 'student_parent')
            ->withPivot('relationship', 'is_primary_contact')
            ->withTimestamps();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(ParentGuardian::class, 'guardian_student', 'student_id', 'guardian_id')
            ->withPivot('relationship', 'is_primary', 'can_pickup', 'financial_responsible')
            ->withTimestamps();
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function quranProgress()
    {
        return $this->hasMany(QuranProgress::class);
    }

    public function hifzEnrollments()
    {
        return $this->hasMany(HifzEnrollment::class);
    }

    public function hifzSessionRecords()
    {
        return $this->hasMany(HifzSessionRecord::class);
    }

    public function hifzMilestones()
    {
        return $this->hasMany(HifzMilestone::class);
    }

    // Helper methods
    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getFullNameArabicAttribute()
    {
        return $this->first_name_arabic.' '.$this->last_name_arabic;
    }

    public function getFullNameDhivehiAttribute()
    {
        return $this->first_name_dhivehi.' '.$this->last_name_dhivehi;
    }
}
