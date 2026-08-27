<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEnrollmentsForOfferingAction;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Domains\People\Actions\ListStudentsByIdsAction;

class ListSessionAttendanceAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $sessionId): array
    {
        $session = CourseOfferingSession::query()->with('offering')->findOrFail($sessionId);
        $marked = AttendanceRecord::query()
            ->where('course_offering_session_id', $sessionId)
            ->get()
            ->keyBy('enrollment_id');

        $enrollments = collect(app(ListEnrollmentsForOfferingAction::class)->execute($session->course_offering_id));
        $students = app(ListStudentsByIdsAction::class)
            ->execute($enrollments->pluck('student_id')->all())
            ->keyBy('id');

        $roster = $enrollments
            ->map(function (array $enrollment) use ($marked, $students) {
                $row = $marked->get($enrollment['id']);
                $student = $students->get((int) $enrollment['student_id']);

                return [
                    'enrollment_id' => $enrollment['id'],
                    'student_id' => $enrollment['student_id'],
                    'student_name' => $student['name'] ?? ('Student '.$enrollment['student_id']),
                    'status' => $row?->status?->value ?? 'pending',
                    'attendance_mode' => $row?->attendance_mode?->value,
                    'notes' => $row?->notes,
                ];
            })
            ->values()
            ->all();

        return [
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'course_offering_id' => $session->course_offering_id,
                'academic_year_id' => $session->academic_year_id ? (int) $session->academic_year_id : null,
                'offering_title' => $session->offering?->title,
                'starts_at' => $session->starts_at?->toIso8601String(),
            ],
            'statuses' => array_map(fn ($status) => $status->value, \App\Domains\Offerings\Enums\AttendanceStatus::cases()),
            'modes' => array_map(fn ($mode) => $mode->value, \App\Domains\Offerings\Enums\AttendanceMode::cases()),
            'roster' => $roster,
        ];
    }
}
