<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;

class SaveCourseLearningOutcomesAction
{
    /**
     * @param  array{en?: string|array<int, string>, dv?: string|array<int, string>, ar?: string|array<int, string>}  $byLocale
     */
    public function execute(int $courseId, array $byLocale): void
    {
        $course = Course::query()->findOrFail($courseId);
        $payload = [];
        foreach (['en', 'dv', 'ar'] as $locale) {
            $payload[$locale] = $this->normalize($byLocale[$locale] ?? []);
        }

        $course->learning_outcomes = ($payload['en'] === [] && $payload['dv'] === [] && $payload['ar'] === [])
            ? null
            : $payload;
        $course->save();
    }

    /**
     * @param  string|array<int, mixed>  $value
     * @return list<string>
     */
    private function normalize(string|array $value): array
    {
        $items = is_string($value) ? (preg_split('/\r\n|\r|\n/', $value) ?: []) : $value;
        $out = [];
        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $line = trim((string) $item);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return array_values($out);
    }
}
