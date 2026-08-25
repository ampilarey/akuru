<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEnrollmentsForOfferingAction;
use App\Domains\Offerings\Models\CourseOfferingSession;

class BulkMarkOfferingAttendanceAction
{
    /**
     * @return list<int>
     */
    public function execute(int $sessionId, string $status, ?string $mode, int $markedBy): array
    {
        $session = CourseOfferingSession::query()->findOrFail($sessionId);
        $ids = [];
        foreach (app(ListEnrollmentsForOfferingAction::class)->execute($session->course_offering_id) as $enrollment) {
            $row = app(RecordOfferingAttendanceAction::class)->execute([
                'course_offering_session_id' => $sessionId,
                'enrollment_id' => $enrollment['id'],
                'status' => $status,
                'attendance_mode' => $mode,
                'marked_by' => $markedBy,
            ]);
            $ids[] = $row->id;
        }

        return $ids;
    }
}
