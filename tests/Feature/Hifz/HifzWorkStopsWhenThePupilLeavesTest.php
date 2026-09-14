<?php

namespace Tests\Feature\Hifz;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzSessionService;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ChangeStudentStatusAction;
use App\Domains\People\Actions\ListStudentIdsOnTheRollAction;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaqa work is not generated for a child who has left the Institute.
 *
 * ## The defect
 *
 * A hifz enrolment outlives the pupil. `hifz_enrollments.status` is
 * `active` / `paused` / `completed` / `transferred` — **no value means "left
 * the school"** — and `HifzEnrollmentController` has `index`, `create` and
 * `store` and nothing else, so an enrolment reads `active` for ever once made.
 * `store` does not even accept a status; it relies on the column default.
 *
 * So withdrawing a pupil left `HifzSessionService` creating a session record
 * for them every day a halaqa met, and the dean's **active students** card
 * counting them indefinitely.
 *
 * ## What this fixes, and what it deliberately leaves
 *
 * The school's own roll does move — a withdrawal comes off the class register
 * the same day — so the roll is asked at the two points that matter: where
 * work is generated, and where people are counted.
 *
 * The enrolment row is **left alone**. Choosing which of its four words
 * describes a departure, or adding a fifth, is a vocabulary decision for the
 * owner; a guard that stops generating work needs no such decision. That gap
 * is recorded rather than quietly filled.
 */
class HifzWorkStopsWhenThePupilLeavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\SchoolSeeder::class);
        $this->seed(\Database\Seeders\ClassSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->seed(\Database\Seeders\SurahSeeder::class);
        $this->seed(HifzDemoSeeder::class);
    }

    private function anActiveEnrolment(): HifzEnrollment
    {
        return HifzEnrollment::where('status', 'active')->firstOrFail();
    }

    public function test_no_session_record_is_created_for_a_pupil_who_has_left(): void
    {
        $enrollment = $this->anActiveEnrolment();
        $student = Student::findOrFail($enrollment->student_id);
        $program = HifzProgram::findOrFail($enrollment->hifz_program_id);
        $teacher = Teacher::findOrFail($enrollment->teacher_id);
        $creator = User::query()->firstOrFail();

        // The happy path first: while the pupil is on the roll, a record is
        // created. Without this half, a guard that refused everybody would
        // pass the test below just as happily.
        $session = app(HifzSessionService::class)->createSessionForToday($program, $teacher, $creator);

        $this->assertTrue(
            HifzSessionRecord::where('hifz_session_id', $session->id)
                ->where('student_id', $student->id)
                ->exists(),
            'A pupil on the roll should get a session record.'
        );

        HifzSessionRecord::where('hifz_session_id', $session->id)->delete();

        app(ChangeStudentStatusAction::class)->execute($student, StudentStatus::Withdrawn, $creator->id);

        $session = app(HifzSessionService::class)->createSessionForToday($program, $teacher, $creator);

        $this->assertFalse(
            HifzSessionRecord::where('hifz_session_id', $session->id)
                ->where('student_id', $student->id)
                ->exists(),
            'A pupil who has left the Institute should not get today\'s halaqa work.'
        );
    }

    public function test_the_enrolment_row_is_left_alone(): void
    {
        $enrollment = $this->anActiveEnrolment();
        $student = Student::findOrFail($enrollment->student_id);

        app(ChangeStudentStatusAction::class)->execute(
            $student,
            StudentStatus::Withdrawn,
            User::query()->firstOrFail()->id,
        );

        // Not an oversight. The vocabulary has no word for this and picking
        // one is the owner's call; if that changes, this assertion is the
        // thing that should be updated deliberately rather than discovered.
        $this->assertSame('active', $enrollment->fresh()->status?->value);
    }

    public function test_the_dean_stops_counting_a_pupil_who_has_left(): void
    {
        $enrollment = $this->anActiveEnrolment();
        $student = Student::findOrFail($enrollment->student_id);

        $count = fn (): int => count(app(ListStudentIdsOnTheRollAction::class)->execute(
            HifzEnrollment::where('status', 'active')->pluck('student_id')
        ));

        $before = $count();
        $this->assertGreaterThan(0, $before, 'The fixture should have at least one active enrolment.');

        app(ChangeStudentStatusAction::class)->execute(
            $student,
            StudentStatus::Withdrawn,
            User::query()->firstOrFail()->id,
        );

        $this->assertSame($before - 1, $count());
    }

    public function test_the_roll_filter_keeps_everyone_who_is_still_here(): void
    {
        $ids = Student::query()->pluck('id')->all();

        $this->assertSame(
            Student::query()->onTheRoll()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            app(ListStudentIdsOnTheRollAction::class)->execute($ids),
            'The bulk filter and the scope must agree — they are the same question.'
        );

        $this->assertSame([], app(ListStudentIdsOnTheRollAction::class)->execute([]));
    }
}
