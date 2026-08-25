<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Enums\AttendanceStatus;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOfferingSession;

class ListRequiredSessionProgressAction
{
    /**
     * @return array{required_session_ids: list<int>, attended_session_ids: list<int>}
     */
    public function execute(int $offeringId, int $enrollmentId): array
    {
        $required = CourseOfferingSession::query()
            ->where('course_offering_id', $offeringId)
            ->where('is_required', true)
            ->orderBy('starts_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $attended = AttendanceRecord::query()
            ->where('enrollment_id', $enrollmentId)
            ->whereIn('course_offering_session_id', $required)
            ->whereIn('status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
                AttendanceStatus::Excused->value,
            ])
            ->pluck('course_offering_session_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'required_session_ids' => $required,
            'attended_session_ids' => $attended,
        ];
    }
}
