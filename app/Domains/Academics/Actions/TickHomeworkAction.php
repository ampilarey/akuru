<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\HomeworkTick;
use App\Domains\Academics\Models\LessonLog;
use Illuminate\Validation\ValidationException;

/**
 * The pupil marks a piece of homework done, or undoes it.
 *
 * The roster check is the authorisation: a pupil may only tick homework set for
 * a class they are actually on. Nothing here touches grading — a self-tick is a
 * checklist, and the moment it counts for marks pupils stop being honest with
 * it (rule: EduPage does the same and for the same reason).
 */
class TickHomeworkAction
{
    public function execute(int $studentId, int $lessonLogId, bool $done = true): bool
    {
        $log = LessonLog::query()->find($lessonLogId);

        if ($log === null || trim((string) $log->homework) === '') {
            throw ValidationException::withMessages([
                'lesson_log_id' => 'That lesson has no homework to tick.',
            ]);
        }

        $onRoster = app(StudentIsOnClassRosterAction::class)
            ->execute($studentId, (int) $log->classroom_id);

        if (! $onRoster) {
            throw ValidationException::withMessages([
                'lesson_log_id' => 'That homework is not for this student.',
            ]);
        }

        if (! $done) {
            HomeworkTick::query()
                ->where('lesson_log_id', $log->id)
                ->where('student_id', $studentId)
                ->delete();

            return false;
        }

        HomeworkTick::query()->updateOrCreate(
            ['lesson_log_id' => $log->id, 'student_id' => $studentId],
            [
                // Rule 10: the tick happens in time, so it carries the year of
                // the lesson it belongs to rather than whatever year is current
                // when the pupil gets round to ticking.
                'academic_year_id' => $this->yearFor($log),
                'ticked_at' => now(),
            ],
        );

        return true;
    }

    /**
     * `lesson_logs.academic_year_id` is nullable, so older rows may not carry
     * one; the class it was taught to always does. Falling back to the class
     * keeps the tick's year honest instead of stamping it with whatever year
     * happens to be current when the pupil gets round to it.
     */
    private function yearFor(LessonLog $log): ?int
    {
        if ($log->academic_year_id !== null) {
            return (int) $log->academic_year_id;
        }

        $classYear = ClassRoom::query()->whereKey($log->classroom_id)->value('academic_year_id');

        if ($classYear === null) {
            throw ValidationException::withMessages([
                'lesson_log_id' => 'That lesson is not attached to an academic year.',
            ]);
        }

        return (int) $classYear;
    }
}
