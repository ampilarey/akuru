<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEnrollmentsForOfferingAction;
use App\Domains\Courses\Actions\SyncEnrollmentProgressByIdAction;
use App\Domains\Offerings\Enums\AttendanceMode;
use App\Domains\Offerings\Enums\AttendanceStatus;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOfferingSession;
use Illuminate\Validation\ValidationException;

class RecordOfferingAttendanceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AttendanceRecord
    {
        $session = CourseOfferingSession::query()->findOrFail((int) $data['course_offering_session_id']);
        $status = AttendanceStatus::tryFrom((string) ($data['status'] ?? ''));
        if ($status === null) {
            throw ValidationException::withMessages(['status' => 'Invalid attendance status.']);
        }

        $mode = isset($data['attendance_mode']) && $data['attendance_mode'] !== ''
            ? AttendanceMode::tryFrom((string) $data['attendance_mode'])
            : null;
        if (isset($data['attendance_mode']) && $data['attendance_mode'] !== '' && $mode === null) {
            throw ValidationException::withMessages(['attendance_mode' => 'Invalid attendance mode.']);
        }

        $enrollmentId = (int) $data['enrollment_id'];
        $roster = collect(app(ListEnrollmentsForOfferingAction::class)->execute($session->course_offering_id))
            ->firstWhere('id', $enrollmentId);
        if ($roster === null) {
            throw ValidationException::withMessages(['enrollment_id' => 'Enrollment is not on this offering.']);
        }

        $row = AttendanceRecord::query()->firstOrNew([
            'course_offering_session_id' => $session->id,
            'enrollment_id' => $enrollmentId,
        ]);
        $row->fill([
            'course_offering_id' => $session->course_offering_id,
            'student_id' => (int) $roster['student_id'],
            'academic_year_id' => $session->academic_year_id,
            'status' => $status,
            'attendance_mode' => $mode,
            'marked_by' => $data['marked_by'] ?? null,
            'marked_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);
        $row->save();
        app(SyncEnrollmentProgressByIdAction::class)->execute($enrollmentId);

        return $row->refresh();
    }
}
