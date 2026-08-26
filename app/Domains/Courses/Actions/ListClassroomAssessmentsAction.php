<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Assessment;
use Illuminate\Support\Collection;

class ListClassroomAssessmentsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $classroomId): Collection
    {
        return Assessment::query()
            ->where('classroom_id', $classroomId)
            ->orderBy('id')
            ->get()
            ->map(fn (Assessment $assessment): array => app(ListCourseAssessmentsAction::class)->serialize($assessment));
    }
}
