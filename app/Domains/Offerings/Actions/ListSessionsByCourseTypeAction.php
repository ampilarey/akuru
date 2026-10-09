<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEngineCoursesAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;

/**
 * Engine-owned seam (STATUS §5pu): the sessions of the offerings whose course
 * has a course_type VALUE the caller supplies — 'hifz' from the Quran
 * component — so the engine stays subject-ignorant (rule 6), as
 * ListEnrollmentTargetsByCourseTypeAction already does for enrollments.
 *
 * From two weeks back on, soonest first: a teacher fills a halaqa sheet on the
 * day or in the days after, and the next ones are what they plan around.
 */
class ListSessionsByCourseTypeAction
{
    public const LOOK_BACK_DAYS = 14;

    /**
     * @return list<array{id: int, title: string|null, starts_at: string|null, when: string|null, location_name: string|null, online: bool, offering_title: string|null, course_title: string|null, teacher_user_id: int|null}>
     */
    public function execute(string $courseType, ?int $teacherUserId = null): array
    {
        $courses = app(ListEngineCoursesAction::class)->execute($courseType)->keyBy('id');
        if ($courses->isEmpty()) {
            return [];
        }
        $offerings = CourseOffering::query()->whereIn('course_id', $courses->keys()->all())->get(['id', 'course_id', 'title'])->keyBy('id');
        if ($offerings->isEmpty()) {
            return [];
        }

        return CourseOfferingSession::query()
            ->whereIn('course_offering_id', $offerings->keys()->all())
            ->when($teacherUserId !== null, fn ($query) => $query->where('teacher_user_id', $teacherUserId))
            ->where('starts_at', '>=', now()->subDays(self::LOOK_BACK_DAYS)->startOfDay())
            ->orderBy('starts_at')
            ->limit(200)
            ->get()
            ->map(function (CourseOfferingSession $session) use ($offerings, $courses): array {
                $offering = $offerings->get($session->course_offering_id);

                return [
                    'id' => (int) $session->id,
                    'title' => $session->title,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                    // Digits only, so it reads the same in every language.
                    'when' => $session->starts_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'location_name' => $session->location_name,
                    'online' => filled($session->online_meeting_url),
                    'offering_title' => $offering?->title,
                    'course_title' => $offering ? ($courses->get($offering->course_id)['title'] ?? null) : null,
                    'teacher_user_id' => $session->teacher_user_id !== null ? (int) $session->teacher_user_id : null,
                ];
            })
            ->values()
            ->all();
    }
}
