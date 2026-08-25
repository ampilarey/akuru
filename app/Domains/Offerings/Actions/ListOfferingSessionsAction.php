<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEnrollmentsForOfferingAction;
use App\Domains\Courses\Actions\ResolveEngineCourseAction;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;

class ListOfferingSessionsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $offeringId): array
    {
        $offering = CourseOffering::query()->findOrFail($offeringId);
        $course = app(ResolveEngineCourseAction::class)->execute($offering->course_id);

        return [
            'offering' => [
                'id' => $offering->id,
                'title' => $offering->title,
                'course_title' => $course['title'],
                'delivery_mode' => $offering->delivery_mode?->value ?? $offering->delivery_mode,
            ],
            'types' => array_map(fn ($type) => $type->value, \App\Domains\Offerings\Enums\SessionType::cases()),
            'sessions' => CourseOfferingSession::query()
                ->where('course_offering_id', $offeringId)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (CourseOfferingSession $session) => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'session_type' => $session->session_type?->value ?? $session->session_type,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                    'ends_at' => $session->ends_at?->toIso8601String(),
                    'timezone' => $session->timezone,
                    'location_name' => $session->location_name,
                    'online_meeting_url' => $session->online_meeting_url,
                    'teacher_user_id' => $session->teacher_user_id,
                    'is_required' => $session->is_required,
                ])
                ->values()
                ->all(),
            'enrollment_count' => count(app(ListEnrollmentsForOfferingAction::class)->execute($offeringId)),
            'marked_count' => AttendanceRecord::query()
                ->where('course_offering_id', $offeringId)
                ->where('status', '!=', 'pending')
                ->count(),
        ];
    }
}
