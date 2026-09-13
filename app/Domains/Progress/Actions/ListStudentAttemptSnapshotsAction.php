<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\AssessmentAttempt;

/**
 * Every question snapshot this student has actually been served.
 *
 * `ServeCatalogMediaAction` needs to know whether a media file belongs to a
 * question the student is sitting, and the only durable record of that is the
 * attempt's own frozen snapshots — a question can be detached from an
 * assessment, or its attachment swapped, after an attempt began.
 *
 * The snapshots are returned raw rather than filtered here. Their shape is
 * `SnapshotQuestionAction`'s to define, so Courses reads them with its own
 * `ResolveQuestionMediaAction`; Progress deciding what an attachment looks like
 * would be the boundary crossing rule 3 is about, just spelled without an
 * import.
 *
 * Scoped to one student, so this is the student's own history and never a
 * window into anyone else's paper.
 */
class ListStudentAttemptSnapshotsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $studentId): array
    {
        $snapshots = [];

        foreach (AssessmentAttempt::query()->where('student_id', $studentId)->pluck('snapshots') as $row) {
            foreach (is_array($row) ? $row : [] as $snapshot) {
                if (is_array($snapshot)) {
                    $snapshots[] = $snapshot;
                }
            }
        }

        return $snapshots;
    }
}
