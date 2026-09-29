<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\PostCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RESEARCH_ARTICLES_PLAN R4: the news categories (`post_categories`, which
 * has been there since 2025 with no screen). Name, address, order, and
 * whether it is offered.
 */
class SaveNewsCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?PostCategory $category = null): PostCategory
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'A category needs a name.']);
        }
        $slug = Str::slug(trim((string) ($data['slug'] ?? '')) ?: $name) ?: 'category-'.Str::lower(Str::random(5));
        if (PostCategory::query()->where('slug', $slug)->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Another category already uses that address.']);
        }

        $payload = [
            'name' => $name,
            'slug' => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOL),
        ];

        if ($category === null) {
            return PostCategory::query()->create($payload);
        }
        $category->fill($payload)->save();

        return $category->refresh();
    }
}
