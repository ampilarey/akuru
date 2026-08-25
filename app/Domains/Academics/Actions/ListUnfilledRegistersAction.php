<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\Academics\Models\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListUnfilledRegistersAction
{
    /**
     * Expected/draft logs whose lesson time has passed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $academicYearId = null, ?int $teacherId = null): Collection
    {
        $now = now()->timezone(config('app.timezone'));
        $today = $now->toDateString();

        $logs = LessonLog::query()
            ->whereIn('status', [LessonLogStatus::Expected->value, LessonLogStatus::Draft->value])
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when($teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->orderBy('date')
            ->orderBy('period_id')
            ->get()
            ->filter(fn (LessonLog $log) => $this->isPast($log, $today, $now->format('H:i:s')));

        return app(ListTeacherTodayRegistersAction::class)->serialize($logs->values());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fillRates(?int $academicYearId = null): Collection
    {
        $query = LessonLog::query()
            ->when($academicYearId, fn ($builder) => $builder->where('academic_year_id', $academicYearId));

        $rows = $query
            ->selectRaw('teacher_id, COUNT(*) as total, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as filled', [
                LessonLogStatus::Submitted->value,
                LessonLogStatus::Locked->value,
            ])
            ->groupBy('teacher_id')
            ->get();

        $teachers = DB::table('teachers')
            ->whereIn('id', $rows->pluck('teacher_id'))
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($teachers) {
            $teacher = $teachers[$row->teacher_id] ?? null;
            $total = (int) $row->total;
            $filled = (int) $row->filled;

            return [
                'teacher_id' => (int) $row->teacher_id,
                'teacher_name' => trim(($teacher->first_name ?? '').' '.($teacher->last_name ?? '')),
                'total' => $total,
                'filled' => $filled,
                'rate' => $total === 0 ? 0 : round(($filled / $total) * 100, 1),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function planAdherence(?int $academicYearId = null): Collection
    {
        $plans = DB::table('course_plans')
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->where('status', '!=', 'archived')
            ->get(['id', 'title', 'teacher_id', 'subject_id', 'classroom_id']);

        $topics = DB::table('plan_topics')
            ->whereIn('course_plan_id', $plans->pluck('id'))
            ->selectRaw('course_plan_id, COUNT(*) as total, SUM(is_completed) as completed')
            ->groupBy('course_plan_id')
            ->get()
            ->keyBy('course_plan_id');

        return $plans->map(function ($plan) use ($topics) {
            $stats = $topics[$plan->id] ?? null;
            $total = (int) ($stats->total ?? 0);
            $completed = (int) ($stats->completed ?? 0);

            return [
                'id' => $plan->id,
                'title' => $plan->title,
                'teacher_id' => $plan->teacher_id,
                'total' => $total,
                'completed' => $completed,
                'rate' => $total === 0 ? 0 : round(($completed / $total) * 100, 1),
            ];
        })->values();
    }

    private function isPast(LessonLog $log, string $today, string $nowTime): bool
    {
        $date = $log->date?->toDateString();
        if ($date === null) {
            return false;
        }
        if ($date < $today) {
            return true;
        }
        if ($date > $today) {
            return false;
        }

        if ($log->period_id === null) {
            return false;
        }

        $end = Period::query()->where('id', $log->period_id)->value('end_time');
        if ($end === null) {
            return false;
        }

        return (string) $end <= $nowTime;
    }
}
