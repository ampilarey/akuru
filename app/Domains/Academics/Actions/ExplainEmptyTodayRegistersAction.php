<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CalendarDay;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Timetable;
use Carbon\Carbon;

class ExplainEmptyTodayRegistersAction
{
    /**
     * Said in the page's language (BACKLOG C21, slice OA1); the code is what
     * a caller branches on, never the message.
     *
     * @return array{code: string, message: string, can_generate: bool}
     */
    public function execute(?int $teacherId, ?int $userId, string $date): array
    {
        if ($teacherId === null) {
            return [
                'code' => 'no_teacher',
                'message' => __('academics.empty_no_teacher'),
                'can_generate' => false,
            ];
        }

        if (Period::query()->exists() === false) {
            return [
                'code' => 'no_periods',
                'message' => __('academics.empty_no_periods'),
                'can_generate' => false,
            ];
        }

        $year = AcademicYear::query()->where('status', AcademicYearStatus::Active)->first();

        if ($year === null) {
            return [
                'code' => 'no_year',
                'message' => __('academics.empty_no_year'),
                'can_generate' => false,
            ];
        }

        $day = Carbon::parse($date, config('app.timezone'));
        $blocked = CalendarDay::query()
            ->where('academic_year_id', $year->id)
            ->where('affects_timetable', true)
            ->whereDate('date', $day->toDateString())
            ->exists();

        if ($blocked) {
            return [
                'code' => 'holiday',
                'message' => __('academics.empty_non_teaching'),
                'can_generate' => false,
            ];
        }

        $classIds = $userId
            ? ClassRoom::query()->where('class_teacher_id', $userId)->pluck('id')->all()
            : [];

        $weekday = strtolower($day->englishDayOfWeek);
        $slots = Timetable::query()
            ->where('academic_year_id', $year->id)
            ->where('is_active', true)
            ->whereRaw('LOWER(day_of_week) = ?', [$weekday])
            ->where(function ($inner) use ($teacherId, $classIds) {
                $inner->where('teacher_id', $teacherId);
                if ($classIds !== []) {
                    $inner->orWhereIn('class_id', $classIds);
                }
            })
            ->count();

        if ($slots === 0) {
            return [
                'code' => 'no_timetable',
                'message' => __('academics.empty_no_slots', ['weekday' => __('academics.weekday_'.$weekday)]),
                'can_generate' => false,
            ];
        }

        return [
            'code' => 'not_generated',
            'message' => __('academics.empty_not_generated'),
            'can_generate' => true,
        ];
    }
}
