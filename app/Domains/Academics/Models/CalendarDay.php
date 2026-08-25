<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\CalendarDayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarDay extends Model
{
    protected $fillable = [
        'academic_year_id',
        'date',
        'type',
        'title',
        'title_arabic',
        'title_dhivehi',
        'affects_timetable',
        'event_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => CalendarDayType::class,
            'affects_timetable' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
