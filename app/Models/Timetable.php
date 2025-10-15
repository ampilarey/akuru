<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'period_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'room_arabic',
        'room_dhivehi',
        'start_date',
        'end_date',
        'frequency',
        'recurring_days',
        'is_recurring',
        'color',
        'description',
        'description_arabic',
        'description_dhivehi',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'start_date' => 'date',
        'end_date' => 'date',
        'recurring_days' => 'array',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    // Helper methods
    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }

    public function getFormattedTimeAttribute()
    {
        return \Carbon\Carbon::parse($this->start_time)->format('H:i') . ' - ' . 
               \Carbon\Carbon::parse($this->end_time)->format('H:i');
    }
}