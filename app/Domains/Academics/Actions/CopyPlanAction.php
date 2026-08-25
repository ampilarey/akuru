<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\CoursePlanStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CoursePlan;
use Illuminate\Validation\ValidationException;

class CopyPlanAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(CoursePlan $source, array $data): CoursePlan
    {
        $classroomId = (int) ($data['classroom_id'] ?? 0);
        if ($classroomId < 1) {
            throw ValidationException::withMessages([
                'classroom_id' => 'A target class is required.',
            ]);
        }

        $yearId = isset($data['academic_year_id']) && $data['academic_year_id'] !== ''
            ? (int) $data['academic_year_id']
            : $source->academic_year_id;

        $yearName = $source->academic_year;
        if ($yearId) {
            $yearName = AcademicYear::query()->where('id', $yearId)->value('name') ?? $yearName;
        }

        $copy = $source->replicate(['id']);
        $copy->classroom_id = $classroomId;
        $copy->academic_year_id = $yearId;
        $copy->academic_year = $yearName ?: '2024-2025';
        $copy->term_id = array_key_exists('term_id', $data) && $data['term_id'] !== '' && $data['term_id'] !== null
            ? (int) $data['term_id']
            : $source->term_id;
        $copy->teacher_id = isset($data['teacher_id']) && $data['teacher_id'] !== ''
            ? (int) $data['teacher_id']
            : $source->teacher_id;
        $copy->status = CoursePlanStatus::Draft;
        $copy->save();

        foreach ($source->topics()->get() as $topic) {
            $replica = $topic->replicate(['id']);
            $replica->course_plan_id = $copy->id;
            $replica->is_completed = false;
            $replica->save();
        }

        return $copy->load('topics');
    }
}
