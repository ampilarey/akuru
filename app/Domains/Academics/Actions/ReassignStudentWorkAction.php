<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentWork;
use App\Domains\Academics\Models\StudentWorkReassignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move a photo to the pupil it actually belongs to.
 *
 * **The plan names this as the failure mode EduPage's own documentation
 * records: work sent to the wrong parent.** So this is a first-class verb with
 * its own audit trail, not an edit form that quietly rewrites `student_id`.
 *
 * The correction takes effect immediately — the moment it is saved, the first
 * family stops seeing the photo and the second family starts. What is kept is
 * the record that it happened, because *"which family saw my child's work, and
 * for how long?"* is a question a school will be asked and a silently-updated
 * column cannot answer it.
 *
 * Moving work to the pupil it is already assigned to is refused rather than
 * writing a no-op audit row that would make the history harder to read.
 */
class ReassignStudentWorkAction
{
    public function execute(int $workId, int $toStudentId, int $staffUserId): StudentWork
    {
        $work = StudentWork::query()->find($workId);

        if ($work === null) {
            throw ValidationException::withMessages([
                'work' => 'That piece of work no longer exists.',
            ]);
        }

        if ($toStudentId <= 0) {
            throw ValidationException::withMessages([
                'student_id' => 'Choose whose work this is.',
            ]);
        }

        if ((int) $work->student_id === $toStudentId) {
            throw ValidationException::withMessages([
                'student_id' => 'That is already whose work this is.',
            ]);
        }

        return DB::transaction(function () use ($work, $toStudentId, $staffUserId): StudentWork {
            StudentWorkReassignment::query()->create([
                'student_work_id' => (int) $work->id,
                'from_student_id' => (int) $work->student_id,
                'to_student_id' => $toStudentId,
                'moved_by' => $staffUserId,
            ]);

            $work->update(['student_id' => $toStudentId]);

            return $work->refresh();
        });
    }
}
