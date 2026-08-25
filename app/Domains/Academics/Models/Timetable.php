<?php

namespace App\Domains\Academics\Models;

use App\Domains\People\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'academic_year_id',
        'term_id',
        'period_id',
        'room_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'room_arabic',
        'room_dhivehi',
        'start_date',
        'end_date',
        'valid_from',
        'valid_until',
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
        'valid_from' => 'date',
        'valid_until' => 'date',
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

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function roomRecord()
    {
        return $this->belongsTo(Room::class, 'room_id');
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
        return \Carbon\Carbon::parse($this->start_time)->format('H:i').' - '.
               \Carbon\Carbon::parse($this->end_time)->format('H:i');
    }
}
