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
            'passing_score' => $assessment->passing_score !== null ? (int) $assessment->passing_score : null,
            'max_score' => (int) $assessment->max_score,
        ];
    }
}
