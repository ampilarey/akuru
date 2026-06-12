<?php

namespace App\Domains\People\Models;

use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Grade;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\QuranProgress;
use App\Domains\Settings\Models\School;
use App\Domains\Academics\Models\Subject;
use App\Domains\Academics\Models\Timetable;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'teacher_id',
        'first_name',
        'first_name_arabic',
        'first_name_dhivehi',
        'last_name',
        'last_name_arabic',
        'last_name_dhivehi',
        'date_of_birth',
        'gender',
        'national_id',
        'phone',
        'address',
        'email',
        'qualification',
        'qualification_arabic',
        'qualification_dhivehi',
        'specialization',
        'specialization_arabic',
        'specialization_dhivehi',
        'joining_date',
        'salary',
        'photo',
        'status',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'salary' => 'decimal:2',
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

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject')
            ->withPivot('is_primary_teacher')
            ->withTimestamps();
    }

    public function classes()
    {
        return $this->hasMany(ClassRoom::class, 'class_teacher_id');
    }

    public function quranProgress()
    {
        return $this->hasMany(QuranProgress::class);
    }

    public function hifzEnrollments()
    {
        return $this->hasMany(HifzEnrollment::class);
    }

    public function hifzSessions()
    {
        return $this->hasMany(HifzSession::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
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

    public function students()
    {
        return $this->hasManyThrough(Student::class, ClassRoom::class, 'class_teacher_id', 'class_id');
    }
}
