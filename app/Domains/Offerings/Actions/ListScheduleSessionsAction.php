<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ResolveEngineCourseAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;

class ListScheduleSessionsAction
{
    /**
     * @param  list<int>  $offeringIds
     * @return list<array<string, mixed>>
     */
    public function execute(array $offeringIds = [], ?int $teacherUserId = null, bool $upcomingOnly = false): array
    {
        $query = CourseOfferingSession::query()->orderBy('starts_at');

        $ids = array_values(array_filter(array_map('intval', $offeringIds)));
        if ($ids !== []) {
            $query->whereIn('course_offering_id', $ids);
        }
        if ($teacherUserId !== null) {
            $query->where('teacher_user_id', $teacherUserId);
        }
        if ($ids === [] && $teacherUserId === null) {
            return [];
        }
        if ($upcomingOnly) {
            $query->where('starts_at', '>=', now()->subHour());
        }

        return $query->get()->map(function (CourseOfferingSession $session): array {
            $offering = CourseOffering::query()->find($session->course_offering_id);
            $course = $offering ? app(ResolveEngineCourseAction::class)->execute($offering->course_id) : null;

            return [
                'id' => $session->id,
                'course_offering_id' => $session->course_offering_id,
                'offering_title' => $offering?->title,
                'course_title' => $course['title'] ?? null,
                'title' => $session->title,
                'session_type' => $session->session_type?->value ?? $session->session_type,
                'starts_at' => $session->starts_at?->toIso8601String(),
                'ends_at' => $session->ends_at?->toIso8601String(),
                'timezone' => $session->timezone,
                'location_name' => $session->location_name,
                'online_meeting_url' => $session->online_meeting_url,
                'teacher_user_id' => $session->teacher_user_id,
                'is_required' => $session->is_required,
            ];
        })->all();
    }
}
