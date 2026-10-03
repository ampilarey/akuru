<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseCategory;
use Illuminate\Validation\ValidationException;

/**
 * A category goes only when no course uses it: `courses.course_category_id`
 * is required, so removing a category in use would orphan every course in
 * it on the public site. The message says which to move first.
 */
class DeleteCourseCategoryAction
{
    public function execute(int $id): void
    {
        $category = CourseCategory::query()->findOrFail($id);
        $inUse = $category->courses()->withTrashed()->count();
        if ($inUse > 0) {
            throw ValidationException::withMessages([
                'category' => trans_choice('admin.courses_category_in_use', $inUse, ['count' => $inUse, 'name' => $category->name]),
            ]);
        }

        $category->delete();
    }
}
