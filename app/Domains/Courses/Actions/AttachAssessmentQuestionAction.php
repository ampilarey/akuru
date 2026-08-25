<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\AssessmentQuestion;
use App\Domains\Courses\Models\Question;
use Illuminate\Validation\ValidationException;

class AttachAssessmentQuestionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AssessmentQuestion
    {
        $assessment = Assessment::query()->findOrFail((int) $data['assessment_id']);
        $question = Question::query()->find($data['question_id'] ?? 0);
        if ($question === null) {
            throw ValidationException::withMessages(['question_id' => 'Question not found.']);
        }

        $existing = AssessmentQuestion::query()
            ->where('assessment_id', $assessment->id)
            ->where('question_id', $question->id)
            ->first();

        $position = (int) ($data['position'] ?? ((int) AssessmentQuestion::query()
            ->where('assessment_id', $assessment->id)
            ->max('position')) + 1);

        $payload = [
            'assessment_id' => $assessment->id,
            'question_id' => $question->id,
            'position' => max(1, $position),
            'points_override' => $data['points_override'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? true),
        ];

        if ($existing === null) {
            $row = AssessmentQuestion::query()->create($payload);
        } else {
            $existing->fill($payload);
            $existing->save();
            $row = $existing;
        }

        $this->refreshMaxScore($assessment);

        return $row->fresh();
    }

    public function detach(int $assessmentId, int $questionId): void
    {
        $assessment = Assessment::query()->findOrFail($assessmentId);
        AssessmentQuestion::query()
            ->where('assessment_id', $assessmentId)
            ->where('question_id', $questionId)
            ->delete();
        $this->refreshMaxScore($assessment);
    }

    private function refreshMaxScore(Assessment $assessment): void
    {
        $total = (int) AssessmentQuestion::query()
            ->where('assessment_id', $assessment->id)
            ->get()
            ->sum(fn (AssessmentQuestion $row) => (int) ($row->points_override ?? 1));

        $assessment->update(['max_score' => $total]);
    }
}
