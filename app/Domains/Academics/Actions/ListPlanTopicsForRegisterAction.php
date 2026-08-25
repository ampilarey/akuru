<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\CoursePlan;
use App\Domains\Academics\Models\LessonLog;
use Illuminate\Support\Collection;

class ListPlanTopicsForRegisterAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(LessonLog $log): Collection
    {
        $plan = CoursePlan::query()
            ->where('teacher_id', $log->teacher_id)
            ->where('subject_id', $log->subject_id)
            ->where('classroom_id', $log->classroom_id)
            ->where('status', '!=', 'archived')
            ->when(
                $log->academic_year_id,
                fn ($query) => $query->where('academic_year_id', $log->academic_year_id),
            )
            ->latest('id')
            ->first();

        if ($plan === null) {
            return collect();
        }

        return $plan->topics()->get()->map(fn ($topic) => [
            'id' => $topic->id,
            'title' => $topic->title,
            'is_completed' => $topic->is_completed,
            'order' => $topic->order,
        ])->values();
    }
}
