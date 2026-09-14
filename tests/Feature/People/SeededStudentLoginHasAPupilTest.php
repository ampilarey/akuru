<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\People\Models\Student;
use Database\Seeders\PilotRehearsalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The seeded student login is somebody.
 *
 * ## What was wrong
 *
 * `UserSeeder` creates `student@akuru.edu.mv` and stops. Two lines above it the
 * teacher gets a `teachers` row from `EnsureTeacherRowAction`; the student got
 * nothing, and `PilotRehearsalSeeder` then created all fifteen pupils with
 * `user_id` explicitly null.
 *
 * So `ResolveStudentForUserAction` answered **null** for that login, and
 * everything keyed on *which pupil is this?* was unreachable: `/learn`, lesson
 * access, a course enrolment, the pupil's own progress. Anybody exploring a
 * seeded app as a student hit that wall — the operator walking staging
 * included, which is the gate the rest of the go-live list waits on.
 *
 * Nothing failed while it was broken. A seeder that quietly links nobody makes
 * the feature look absent rather than unseeded, which is the same shape as the
 * HR profiles the marker seeder already had to work around.
 *
 * ## Why this is a test and not a comment
 *
 * The link is one line in a seeder, and the failure it prevents is silent and
 * far away from it. This is the cheapest thing that notices.
 */
it('gives the seeded student login a pupil to be', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\SchoolSeeder::class);
    $this->seed(\Database\Seeders\PeriodSeeder::class);
    $this->seed(\Database\Seeders\SubjectSeeder::class);
    $this->seed(\Database\Seeders\ClassSeeder::class);
    $this->seed(\Database\Seeders\UserSeeder::class);
    $this->seed(PilotRehearsalSeeder::class);

    $user = User::query()->where('email', PilotRehearsalSeeder::STUDENT_EMAIL)->firstOrFail();

    $pupil = app(ResolveStudentForUserAction::class)->execute((int) $user->id);

    expect($pupil)->not->toBeNull(
        'The seeded student login resolves to no pupil, so /learn, lesson access and enrolment '
        .'are all unreachable for anybody walking a seeded app.'
    );

    // The pupil the rest of the seed already knows about — on the class roster,
    // with a guardian — rather than a child invented for the login.
    expect(Student::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('does not move the login to a second pupil when the seeder runs again', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\SchoolSeeder::class);
    $this->seed(\Database\Seeders\PeriodSeeder::class);
    $this->seed(\Database\Seeders\SubjectSeeder::class);
    $this->seed(\Database\Seeders\ClassSeeder::class);
    $this->seed(\Database\Seeders\UserSeeder::class);
    $this->seed(PilotRehearsalSeeder::class);

    $user = User::query()->where('email', PilotRehearsalSeeder::STUDENT_EMAIL)->firstOrFail();
    $first = Student::query()->where('user_id', $user->id)->value('id');

    $this->seed(PilotRehearsalSeeder::class);

    expect(Student::query()->where('user_id', $user->id)->pluck('id')->all())->toBe([$first]);
});
