<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Assessment;

class ResolveAssessmentSettingsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $assessmentId): array
    {
        $assessment = Assessment::query()->findOrFail($assessmentId);

        return [
            'id' => $assessment->id,
            'course_id' => $assessment->course_id,
            'classroom_id' => $assessment->classroom_id,
            'title' => $assessment->title,
            'status' => $assessment->status->value,
            'retake_limit' => $assessment->retake_limit,
            'randomize_questions' => (bool) $assessment->randomize_questions,
            'show_results' => (bool) $assessment->show_results,
            'show_correct_answers' => (bool) $assessment->show_correct_answers,
            // SPEC §19 "Teacher marking". Same shape as the §31 bug above: the
            // column was captured by the controller, saved, and listed back —
            // and never arrived here, so the scoring path could not honour it
            // even in principle. `MigrateLegacyAssessmentsAction` sets it true
            // for every migrated legacy assignment, so there is real data
            // carrying a flag that did nothing.
            'requires_teacher_marking' => (bool) $assessment->requires_teacher_marking,
            'passing_score' => $assessment->passing_score !== null ? (int) $assessment->passing_score : null,
            // SPEC §31. This was absent, which is why nothing downstream could
            // enforce the limit even in principle: the submit path resolves
            // settings through here and the field simply never arrived.
            'time_limit_minutes' => $assessment->time_limit_minutes !== null
                ? (int) $assessment->time_limit_minutes
                : null,
            'max_score' => (int) $assessment->max_score,
        ];
    }
}
