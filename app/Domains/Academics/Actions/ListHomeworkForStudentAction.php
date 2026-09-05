<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\HomeworkTick;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\People\Actions\ListTeachersByIdsAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The homework a student can see: lesson logs with something written in the
 * homework box, for the classes they are on.
 *
 * Only **submitted** registers count. A draft register is the teacher's working
 * copy; showing half-typed homework to a family is worse than showing none.
 *
 * Teacher names go through People's ListTeachersByIdsAction rather than a
 * Teacher import — the cross-domain baseline may only shrink (rule 3).
 */
class ListHomeworkForStudentAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $studentId, int $lookbackDays = 30): Collection
    {
        $classIds = DB::table('class_student')
            ->where('student_id', $studentId)
            ->where('status', ClassStudentStatus::Active->value)
            ->pluck('class_id')
            ->all();

        if ($classIds === []) {
            return collect();
        }

        $since = Carbon::now(config('app.timezone'))->startOfDay()->subDays($lookbackDays)->toDateString();

        $logs = LessonLog::query()
            ->whereIn('classroom_id', $classIds)
            ->whereNotNull('homework')
            ->where('homework', '!=', '')
            ->whereIn('status', [LessonLogStatus::Submitted->value, LessonLogStatus::Locked->value])
            ->whereDate('date', '>=', $since)
            ->with(['subject'])
            ->get();

        if ($logs->isEmpty()) {
            return collect();
        }

        $ticked = HomeworkTick::query()
            ->where('student_id', $studentId)
            ->whereIn('lesson_log_id', $logs->pluck('id')->all())
            ->pluck('ticked_at', 'lesson_log_id');

        $names = app(ListTeachersByIdsAction::class)
            ->execute($logs->pluck('teacher_id')->all())
            ->keyBy('id');

        $today = Carbon::now(config('app.timezone'))->startOfDay();

        return $logs
            ->map(function (LessonLog $log) use ($ticked, $names, $today): array {
                $due = $log->homework_due_date;
                $tick = $ticked->get($log->id);

                return [
                    'id' => (int) $log->id,
                    'subject' => (string) ($log->subject?->name ?? ''),
                    'teacher' => $names->get((int) $log->teacher_id)['name'] ?? null,
                    'set_on' => $log->date?->toDateString(),
                    'due_date' => $due?->toDateString(),
                    'homework' => (string) $log->homework,
                    'is_done' => $tick !== null,
                    'done_at' => $tick?->toIso8601String(),
                    // Overdue only means something once a due date exists and
                    // the pupil has not ticked it; homework with no due date is
                    // not late, it is undated.
                    'is_overdue' => $due !== null && $tick === null && $due->lt($today),
                ];
            })
            // Undone first, then soonest due; homework with no due date sorts
            // after dated work rather than jumping the queue with an empty key.
            ->sortBy([
                fn (array $a, array $b): int => ($a['is_done'] ? 1 : 0) <=> ($b['is_done'] ? 1 : 0),
                fn (array $a, array $b): int => ($a['due_date'] ?? '9999-12-31') <=> ($b['due_date'] ?? '9999-12-31'),
                fn (array $a, array $b): int => ($b['set_on'] ?? '') <=> ($a['set_on'] ?? ''),
            ])
            ->values();
    }

    /** Badge count for the E1 home tile: outstanding, not everything. */
    public function outstandingCount(int $studentId): int
    {
        return $this->execute($studentId)
            ->filter(fn (array $row): bool => ! $row['is_done'])
            ->count();
    }
}
