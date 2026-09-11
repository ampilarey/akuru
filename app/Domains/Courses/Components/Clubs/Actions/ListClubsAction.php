<?php

namespace App\Domains\Courses\Components\Clubs\Actions;

use App\Domains\Courses\Actions\ListEngineCoursesAction;
use App\Domains\Courses\Actions\ListEnrollmentTargetsByCourseTypeAction;
use Illuminate\Support\Collection;

/**
 * E17 — school clubs, which are courses.
 *
 * There is **no clubs table and no club membership table**, deliberately. A
 * club is a `Course` with `course_type = 'club'` and its members are ordinary
 * `CourseEnrollment` rows (rule 11: one enrolment system). The plan asked for
 * exactly this — "the course engine can model these already … resist a
 * parallel enrolment system" — so this component is a reader and a screen, not
 * a schema.
 *
 * Reaches the engine only through Courses\Actions seams, never the models
 * (rule 3, and ComponentsIsolationTest enforces it). `'club'` is a value this
 * component supplies; the engine never branches on it (rule 6).
 */
class ListClubsAction
{
    public const COURSE_TYPE = 'club';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        $memberCounts = collect(app(ListEnrollmentTargetsByCourseTypeAction::class)->execute(self::COURSE_TYPE))
            ->groupBy('course_id')
            ->map(fn (Collection $rows): int => $rows->count());

        return app(ListEngineCoursesAction::class)->execute(self::COURSE_TYPE)
            ->map(fn (array $course): array => [
                'id' => (int) $course['id'],
                'title' => $course['title'],
                'title_dv' => $course['title_dv'] ?? null,
                'title_ar' => $course['title_ar'] ?? null,
                'slug' => $course['slug'] ?? null,
                'members' => (int) ($memberCounts->get((int) $course['id']) ?? 0),
            ])
            ->values();
    }
}
