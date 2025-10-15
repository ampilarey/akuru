<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_arabic',
        'name_dhivehi',
        'description',
        'description_arabic',
        'description_dhivehi',
        'address',
        'phone',
        'email',
        'website',
        'logo',
        'principal_name',
        'principal_name_arabic',
        'principal_name_dhivehi',
        'established_year',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function classes()
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }
}
