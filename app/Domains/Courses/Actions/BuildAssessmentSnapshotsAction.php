<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\AssessmentQuestion;
use App\Domains\Courses\Models\Question;

class BuildAssessmentSnapshotsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $assessmentId, bool $randomize = false): array
    {
        $assessment = Assessment::query()->findOrFail($assessmentId);
        $rows = AssessmentQuestion::query()
            ->where('assessment_id', $assessment->id)
            ->orderBy('position')
            ->get();

        $snapshots = $rows->map(function (AssessmentQuestion $row): array {
            $question = Question::query()->findOrFail($row->question_id);
            $snapshot = app(SnapshotQuestionAction::class)->execute($question);
            $snapshot['points'] = (int) ($row->points_override ?? 1);
            $snapshot['is_required'] = (bool) $row->is_required;
            $snapshot['position'] = (int) $row->position;

            return $snapshot;
        })->values()->all();

        if ($randomize || $assessment->randomize_questions) {
            shuffle($snapshots);
        }

        return $snapshots;
    }
}
