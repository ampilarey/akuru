<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\OfferingHalaqaEnrollmentLink;
use App\Domains\Offerings\Models\OfferingHalaqaLink;
use App\Domains\Offerings\Models\OfferingHalaqaSessionLink;
use App\Support\Contracts\HalaqaReferenceReader;

/**
 * F2 verification gate (rule 9, S1.1b template): is the whole Hifz structure —
 * programs, enrollments, sessions, attendance — represented on the engine
 * side? Read-only; unresolved rows are listed, never guessed. Checks what the
 * reader exposes (active enrollments); an unmapped program fails the gate and
 * its details are not enumerated further.
 */
class VerifyHalaqaStructureAction
{
    /**
     * @return array{
     *     programs: int,
     *     unmapped_programs: list<array{hifz_program_id: int, name: string}>,
     *     enrollments: int,
     *     unlinked_enrollments: list<array{hifz_program_id: int, hifz_enrollment_id: int, student_id: int}>,
     *     sessions: int,
     *     unmirrored_sessions: list<array{hifz_program_id: int, hifz_session_id: int}>,
     *     attendance_expected: int,
     *     attendance_missing: list<array{hifz_session_id: int, student_id: int}>,
     *     ok: bool
     * }
     */
    public function execute(): array
    {
        $reader = app(HalaqaReferenceReader::class);

        $unmappedPrograms = [];
        $unlinkedEnrollments = [];
        $unmirroredSessions = [];
        $attendanceMissing = [];
        $enrollmentTotal = 0;
        $sessionTotal = 0;
        $attendanceExpected = 0;

        $programs = $reader->listPrograms();
        foreach ($programs as $program) {
            $programId = (int) $program['id'];
            $link = OfferingHalaqaLink::query()
                ->where('hifz_program_id', $programId)
                ->first();
            if ($link === null) {
                $unmappedPrograms[] = [
                    'hifz_program_id' => $programId,
                    'name' => (string) ($program['name'] ?? ''),
                ];

                continue;
            }

            $studentToEnrollment = [];
            foreach ($reader->listEnrollments($programId) as $enrollment) {
                $enrollmentTotal++;
                $enrollmentLink = OfferingHalaqaEnrollmentLink::query()
                    ->where('hifz_enrollment_id', (int) $enrollment['id'])
                    ->first();
                if ($enrollmentLink === null) {
                    $unlinkedEnrollments[] = [
                        'hifz_program_id' => $programId,
                        'hifz_enrollment_id' => (int) $enrollment['id'],
                        'student_id' => (int) $enrollment['student_id'],
                    ];

                    continue;
                }
                $studentToEnrollment[(int) $enrollment['student_id']] = (int) $enrollmentLink->course_enrollment_id;
            }

            foreach ($reader->listSessions($programId) as $session) {
                $sessionTotal++;
                $sessionLink = OfferingHalaqaSessionLink::query()
                    ->where('hifz_session_id', (int) $session['id'])
                    ->first();
                if ($sessionLink === null) {
                    $unmirroredSessions[] = [
                        'hifz_program_id' => $programId,
                        'hifz_session_id' => (int) $session['id'],
                    ];

                    continue;
                }

                foreach ($reader->listSessionRecords((int) $session['id']) as $record) {
                    $enrollmentId = $studentToEnrollment[(int) $record['student_id']] ?? null;
                    if ($enrollmentId === null) {
                        continue;
                    }
                    $attendanceExpected++;
                    $present = AttendanceRecord::query()
                        ->where('course_offering_session_id', $sessionLink->course_offering_session_id)
                        ->where('enrollment_id', $enrollmentId)
                        ->exists();
                    if (! $present) {
                        $attendanceMissing[] = [
                            'hifz_session_id' => (int) $session['id'],
                            'student_id' => (int) $record['student_id'],
                        ];
                    }
                }
            }
        }

        return [
            'programs' => count($programs),
            'unmapped_programs' => $unmappedPrograms,
            'enrollments' => $enrollmentTotal,
            'unlinked_enrollments' => $unlinkedEnrollments,
            'sessions' => $sessionTotal,
            'unmirrored_sessions' => $unmirroredSessions,
            'attendance_expected' => $attendanceExpected,
            'attendance_missing' => $attendanceMissing,
            'ok' => $unmappedPrograms === []
                && $unlinkedEnrollments === []
                && $unmirroredSessions === []
                && $attendanceMissing === [],
        ];
    }
}
