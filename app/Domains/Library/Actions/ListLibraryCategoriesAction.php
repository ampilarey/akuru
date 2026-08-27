<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryCategory;

class ListLibraryCategoriesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(bool $activeOnly = true, bool $withCounts = false): array
    {
        return LibraryCategory::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($withCounts, fn ($query) => $query->withCount([
                'items' => fn ($sub) => $sub->where('status', 'published'),
            ]))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (LibraryCategory $category): array => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'name_dv' => $category->name_dv,
                'name_ar' => $category->name_ar,
                'slug' => $category->slug,
                'sort_order' => (int) $category->sort_order,
                'is_active' => (bool) $category->is_active,
                'published_count' => $withCounts ? (int) $category->items_count : null,
            ])
            ->values()
            ->all();
    }
}
