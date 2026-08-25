<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseLevel;
use Illuminate\Support\Str;

class SaveCourseLevelAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CourseLevel $level = null): CourseLevel
    {
        $payload = [
            'name_en' => $data['name_en'],
            'name_dv' => $data['name_dv'] ?? null,
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => $data['slug'] ?? Str::slug((string) $data['name_en']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($level === null) {
            return CourseLevel::query()->create($payload);
        }

        $level->fill($payload);
        $level->save();

        return $level->refresh();
    }
}
