<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\MeetingSlotStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingSlot extends Model
{
    protected $fillable = [
        'academic_year_id',
        'term_id',
        'teacher_id',
        'class_id',
        'room_id',
        'title',
        'title_arabic',
        'title_dhivehi',
        'date',
        'start_time',
        'end_time',
        'capacity',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'capacity' => 'integer',
            'status' => MeetingSlotStatus::class,
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(MeetingBooking::class);
    }
}
