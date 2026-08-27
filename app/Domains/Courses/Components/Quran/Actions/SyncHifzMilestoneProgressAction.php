<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Actions\ApplyEnrollmentCompletionAction;
use App\Domains\Offerings\Actions\ResolveHalaqaEnrollmentLinksAction;
use App\Domains\Progress\Contracts\CourseCompletionEvaluator;
use App\Support\Contracts\HalaqaReferenceReader;

/**
 * F2: memorisation milestones mapped onto engine completion rules — the named
 * second consumer of Progress\Contracts\CourseCompletionEvaluator (ADR-022).
 * A student's required units are their milestone rows; approved ones count as
 * complete. The engine never learns what a milestone is (rule 6): this
 * component feeds ids into the evaluator and persists through the
 * ApplyEnrollmentCompletionAction seam (rule 3).
 *
 * A linked student with no milestone rows is skipped, never zeroed — absence
 * of milestones is absence of evidence, not zero progress.
 */
class SyncHifzMilestoneProgressAction
{
    /**
     * @return array{evaluated: int, completed: int}
     */
    public function execute(int $hifzProgramId): array
    {
        $milestonesByStudent = [];
        foreach (app(HalaqaReferenceReader::class)->listMilestones($hifzProgramId) as $milestone) {
            $milestonesByStudent[(int) $milestone['student_id']][] = $milestone;
        }

        $evaluated = 0;
        $completed = 0;
        foreach (app(ResolveHalaqaEnrollmentLinksAction::class)->execute($hifzProgramId) as $link) {
            $milestones = $milestonesByStudent[$link['student_id']] ?? [];
            if ($milestones === []) {
                continue;
            }

            $required = array_map(fn (array $row): int => (int) $row['id'], $milestones);
            $approved = array_map(
                fn (array $row): int => (int) $row['id'],
                array_filter($milestones, fn (array $row): bool => ($row['status'] ?? null) === 'approved'),
            );

            $result = app(CourseCompletionEvaluator::class)->execute($required, $approved);
            app(ApplyEnrollmentCompletionAction::class)->execute(
                $link['course_enrollment_id'],
                (int) $result['percentage'],
                (bool) $result['is_complete'],
            );

            $evaluated++;
            if ($result['is_complete']) {
                $completed++;
            }
        }

        return ['evaluated' => $evaluated, 'completed' => $completed];
    }
}
