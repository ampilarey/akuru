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
     * @return array{code: string, message: string, can_generate: bool}
     */
    public function execute(?int $teacherId, ?int $userId, string $date): array
    {
        if ($teacherId === null) {
            return [
                'code' => 'no_teacher',
                'message' => 'No teacher profile is linked to this login. An admin must create a teachers row for you before Today can list registers.',
                'can_generate' => false,
            ];
        }

        if (Period::query()->exists() === false) {
            return [
                'code' => 'no_periods',
                'message' => 'No periods exist. An admin with timetable access must add periods, then place you on the timetable.',
                'can_generate' => false,
            ];
        }

        $year = AcademicYear::query()->where('status', AcademicYearStatus::Active)->first()
            ?? AcademicYear::query()->where('is_current', true)->first();

        if ($year === null) {
            return [
                'code' => 'no_year',
                'message' => 'No academic year is active. An admin must activate a year before registers can be generated.',
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
                'message' => 'This date is a non-teaching day (holiday or similar). Registers are not generated.',
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
                'message' => 'No timetable slots for you on '.$weekday.'. An admin must place you (or your class) on the timetable for this weekday.',
                'can_generate' => false,
            ];
        }

        return [
            'code' => 'not_generated',
            'message' => 'Today’s registers have not been generated yet. You can create yours for this date. School-wide generation is still limited to staff with registers.manage.',
            'can_generate' => true,
        ];
    }
}
