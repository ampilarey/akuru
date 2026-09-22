<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CoursePlan;
use Illuminate\Support\Collection;

/**
 * Teaching plans as the plans screen shows them: every plan for a manager,
 * only their own for a teacher.
 *
 * The year name comes from `academic_year_id`; the legacy `academic_year`
 * string is nullable and no longer written (STATUS §5eu), so it is only a
 * fallback for rows that predate the FK.
 */
class ListCoursePlansAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(bool $canManage, ?int $teacherId): Collection
    {
        $years = AcademicYear::query()->pluck('name', 'id');

        return CoursePlan::query()
            ->with('topics')
            ->when(! $canManage && $teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->orderByDesc('id')
            ->get()
            ->map(fn (CoursePlan $plan): array => [
                'id' => $plan->id,
                'title' => $plan->title,
                'teacher_id' => $plan->teacher_id,
                'subject_id' => $plan->subject_id,
                'classroom_id' => $plan->classroom_id,
                'academic_year_id' => $plan->academic_year_id,
                'academic_year' => $years[$plan->academic_year_id] ?? $plan->academic_year,
                'status' => $plan->status?->value,
                'topics' => $plan->topics->map(fn ($topic): array => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'order' => $topic->order,
                    'is_completed' => $topic->is_completed,
                ]),
            ]);
    }
}
