<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The website's course categories (`course_categories`, seeded in 2025 and
 * with no screen until BACKLOG C16 slice N2 — the owner, on the live site:
 * "there is no cat"). Name, address and order. Takes an id rather than the
 * model so the Website CMS controller stays off the Courses models.
 *
 * @return array{id: int, name: string, slug: string, order: int}
 */
class SaveCourseCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int, name: string, slug: string, order: int}
     */
    public function execute(array $data, ?int $id = null): array
    {
        $category = $id === null ? null : CourseCategory::query()->findOrFail($id);

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => trans('admin.courses_category_needs_name')]);
        }
        $slug = Str::slug(trim((string) ($data['slug'] ?? '')) ?: $name) ?: 'category-'.Str::lower(Str::random(5));
        if (CourseCategory::query()->where('slug', $slug)->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists()) {
            throw ValidationException::withMessages(['slug' => trans('admin.courses_category_slug_taken')]);
        }

        $payload = [
            'name' => $name,
            'slug' => $slug,
            'order' => (int) ($data['order'] ?? ($category?->order ?? 0)),
        ];

        if ($category === null) {
            $category = CourseCategory::query()->create($payload);
        } else {
            $category->fill($payload)->save();
            $category->refresh();
        }

        return ['id' => (int) $category->id, 'name' => $category->name, 'slug' => $category->slug, 'order' => (int) $category->order];
    }
}
