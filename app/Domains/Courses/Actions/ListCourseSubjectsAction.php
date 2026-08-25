<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Support\Collection;

class ListCourseSubjectsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return CourseSubject::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(fn (CourseSubject $row) => [
                'id' => $row->id,
                'parent_id' => $row->parent_id,
                'name_en' => $row->name_en,
                'name_dv' => $row->name_dv,
                'name_ar' => $row->name_ar,
                'slug' => $row->slug,
                'sort_order' => $row->sort_order,
                'active' => $row->active,
            ])->values();
    }
}
