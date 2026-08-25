<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\CoursePlanStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CoursePlan;
use Illuminate\Validation\ValidationException;

class SaveCoursePlanAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CoursePlan $plan = null): CoursePlan
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $yearId = isset($data['academic_year_id']) && $data['academic_year_id'] !== ''
            ? (int) $data['academic_year_id']
            : null;
        $yearName = trim((string) ($data['academic_year'] ?? ''));
        if ($yearId) {
            $yearName = (string) (AcademicYear::query()->where('id', $yearId)->value('name') ?? $yearName);
        }
        if ($yearName === '') {
            $yearName = '2024-2025';
        }

        $payload = [
            'teacher_id' => (int) $data['teacher_id'],
            'subject_id' => (int) $data['subject_id'],
            'classroom_id' => (int) $data['classroom_id'],
            'academic_year' => $yearName,
            'academic_year_id' => $yearId,
            'term_id' => isset($data['term_id']) && $data['term_id'] !== '' && $data['term_id'] !== null
                ? (int) $data['term_id']
                : null,
            'title' => $title,
            'description' => $this->nullableString($data['description'] ?? null),
            'status' => CoursePlanStatus::from((string) ($data['status'] ?? CoursePlanStatus::Active->value)),
        ];

        if ($plan === null) {
            return CoursePlan::query()->create($payload);
        }

        $plan->fill($payload);
        $plan->save();

        return $plan->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
