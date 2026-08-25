<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamMark;
use Illuminate\Validation\ValidationException;

class SaveExamMarkAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Exam $exam, int $studentId, array $data, int $actorId): ExamMark
    {
        if (! in_array($exam->status, [ExamStatus::MarksEntry, ExamStatus::Review], true)) {
            throw ValidationException::withMessages([
                'status' => 'Marks can only be entered while the exam is in marks_entry or review.',
            ]);
        }

        $onRoster = app(ListExamRosterAction::class)->execute($exam)
            ->contains(fn (array $row) => $row['student_id'] === $studentId);

        if (! $onRoster) {
            throw ValidationException::withMessages([
                'student_id' => 'Student is not on the exam roster for that date.',
            ]);
        }

        $absent = (bool) ($data['is_absent'] ?? false);
        $exempt = (bool) ($data['is_exempt'] ?? false);
        $raw = $data['marks'] ?? null;
        $marks = ($raw === '' || $raw === null) ? null : (float) $raw;

        if ($absent && $exempt) {
            throw ValidationException::withMessages([
                'is_absent' => 'Absent and exempt cannot both be set.',
            ]);
        }

        if (($absent || $exempt) && $marks !== null) {
            throw ValidationException::withMessages([
                'marks' => 'Absent or exempt rows cannot have marks.',
            ]);
        }

        if ($marks !== null && $marks < 0) {
            throw ValidationException::withMessages(['marks' => 'Marks cannot be negative.']);
        }

        if ($marks !== null && $marks > (float) $exam->max_marks) {
            throw ValidationException::withMessages([
                'marks' => 'Marks cannot exceed max marks ('.$exam->max_marks.').',
            ]);
        }

        $existing = ExamMark::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->first();

        $payload = [
            'exam_id' => $exam->id,
            'student_id' => $studentId,
            'marks' => $marks,
            'is_absent' => $absent,
            'is_exempt' => $exempt,
            'remarks' => $this->nullable($data['remarks'] ?? null),
            'updated_by' => $actorId,
        ];

        if ($existing === null) {
            $payload['entered_by'] = $actorId;

            return ExamMark::query()->create($payload);
        }

        $existing->fill($payload);
        $existing->save();

        return $existing->refresh();
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
