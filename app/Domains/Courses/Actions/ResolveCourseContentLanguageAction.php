<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\Lesson;

/**
 * SPEC §7 "Supported Languages and Direction":
 *
 *   > Each course may have its own course language.
 *   >
 *   > **The platform UI language and course content language are separate
 *   > concepts.**
 *   >
 *   > Example: A user may use the UI in Dhivehi. The course may be Arabic.
 *
 * §15.3 gives every text-capable block a `language` setting, and the player
 * reads it — but the default is `auto`, and `auto` resolved to **nothing**:
 *
 *     const language = s.language && s.language !== 'auto' ? s.language : undefined;
 *
 * No `lang` attribute means the block inherits the page's, and the page's is
 * the **UI** language. So §7's example — a Dhivehi UI reading an Arabic course
 * — produced Arabic text marked up as Dhivehi, on every block an author had
 * not tagged by hand. That is the two concepts collapsed into one, which is
 * the exact thing §7 says they are not.
 *
 * `courses.language` has been storable and settable since the course table
 * shipped (the catalog screen offers EN/DV/AR/Mixed) and **nothing read it**
 * except a scope nobody calls. The same taxonomy as §36's `submission_kind`:
 * a column the schema offers, the UI writes, and no reader consults.
 *
 * **`mixed` resolves to null on purpose.** A course that is deliberately more
 * than one language has no single honest answer, and guessing one is worse
 * than leaving the browser to its per-block heuristic — which is what `auto`
 * already does correctly for *direction*.
 *
 * This is read live rather than frozen into the lesson snapshot. Course
 * language is course metadata, not lesson content: it says what language the
 * lesson is *in*, not what it *says*. Freezing it would also need a backfill
 * for every revision published before this slice, and §28.6's concern is that
 * published content does not change under a student — not that a course
 * cannot be re-labelled.
 */
class ResolveCourseContentLanguageAction
{
    /**
     * Languages a `lang` attribute may carry. `mixed` is a real course setting
     * and deliberately absent: it is the one value that must not become a
     * `lang`.
     */
    public const TAGGABLE = ['en', 'dv', 'ar'];

    public function forLesson(int $lessonId): ?string
    {
        $courseId = Lesson::query()->whereKey($lessonId)->value('course_id');

        return $courseId === null ? null : $this->forCourse((int) $courseId);
    }

    public function forCourse(int $courseId): ?string
    {
        return $this->tag(Course::query()->whereKey($courseId)->value('language'));
    }

    public function tag(mixed $language): ?string
    {
        $value = strtolower(trim((string) $language));

        return in_array($value, self::TAGGABLE, true) ? $value : null;
    }
}
