<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'phone',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo',
        'admission_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
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

    // Helper methods
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getFullNameArabicAttribute()
    {
        return $this->first_name_arabic . ' ' . $this->last_name_arabic;
    }

    public function getFullNameDhivehiAttribute()
    {
        return $this->first_name_dhivehi . ' ' . $this->last_name_dhivehi;
    }
}
