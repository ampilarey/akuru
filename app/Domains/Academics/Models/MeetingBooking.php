<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\MeetingBookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingBooking extends Model
{
    protected $fillable = [
        'meeting_slot_id',
        'academic_year_id',
        'term_id',
        'student_id',
        'booked_by',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeetingBookingStatus::class,
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(MeetingSlot::class, 'meeting_slot_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
