<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use App\Domains\People\Enums\GuardianRelationship;
use App\Support\Navigation\BuildNavigationAction;
use App\Support\Navigation\ResolveWorkspacesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * *My learning* (docs/SIGN_IN_PLAN.md, ID2a): a workspace held by a login
 * with learning of its own, derived from its enrolments rather than granted
 * as a role. The owner, 2026-09-28: "When a parent is enrolled in a course
 * … when he changes to student, he will see educational items; when he is
 * in parent, he sees all his children."
 */
function learnerPerson(array $roles = []): User
{
    test()->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create();
    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

/** Enrols the person in a new course the way the website does: their own student record, then the enrolment. */
function enrolOwn(User $user, string $status = 'active', string $payment = 'not_required', string $title = 'Tajweed for adults'): CourseEnrollment
{
    $student = app(RegisterCourseStudentAction::class)->forSelf((int) $user->id, ['first_name' => 'Adult', 'last_name' => 'Learner', 'dob' => '1990-01-01']);
    $course = Course::factory()->create(['title' => $title]);

    return CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'unified_student_id' => $student['id'],
        'status' => $status,
        'payment_status' => $payment,
        'enrolled_at' => $status === 'active' ? now() : null,
        'created_by_user_id' => $user->id,
    ]);
}

function heldWorkspaces(User $user): array
{
    return array_column(app(ResolveWorkspacesAction::class)->execute($user)['list'], 'key');
}

it('holds My learning for a person with learning of their own, and not for a pupil or a refused enrolment', function () {
    $adult = learnerPerson();
    enrolOwn($adult);
    $resolved = app(ResolveWorkspacesAction::class)->execute($adult);
    expect(array_column($resolved['list'], 'key'))->toBe(['learner'])
        ->and($resolved['active'])->toBe('learner')
        ->and($resolved['list'][0]['href'])->toBe('/learn')
        ->and($resolved['list'][0]['label'])->toBe('My learning');

    // Held beside a job, and last: a parent lands on Family, a teacher on the School.
    $parent = learnerPerson(['parent']);
    enrolOwn($parent);
    expect(heldWorkspaces($parent))->toBe(['family', 'learner'])
        ->and(app(ResolveWorkspacesAction::class)->execute($parent)['active'])->toBe('family');
    $teacher = learnerPerson(['teacher']);
    enrolOwn($teacher);
    expect(heldWorkspaces($teacher))->toBe(['school', 'learner']);

    // A school pupil's courses are already in Learn.
    $pupil = learnerPerson(['student']);
    enrolOwn($pupil);
    expect(heldWorkspaces($pupil))->toBe(['learn']);

    // A refused enrolment, or none at all, is no learning.
    $refused = learnerPerson();
    enrolOwn($refused, 'rejected');
    expect(heldWorkspaces($refused))->toBe(['account'])
        ->and(heldWorkspaces(learnerPerson()))->toBe(['account']);
});

it('lands a learner on My learning, and a parent-learner on Family with My learning one switch away', function () {
    $adult = learnerPerson();
    enrolOwn($adult);
    $this->withoutLocalizationMiddleware()->actingAs($adult)->get(route('dashboard'))->assertRedirect(route('learn.dashboard'));
    // The family home is not theirs: they are sent to their own.
    $this->withoutLocalizationMiddleware()->actingAs($adult)->get(route('portal.home'))->assertRedirect(route('dashboard'));

    $parent = learnerPerson(['parent']);
    enrolOwn($parent);
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('dashboard'))->assertRedirect(route('portal.home'));
    $this->withoutLocalizationMiddleware()->actingAs($parent)->post(route('workspace.switch', 'learner'))->assertRedirect('/learn');
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('learn.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Learn/Dashboard')
            ->where('auth.workspace', 'learner')
            ->where('auth.workspaces.1.label', 'My learning')
            ->where('nav.primary', fn ($bar) => collect($bar)->pluck('label')->all() === ['My learning', 'Schedule', 'Browse courses'])
            ->where('nav.groups', fn ($groups) => collect($groups)->pluck('key')->all() === ['education', 'me'])
            ->where('enrollments.0.title', 'Tajweed for adults'));

    // My learning carries the person's own courses and none of their children's screens.
    $learner = app(BuildNavigationAction::class)->execute($parent, 'en', 'learner');
    $hrefs = collect($learner['groups'])->flatMap(fn ($group) => array_column($group['items'], 'href'))->all();
    expect($hrefs)->toContain('/learn', '/learn/schedule', '/my-enrollments')
        ->not->toContain('/portal/children', '/portal/homework', '/portal/holidays', '/portal/loans', '/portal/messages');

    // And Family carries none of their learning. My enrolments is there since
    // ID2b: the enrolments a parent made, their children's among them.
    $family = app(BuildNavigationAction::class)->execute($parent, 'en', 'family');
    $familyHrefs = collect($family['groups'])->flatMap(fn ($group) => array_column($group['items'], 'href'))->all();
    expect($familyHrefs)->not->toContain('/learn', '/learn/schedule')->toContain('/my-enrollments');
});

it('shows the enrolments still waiting on My learning, and on what', function () {
    $adult = learnerPerson();
    enrolOwn($adult, 'pending', 'pending', 'Arabic for beginners');
    enrolOwn($adult, 'pending', 'confirmed', 'Seerah');

    // Waiting enrolments alone make them a learner, so an unpaid registration is not lost.
    expect(heldWorkspaces($adult))->toBe(['learner']);
    $this->withoutLocalizationMiddleware()->actingAs($adult)->get(route('learn.dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('waiting', 2)
            ->where('waiting', fn ($rows) => collect($rows)->pluck('awaiting', 'title')->all() === ['Seerah' => 'approval', 'Arabic for beginners' => 'payment']
                || collect($rows)->pluck('awaiting', 'title')->all() === ['Arabic for beginners' => 'payment', 'Seerah' => 'approval'])
            ->has('enrollments', 0)
            ->where('i18n.learn.waiting_payment', 'Awaiting payment'));
});

it('keeps a parent-learner’s own record off the Family home, and a pupil’s on theirs', function () {
    $parent = learnerPerson(['parent']);
    enrolOwn($parent);
    $guardian = \App\Domains\People\Models\ParentGuardian::query()->create([
        'user_id' => $parent->id, 'first_name' => 'Hawwa', 'last_name' => 'Ali', 'phone' => '7000001',
        'email' => $parent->email, 'address' => 'Malé', 'relationship' => 'mother',
    ]);
    $child = makeStudent(['first_name' => 'Zara', 'last_name' => 'Ali']);
    app(AttachGuardianAction::class)->execute($child, $guardian, GuardianRelationship::Mother, true);

    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('title', 'Parent Dashboard')
            ->has('students', 1)->where('students.0.name', 'Zara Ali'));

    $pupil = learnerPerson(['student']);
    enrolOwn($pupil);
    $this->withoutLocalizationMiddleware()->actingAs($pupil)->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('title', 'Student Dashboard')->where('students.0.relationship', 'self'));
});

it('labels My learning and its waiting list in Dhivehi and Arabic', function () {
    foreach (['nav.workspace_learner', 'nav.my_learning', 'nav.browse_courses', 'nav.my_enrolments', 'learn.waiting_title', 'learn.waiting_payment', 'learn.waiting_approval', 'learn.waiting_details'] as $key) {
        foreach (['dv', 'ar'] as $locale) {
            expect(trans($key, [], $locale))->not->toBe($key)->not->toBe(trans($key, [], 'en'));
        }
    }
});
