<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveLibraryCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?LibraryCategory $category = null): LibraryCategory
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Category name is required.']);
        }

        $slug = (string) ($data['slug'] ?? Str::slug($name));
        $exists = LibraryCategory::query()
            ->where('slug', $slug)
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['slug' => 'Category slug already in use.']);
        }

        $payload = [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $name,
            'name_dv' => $data['name_dv'] ?? null,
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($category === null) {
            return LibraryCategory::query()->create($payload);
        }

        $category->fill($payload);
        $category->save();

        return $category->refresh();
    }
}
