<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'name_arabic',
        'name_dhivehi',
        'code',
        'description',
        'description_arabic',
        'description_dhivehi',
        'type',
        'credits',
        'is_quran_subject',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function classes()
    {
        return $this->belongsToMany(ClassRoom::class, 'class_subject')
                    ->withPivot('hours_per_week', 'is_compulsory')
                    ->withTimestamps();
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
