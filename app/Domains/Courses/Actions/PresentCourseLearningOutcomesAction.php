<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;

class PresentCourseLearningOutcomesAction
{
    /**
     * Localized "What you'll be able to do" lines. Empty settings omit the section.
     *
     * @return list<string>
     */
    public function execute(int $courseId, ?string $locale = null): array
    {
        $course = Course::query()->find($courseId);
        if ($course === null) {
            return [];
        }

        return $this->forLocale($course->learning_outcomes, $locale ?: app()->getLocale());
    }

    /**
     * @return list<string>
     */
    public function forLocale(mixed $raw, string $locale): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : $this->splitLines($raw);
        }

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        if (array_is_list($raw)) {
            return $this->clean($raw);
        }

        foreach ([$locale, 'en', 'dv', 'ar'] as $key) {
            $chunk = $raw[$key] ?? null;
            $lines = is_string($chunk) ? $this->splitLines($chunk) : (is_array($chunk) ? $this->clean($chunk) : []);
            if ($lines !== []) {
                return $lines;
            }
        }

        return [];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<string>
     */
    private function clean(array $items): array
    {
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

    /**
     * @return list<string>
     */
    private function splitLines(string $text): array
    {
        return $this->clean(preg_split('/\r\n|\r|\n/', $text) ?: []);
    }
}
