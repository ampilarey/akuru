<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\OfferingHalaqaEnrollmentLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use App\Support\Contracts\HalaqaReferenceReader;

/**
 * F2 backfill (rule 9 additive step, S1.1b template): map every Hifz program
 * onto engine structure — Course + Offering + link (MapHalaqaProgramAction),
 * mirror every session regardless of the dual_write flag (one-time migration,
 * not the ongoing-write switch), create engine enrollments with mapping rows,
 * and write attendance for every legacy session record whose student is
 * linked. Idempotent: every step skips what already exists. Never deletes.
 *
 * Scope note: HalaqaReferenceReader::listEnrollments exposes ACTIVE Hifz
 * enrollments — paused/completed/transferred stay legacy-only until their own
 * migration decision (recorded in STATUS). Attendance of unlinked students is
 * skipped here and reported by VerifyHalaqaStructureAction, never guessed.
 */
class BackfillHalaqaStructureAction
{
    /**
     * @return array{
     *     programs: int,
     *     programs_mapped: int,
     *     sessions_mirrored: int,
     *     enrollments_linked: int,
     *     attendance_written: int
     * }
     */
    public function execute(?int $createdBy = null): array
    {
        $reader = app(HalaqaReferenceReader::class);

        $programsMapped = 0;
        $sessionsMirrored = 0;
        $enrollmentsLinked = 0;
        $attendanceWritten = 0;

        $programs = $reader->listPrograms();
        foreach ($programs as $program) {
            $programId = (int) $program['id'];
            $map = app(MapHalaqaProgramAction::class)->execute($programId, $createdBy);
            if ($map['created']) {
                $programsMapped++;
            }

            foreach ($reader->listSessions($programId) as $session) {
                $result = app(MirrorHalaqaSessionAction::class)->execute(
                    (int) $session['id'],
                    $map['offering_id'],
                    requireDualWrite: false,
                );
                if ($result['created']) {
                    $sessionsMirrored++;
                }
            }

            $studentToEnrollment = [];
            foreach ($reader->listEnrollments($programId) as $enrollment) {
                $hifzEnrollmentId = (int) $enrollment['id'];
                $link = OfferingHalaqaEnrollmentLink::query()
                    ->where('hifz_enrollment_id', $hifzEnrollmentId)
                    ->first();
                if ($link === null) {
                    $engineEnrollment = app(EnrollUnifiedStudentInOfferingAction::class)->execute(
                        (int) $enrollment['student_id'],
                        $map['course_id'],
                        $map['offering_id'],
                        $createdBy,
                    );
                    $link = OfferingHalaqaEnrollmentLink::query()->create([
                        'course_enrollment_id' => $engineEnrollment->id,
                        'hifz_enrollment_id' => $hifzEnrollmentId,
                    ]);
                    $enrollmentsLinked++;
                }
                $studentToEnrollment[(int) $enrollment['student_id']] = (int) $link->course_enrollment_id;
            }

            foreach ($reader->listSessions($programId) as $session) {
                $sessionLink = OfferingHalaqaSessionLink::query()
                    ->where('hifz_session_id', (int) $session['id'])
                    ->first();
                if ($sessionLink === null) {
                    continue;
                }
                foreach ($reader->listSessionRecords((int) $session['id']) as $record) {
                    $enrollmentId = $studentToEnrollment[(int) $record['student_id']] ?? null;
                    if ($enrollmentId === null) {
                        continue;
                    }
                    $exists = AttendanceRecord::query()
                        ->where('course_offering_session_id', $sessionLink->course_offering_session_id)
                        ->where('enrollment_id', $enrollmentId)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    app(RecordOfferingAttendanceAction::class)->execute([
                        'course_offering_session_id' => $sessionLink->course_offering_session_id,
                        'enrollment_id' => $enrollmentId,
                        'status' => (string) $record['attendance_status'],
                        'attendance_mode' => 'physical',
                        'marked_by' => $createdBy,
                    ]);
                    $attendanceWritten++;
                }
            }
        }

        return [
            'programs' => count($programs),
            'programs_mapped' => $programsMapped,
            'sessions_mirrored' => $sessionsMirrored,
            'enrollments_linked' => $enrollmentsLinked,
            'attendance_written' => $attendanceWritten,
        ];
    }
}
