<?php

use App\Domains\Academics\Actions\SaveStudentWorkAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\CoursePlan;
use App\Domains\Academics\Models\PickupNotice;
use App\Domains\Circulation\Models\BookTitle;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * CLAUDE.md's standing convention: *"every listing gets CSV export."*
 *
 * Counting `*.index` routes against `*.export` routes found seven screens that
 * genuinely list something and had no way to get it off the system — classes,
 * academic years, teaching plans, student work, the pick-up console,
 * circulation and the user roster. This closes all seven.
 *
 * **Every case asserts a seeded value is in the body, not just a 200.** This
 * repo has shipped enough empty grids that a passing status code is not
 * evidence of anything; a CSV that is a header row and nothing else answers
 * 200 exactly as happily as one with the data in it.
 */
function exportStaff(string $role = 'admin', array $permissions = []): User
{
    Role::findOrCreate($role, 'web');

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user->fresh();
}

/** The streamed body, which `getContent()` does not give you. */
function csvBody(\Illuminate\Testing\TestResponse $response): string
{
    return $response->streamedContent();
}

it('exports the class directory for the year on screen', function () {
    $year = makeYear(['status' => AcademicYearStatus::Active, 'is_current' => true]);
    makeClass($year, 'Grade 4', 'Rose');

    $response = $this->withoutLocalizationMiddleware()->actingAs(exportStaff())
        ->get(route('academics.classes.export', ['academic_year_id' => $year->id]))
        ->assertOk();

    expect(csvBody($response))
        ->toContain('name,section,capacity')
        ->toContain('Grade 4')
        ->toContain('Rose');
});

it('exports academic years with their terms flattened, and a year with no terms still appears', function () {
    $withTerms = makeYear(['name' => '2026-2027', 'status' => AcademicYearStatus::Active, 'is_current' => true]);
    makeTerm($withTerms, 'Term 1');
    makeYear(['name' => '2027-2028', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);

    $response = $this->withoutLocalizationMiddleware()->actingAs(exportStaff())
        ->get(route('academics.years.export'))
        ->assertOk();

    $body = csvBody($response);

    expect($body)->toContain('2026-2027')->toContain('Term 1')
        // The empty year is the row somebody would be checking for, so its
        // absence would make the export misleading rather than merely short.
        ->and($body)->toContain('2027-2028');
});

it('exports teaching plans with their topic counts', function () {
    $year = makeYear(['status' => AcademicYearStatus::Active, 'is_current' => true]);

    $plan = CoursePlan::query()->create([
        'teacher_id' => makeTeacherRow()->id,
        'subject_id' => makeSubject()->id,
        'classroom_id' => makeClass($year)->id,
        'academic_year_id' => $year->id,
        'title' => 'Arabic scheme of work',
        'status' => 'draft',
    ]);
    $plan->topics()->create(['title' => 'Letters', 'order' => 1, 'is_completed' => true]);
    $plan->topics()->create(['title' => 'Vowels', 'order' => 2, 'is_completed' => false]);

    $response = $this->withoutLocalizationMiddleware()->actingAs(exportStaff('headmaster', ['registers.manage']))
        ->get(route('academics.plans.export'))
        ->assertOk();

    expect(csvBody($response))
        ->toContain('Arabic scheme of work')
        // Two topics, one of them done.
        ->toContain(',2,1');
});

it('exports the student work log with how many times each photo has been moved', function () {
    Storage::fake('local');
    makeYear(['status' => AcademicYearStatus::Active, 'is_current' => true]);
    $student = makeStudent();
    $staff = exportStaff();

    // Through the real action rather than a hand-built row: `photo_media_id`
    // is NOT NULL, and a fixture that forces past that would be testing an
    // arrangement the app cannot produce.
    app(SaveStudentWorkAction::class)->execute(
        ['student_id' => $student->id, 'title' => 'Clay mosque', 'done_on' => '2026-03-04'],
        (int) $staff->id,
        UploadedFile::fake()->image('work.jpg'),
    );

    $response = $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.work.export'))
        ->assertOk();

    expect(csvBody($response))
        ->toContain('Clay mosque')
        ->toContain('times_moved')
        ->toContain($student->first_name);
});

it("exports one day's pick-up log, and not another day's", function () {
    $year = makeYear(['status' => AcademicYearStatus::Active, 'is_current' => true]);
    $student = makeStudent();
    $guardian = User::factory()->create(['name' => 'Aminath Guardian']);

    PickupNotice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'guardian_user_id' => $guardian->id,
        'date' => '2026-03-04',
        'status' => 'requested',
        'requested_at' => '2026-03-04 12:00:00',
        'note' => 'Dentist',
    ]);

    $response = $this->withoutLocalizationMiddleware()->actingAs(exportStaff())
        ->get(route('academics.pickup.export', ['date' => '2026-03-04']))
        ->assertOk();

    expect(csvBody($response))->toContain('Dentist')->toContain('Aminath Guardian')->toContain('waiting');

    // The console is one day's work by design, and so is its export.
    $otherDay = $this->withoutLocalizationMiddleware()->actingAs(exportStaff())
        ->get(route('academics.pickup.export', ['date' => '2026-03-05']))
        ->assertOk();

    expect(csvBody($otherDay))->not->toContain('Dentist');
});

