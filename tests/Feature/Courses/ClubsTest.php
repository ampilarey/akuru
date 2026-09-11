<?php

use App\Domains\Courses\Actions\CancelEnrollmentAction;
use App\Domains\Courses\Components\Clubs\Actions\AddClubMemberAction;
use App\Domains\Courses\Components\Clubs\Actions\ListClubRosterAction;
use App\Domains\Courses\Components\Clubs\Actions\ListClubsAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E17 — school clubs.
 *
 * **There is no clubs table and no club-membership table**, and that is the
 * point. A club is a `Course` with `course_type = 'club'`; its members are
 * ordinary `CourseEnrollment` rows. The plan asked for exactly this — "the
 * course engine can model these already … resist a parallel enrolment system"
 * (rule 11) — so most of what is asserted here is that nothing new was
 * invented.
 */
function makeClub(string $title = 'Chess club'): Course
{
    $club = Course::factory()->create([
        'title' => $title,
        'course_type' => 'club',
        'workflow_status' => 'published',
    ]);

    // A club runs through an offering like any other course; the roster screen
    // says so plainly when one is missing. `self_learning` because that is the
    // mode DefaultSelfLearningOfferingAction resolves.
    CourseOffering::query()->create([
        'course_id' => $club->id,
        'title' => $title.' offering',
        'slug' => Str::slug($title).'-offering-'.$club->id,
        'delivery_mode' => 'self_learning',
        'status' => 'open',
        'pin_mode' => 'latest',
        'academic_year_id' => makeYear(['name' => 'Club year '.$club->id])->id,
    ]);

    return $club;
}

function clubStudentId(): int
{
    return (int) makeStudent(['first_name' => 'Club', 'last_name' => 'Member '.uniqid()])->id;
}

function clubStaff(): User
{
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user->fresh();
}

it('lists clubs and counts members without a clubs table', function () {
    $club = makeClub();
    Course::factory()->create(['title' => 'Ordinary course', 'course_type' => 'general']);

    $clubs = app(ListClubsAction::class)->execute();

    expect($clubs)->toHaveCount(1)
        ->and($clubs->first()['title'])->toBe('Chess club')
        ->and($clubs->first()['members'])->toBe(0)
        // The engine is untouched: no table named for clubs exists.
        ->and(Schema::hasTable('clubs'))->toBeFalse()
        ->and(Schema::hasTable('club_members'))->toBeFalse();
});

it('adds a member through the engine rather than a parallel system', function () {
    $club = makeClub();
    $staff = clubStaff();
    $studentId = clubStudentId();

    $enrollmentId = app(AddClubMemberAction::class)->execute($club->id, $studentId, $staff->id);

    expect($enrollmentId)->toBeGreaterThan(0)
        // The row is an ordinary course enrollment.
        ->and(CourseEnrollment::query()->whereKey($enrollmentId)->value('course_id'))->toBe($club->id);

    expect(app(ListClubsAction::class)->execute()->first()['members'])->toBe(1);
});

it('says so plainly when a club has no offering yet', function () {
    // "Nothing happened" on a roster screen is the failure mode this session
    // spent all day fixing.
    $club = Course::factory()->create(['course_type' => 'club', 'workflow_status' => 'published']);
    $studentId = clubStudentId();

    expect(fn () => app(AddClubMemberAction::class)->execute($club->id, $studentId))
        ->toThrow(ValidationException::class);
});

it('accepts a member who is not on any class roster, and marks them a visitor', function () {
    // The plan's "members from outside the enrolled roll". It needed no code —
    // only the absence of a check — but the register must still say which is
    // which, or a club leader cannot trust it.
    $club = makeClub();
    $offRollId = clubStudentId();

    app(AddClubMemberAction::class)->execute($club->id, $offRollId);

    $roster = app(ListClubRosterAction::class)->execute($club->id);

    expect($roster)->toHaveCount(1)
        ->and($roster->first()['on_roll'])->toBeFalse();
});

it('cancels rather than deletes when a member leaves', function () {
    $club = makeClub();
    $enrollmentId = app(AddClubMemberAction::class)->execute($club->id, clubStudentId());

    expect(app(CancelEnrollmentAction::class)->execute($enrollmentId))->toBeTrue()
        // The record that they were in the club last term survives.
        ->and(CourseEnrollment::query()->whereKey($enrollmentId)->exists())->toBeTrue()
        ->and(CourseEnrollment::query()->whereKey($enrollmentId)->value('status'))->toBe('cancelled')
        ->and(app(ListClubRosterAction::class)->execute($club->id))->toHaveCount(0);

    // Removing twice is not an error, but the caller can tell the difference.
    expect(app(CancelEnrollmentAction::class)->execute($enrollmentId))->toBeFalse();
});

it('keeps one club roster out of another', function () {
    $chess = makeClub('Chess club');
    $debate = makeClub('Debate club');
    app(AddClubMemberAction::class)->execute($chess->id, clubStudentId());

    expect(app(ListClubRosterAction::class)->execute($chess->id))->toHaveCount(1)
        ->and(app(ListClubRosterAction::class)->execute($debate->id))->toHaveCount(0);
});

it('walks the club screens over http', function () {
    $club = makeClub();
    $staff = clubStaff();
    $studentId = clubStudentId();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.clubs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Courses/Clubs/Index')->has('clubs', 1)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->post(route('academics.clubs.members.add', ['club' => $club->id]), ['student_id' => $studentId])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.clubs.show', ['club' => $club->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Courses/Clubs/Roster')->has('members', 1)->etc());

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.clubs.attendance-sheet', ['club' => $club->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Courses/Clubs/AttendanceSheet')->etc());

    $this->withoutLocalizationMiddleware()->actingAs($staff)
        ->get(route('academics.clubs.export', ['club' => $club->id]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('refuses the club screens to a family account', function () {
    $club = makeClub();
    $family = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($family)
        ->get(route('academics.clubs.index'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($family)
        ->post(route('academics.clubs.members.add', ['club' => $club->id]), ['student_id' => 1])
        ->assertForbidden();
});
