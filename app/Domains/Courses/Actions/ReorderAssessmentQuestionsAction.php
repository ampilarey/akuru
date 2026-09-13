<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\AssessmentQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §21 gives `assessment_questions` a **Position** column, and
 * `BuildAssessmentSnapshotsAction` orders every attempt by it.
 *
 * Nothing could change it. `AttachAssessmentQuestionAction` assigns
 * `max(position) + 1` at attach time and there was no reorder route, no
 * control, and no other writer — so the order questions happened to be
 * attached in was the order every student sat them in, permanently. An author
 * who attached the final question before the warm-up had to detach everything
 * and re-attach it in sequence.
 *
 * Deliberately the same shape as `ReorderCourseModulesAction` and
 * `ReorderContentBlocksAction`, including the whole-list requirement: the
 * incoming list must name this assessment's questions exactly once each, so a
 * partial or foreign list cannot renumber some rows and leave the rest
 * colliding.
 *
 * Reordering does **not** disturb attempts already under way. §21's snapshot
 * rule is what makes that true — the order was frozen into the attempt when it
 * started, so a student mid-paper keeps the sequence they were given.
 */
class ReorderAssessmentQuestionsAction
{
    /**
     * @param  list<int>  $orderedQuestionIds
     */
    public function execute(int $assessmentId, array $orderedQuestionIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $orderedQuestionIds)));

        $existing = AssessmentQuestion::query()
            ->where('assessment_id', $assessmentId)
            ->orderBy('position')
            ->pluck('question_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $sorted = $ids;
        sort($sorted);
        $expected = $existing;
        sort($expected);

        if ($sorted !== $expected) {
            throw ValidationException::withMessages([
                'order' => 'The new order must list every question on this assessment exactly once.',
            ]);
        }

        DB::transaction(function () use ($assessmentId, $ids): void {
            foreach ($ids as $index => $questionId) {
                AssessmentQuestion::query()
                    ->where('assessment_id', $assessmentId)
                    ->where('question_id', $questionId)
                    // `position` starts at 1: `AttachAssessmentQuestionAction`
                    // floors it there with `max(1, $position)`, and a zero
                    // written here would be silently raised on the next attach.
                    ->update(['position' => $index + 1]);
            }
        });
    }
}
