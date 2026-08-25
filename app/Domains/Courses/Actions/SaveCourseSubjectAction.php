<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveCourseSubjectAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CourseSubject $subject = null): CourseSubject
    {
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        if ($subject !== null && $parentId === $subject->id) {
            throw ValidationException::withMessages(['parent_id' => 'A subject cannot be its own parent.']);
        }
        if ($subject !== null && $parentId !== null && $this->isDescendant($subject->id, $parentId)) {
            throw ValidationException::withMessages(['parent_id' => 'A subject cannot be nested under its own descendant.']);
        }

        $payload = [
            'parent_id' => $parentId,
            'name_en' => $data['name_en'],
            'name_dv' => $data['name_dv'] ?? null,
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => $data['slug'] ?? Str::slug((string) $data['name_en']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($subject === null) {
            return CourseSubject::query()->create($payload);
        }

        $subject->fill($payload);
        $subject->save();

        return $subject->refresh();
    }

    private function isDescendant(int $ancestorId, int $nodeId): bool
    {
        $current = CourseSubject::query()->find($nodeId);
        while ($current?->parent_id) {
            if ((int) $current->parent_id === $ancestorId) {
                return true;
            }
            $current = CourseSubject::query()->find($current->parent_id);
        }

        return false;
    }
}
