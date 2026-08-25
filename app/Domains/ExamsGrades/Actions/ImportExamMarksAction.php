<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Exam;
use Illuminate\Validation\ValidationException;

class ImportExamMarksAction
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{saved: int}
     */
    public function execute(Exam $exam, array $rows, int $actorId): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages(['rows' => 'CSV has no mark rows.']);
        }

        $saved = 0;
        foreach ($rows as $index => $row) {
            $studentId = (int) ($row['student_id'] ?? 0);
            if ($studentId < 1) {
                throw ValidationException::withMessages([
                    'rows' => 'Row '.($index + 1).' is missing student_id.',
                ]);
            }

            app(SaveExamMarkAction::class)->execute($exam, $studentId, [
                'marks' => $row['marks'] ?? null,
                'is_absent' => $this->boolish($row['is_absent'] ?? false),
                'is_exempt' => $this->boolish($row['is_exempt'] ?? false),
                'remarks' => $row['remarks'] ?? null,
            ], $actorId);
            $saved++;
        }

        return ['saved' => $saved];
    }

    private function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }
}
