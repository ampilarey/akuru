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
            throw ValidationException::withMessages(['name' => __('admin.library_office_error_category_name')]);
        }

        // §5po: a rename keeps the category's address — the shelf's filter and
        // any link to it go by the slug — and whatever the form did not send.
        $slug = (string) ($data['slug'] ?? ($category !== null ? $category->slug : Str::slug($name)));
        $exists = LibraryCategory::query()
            ->where('slug', $slug)
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['slug' => __('admin.library_office_error_category_slug')]);
        }

        $payload = [
            'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $category?->parent_id,
            'name' => $name,
            'name_dv' => $this->translated($data['name_dv'] ?? null),
            'name_ar' => $this->translated($data['name_ar'] ?? null),
            'slug' => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? $category?->sort_order ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? $category?->is_active ?? true),
        ];

        if ($category === null) {
            return LibraryCategory::query()->create($payload);
        }

        $category->fill($payload);
        $category->save();

        return $category->refresh();
    }

    /** An emptied Dhivehi or Arabic name falls back to the English one (`nameIn`), not to a blank. */
    private function translated(mixed $name): ?string
    {
        $name = trim((string) $name);

        return $name === '' ? null : $name;
    }
}
