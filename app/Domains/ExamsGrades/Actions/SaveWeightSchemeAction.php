<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\AssessmentWeightScheme;
use App\Domains\ExamsGrades\Models\ExamType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveWeightSchemeAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?AssessmentWeightScheme $scheme = null): AssessmentWeightScheme
    {
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($yearId < 1 || ! DB::table('academic_years')->where('id', $yearId)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Academic year is required.']);
        }

        $classId = $this->optionalId($data['class_id'] ?? null);
        if ($classId !== null && ! DB::table('classes')->where('id', $classId)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'Class not found.']);
        }

        $subjectId = $this->optionalId($data['subject_id'] ?? null);
        if ($subjectId !== null && ! DB::table('subjects')->where('id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Subject not found.']);
        }

        $weights = $this->weights($data['weights'] ?? []);

        $duplicate = AssessmentWeightScheme::query()
            ->where('academic_year_id', $yearId)
            ->where(fn ($query) => $classId === null
                ? $query->whereNull('class_id')
                : $query->where('class_id', $classId))
            ->where(fn ($query) => $subjectId === null
                ? $query->whereNull('subject_id')
                : $query->where('subject_id', $subjectId))
            ->when($scheme !== null, fn ($query) => $query->where('id', '!=', $scheme->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'A scheme already exists for this year / class / subject scope.',
            ]);
        }

        $payload = [
            'academic_year_id' => $yearId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'weights' => $weights,
        ];

        if ($scheme === null) {
            return AssessmentWeightScheme::query()->create($payload);
        }

        $scheme->fill($payload);
        $scheme->save();

        return $scheme->refresh();
    }

    /**
     * @return array<string, int>
     */
    private function weights(mixed $weights): array
    {
        if (is_string($weights)) {
            $decoded = json_decode($weights, true);
            $weights = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($weights) || $weights === []) {
            throw ValidationException::withMessages(['weights' => 'Weights are required and must sum to 100.']);
        }

        $normalized = [];
        $sum = 0;
        foreach ($weights as $typeId => $percent) {
            $id = (int) $typeId;
            $value = (int) $percent;
            if ($id < 1 || $value < 0) {
                throw ValidationException::withMessages(['weights' => 'Each weight must be a non-negative percent for an exam type.']);
            }
            if (! ExamType::query()->where('id', $id)->exists()) {
                throw ValidationException::withMessages(['weights' => "Unknown exam type {$id}."]);
            }
            $normalized[(string) $id] = $value;
            $sum += $value;
        }

        if ($sum !== 100) {
            throw ValidationException::withMessages(['weights' => "Weights must sum to 100 (got {$sum})."]);
        }

        return $normalized;
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
