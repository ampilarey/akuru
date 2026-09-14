<?php

namespace App\Domains\Hifz\Services;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\MirrorHalaqaSessionAction;
use App\Domains\People\Actions\ListStudentIdsOnTheRollAction;
use App\Domains\People\Models\Teacher;
use App\Enums\Hifz\HifzSessionStatus;
use Carbon\Carbon;
use Throwable;

class HifzSessionService
{
    public function createSessionForToday(HifzProgram $program, Teacher $teacher, User $creator): HifzSession
    {
        $session = HifzSession::firstOrCreate(
            [
                'hifz_program_id' => $program->id,
                'teacher_id' => $teacher->id,
                'session_date' => Carbon::today()->toDateString(),
            ],
            [
                'supervisor_id' => $program->supervisor_id,
                'status' => HifzSessionStatus::Draft,
                'created_by' => $creator->id,
            ]
        );

        $enrollments = HifzEnrollment::where('hifz_program_id', $program->id)
            ->where('status', 'active')
            ->where('teacher_id', $teacher->id)
            ->get();

        // A hifz enrolment outlives the pupil. `hifz_enrollments.status` is
        // `active` / `paused` / `completed` / `transferred`, it has no value
        // meaning "left the school", and `HifzEnrollmentController` has no
        // update path at all — index, create, store and nothing else — so an
        // enrolment reads `active` for ever once it is made.
        //
        // The school's own record does move: a pupil marked withdrawn comes
        // off the class register the same day. So the roll is asked here
        // rather than trusting the enrolment, and today's halaqa work is not
        // generated for a child who has gone. The enrolment row is left alone:
        // choosing which of its four words describes a departure is the
        // owner's call, not this guard's.
        $present = app(ListStudentIdsOnTheRollAction::class)
            ->execute($enrollments->pluck('student_id'));

        foreach ($enrollments as $enrollment) {
            if (! in_array((int) $enrollment->student_id, $present, true)) {
                continue;
            }

            HifzSessionRecord::firstOrCreate(
                [
                    'hifz_session_id' => $session->id,
                    'student_id' => $enrollment->student_id,
                ],
                [
                    'hifz_program_id' => $program->id,
                    'teacher_id' => $teacher->id,
                    'supervisor_id' => $enrollment->supervisor_id ?? $program->supervisor_id,
                    'created_by' => $creator->id,
                ]
            );
        }

        if (config('quran.halaqa_dual_write')) {
            try {
                app(MirrorHalaqaSessionAction::class)->execute($session->id);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $session->load('records.student.user');
    }
}
