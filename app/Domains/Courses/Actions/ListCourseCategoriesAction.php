<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseCategory;

/**
 * The course categories in their order, each with how many courses use it
 * (deleted ones included, since a deleted course keeps its category and
 * can be restored).
 */
class ListCourseCategoriesAction
{
    /**
     * @return list<array{id: int, name: string, slug: string, order: int, courses: int}>
     */
    public function execute(): array
    {
        return CourseCategory::query()->ordered()
            ->withCount(['courses' => fn ($q) => $q->withTrashed()])
            ->get()
            ->map(fn (CourseCategory $category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'order' => (int) $category->order,
                'courses' => (int) $category->courses_count,
            ])
            ->values()
            ->all();
    }
}