it('exports the circulation stock with its copy counts', function () {
    BookTitle::query()->create([
        'title' => 'Dhivehi Raajjeyge Thaareekh',
        'author' => 'Hassan Ahmed',
        'isbn' => '9789991500010',
        'loan_days' => 14,
    ]);

    $response = $this->withoutLocalizationMiddleware()->actingAs(exportStaff())
        ->get(route('circulation.export'))
        ->assertOk();

    expect(csvBody($response))
        ->toContain('Dhivehi Raajjeyge Thaareekh')
        ->toContain('total_copies,available,on_loan');
});

it('exports the user roster with roles, and without the identity documents', function () {
    $subject = User::factory()->create(['name' => 'Ibrahim Roster', 'national_id' => 'A123456']);
    Role::findOrCreate('teacher', 'web');
    $subject->assignRole('teacher');

    $response = $this->withoutLocalizationMiddleware()->actingAs(exportStaff('super_admin'))
        ->get(route('admin.users.export'))
        ->assertOk();

    $body = csvBody($response);

    expect($body)->toContain('Ibrahim Roster')->toContain('teacher')
        // On the screen behind a super_admin login; deliberately not in a file
        // that leaves the building. Same call as the staff export.
        ->and($body)->not->toContain('A123456');
});

it('gives a teacher only their own plans in the export, the way the screen does', function () {
    $year = makeYear(['status' => AcademicYearStatus::Active, 'is_current' => true]);
    $class = makeClass($year);
    $subject = makeSubject();

    $mine = makeTeacherRow();
    $theirs = makeTeacherRow();

    foreach ([[$mine, 'My own plan'], [$theirs, 'Somebody else\'s plan']] as [$teacher, $title]) {
        CoursePlan::query()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $class->id,
            'academic_year_id' => $year->id,
            'title' => $title,
            'status' => 'draft',
        ]);
    }

    Role::findOrCreate('teacher', 'web');
    Permission::findOrCreate('registers.fill', 'web');
    $user = User::query()->find($mine->user_id);
    $user->assignRole('teacher');
    $user->givePermissionTo('registers.fill');

    $response = $this->withoutLocalizationMiddleware()->actingAs($user->fresh())
        ->get(route('academics.plans.export'))
        ->assertOk();

    $body = csvBody($response);

    // The point of the case: an export that ignored the screen's scoping
    // would be a way around the filter rather than a copy of it.
    expect($body)->toContain('My own plan')->and($body)->not->toContain("Somebody else's plan");
});
