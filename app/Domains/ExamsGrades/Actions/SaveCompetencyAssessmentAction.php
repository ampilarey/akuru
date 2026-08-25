<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Competency;
use App\Domains\ExamsGrades\Models\CompetencyAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCompetencyAssessmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $actorId): CompetencyAssessment
    {
        $competency = Competency::query()->find((int) ($data['competency_id'] ?? 0));
        if ($competency === null) {
            throw ValidationException::withMessages(['competency_id' => 'Competency is required.']);
        }

        $studentId = (int) ($data['student_id'] ?? 0);
        if ($studentId < 1 || ! DB::table('students')->where('id', $studentId)->exists()) {
            throw ValidationException::withMessages(['student_id' => 'Student is required.']);
        }

        $termId = (int) ($data['term_id'] ?? 0);
        if ($termId < 1 || ! DB::table('terms')->where('id', $termId)->exists()) {
            throw ValidationException::withMessages(['term_id' => 'Term is required.']);
        }

        $level = trim((string) ($data['level'] ?? ''));
        if ($level === '') {
            throw ValidationException::withMessages(['level' => 'Level is required.']);
        }

        return CompetencyAssessment::query()->updateOrCreate(
            [
                'student_id' => $studentId,
                'competency_id' => $competency->id,
                'term_id' => $termId,
            ],
            [
                'level' => $level,
                'assessed_by' => $actorId,
                'notes' => $this->nullable($data['notes'] ?? null),
            ],
        );
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
