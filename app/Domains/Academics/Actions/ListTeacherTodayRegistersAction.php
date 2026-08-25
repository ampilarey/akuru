<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\LessonLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListTeacherTodayRegistersAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $teacherId, ?string $date = null): Collection
    {
        $day = $date ?? now()->timezone(config('app.timezone'))->toDateString();

        $logs = LessonLog::query()
            ->where('teacher_id', $teacherId)
            ->whereDate('date', $day)
            ->orderBy('period_id')
            ->get();

        return $this->serialize($logs);
    }

    /**
     * @param  Collection<int, LessonLog>  $logs
     * @return Collection<int, array<string, mixed>>
     */
    public function serialize(Collection $logs): Collection
    {
        $classIds = $logs->pluck('classroom_id')->filter()->unique()->all();
        $subjectIds = $logs->pluck('subject_id')->filter()->unique()->all();
        $periodIds = $logs->pluck('period_id')->filter()->unique()->all();

        $classes = $classIds === [] ? collect() : DB::table('classes')->whereIn('id', $classIds)->get(['id', 'name', 'section'])->keyBy('id');
        $subjects = $subjectIds === [] ? collect() : DB::table('subjects')->whereIn('id', $subjectIds)->get(['id', 'name', 'code'])->keyBy('id');
        $periods = $periodIds === [] ? collect() : DB::table('periods')->whereIn('id', $periodIds)->get(['id', 'name', 'start_time', 'end_time'])->keyBy('id');

        return $logs->map(function (LessonLog $log) use ($classes, $subjects, $periods) {
            $class = $classes[$log->classroom_id] ?? null;
            $subject = $subjects[$log->subject_id] ?? null;
            $period = $log->period_id ? ($periods[$log->period_id] ?? null) : null;

            return [
                'id' => $log->id,
                'date' => $log->date?->toDateString(),
                'status' => $log->status?->value,
                'teacher_id' => $log->teacher_id,
                'subject_id' => $log->subject_id,
                'classroom_id' => $log->classroom_id,
                'period_id' => $log->period_id,
                'class_name' => trim(($class->name ?? '').' '.($class->section ?? '')),
                'subject_name' => $subject->name ?? '',
                'period_name' => $period->name ?? null,
                'period_start' => $period->start_time ?? null,
                'period_end' => $period->end_time ?? null,
                'taught_summary' => $log->taught_summary,
                'plan_topic_id' => $log->plan_topic_id,
                'submitted_at' => $log->submitted_at?->toIso8601String(),
            ];
        })->values();
    }
}
