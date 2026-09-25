<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\People\Actions\GuardianMayCollectStudentAction;
use App\Domains\People\Actions\ListCollectableChildrenAction;
use App\Domains\People\Actions\ListFamilyUserIdsForStudentsAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\RecordGuardianLinkPolicyAction;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * OWNER_ACTIONS item 13 (2026-09-25): the parent↔child link's verification
 * status is a **gate**.
 *
 * The risk it closes: anyone with a phone number could register a "child" on
 * the public form under a real pupil's ID card number, be linked to that
 * pupil, and see their attendance, invoices and messages. Verification was a
 * column nothing read. Now a family reaches a child, and a child's news
 * reaches a family, only through a link the office has verified.
 */
function gateParent(): User
{
    $user = User::factory()->create(['name' => 'Aishath Naeem']);
    Role::findOrCreate('parent', 'web');
    $user->assignRole('parent');
    UserContact::query()->create([
        'user_id' => $user->id, 'type' => 'mobile', 'value' => '7'.random_int(100000, 999999),
        'is_primary' => true, 'verified_at' => now(),
    ]);

    return $user;
}

/** A pupil the office already has on the roll, with an ID card number. */
function gatePupil(string $nid = 'A123456'): Student
{
    return makeStudent(['first_name' => 'Real', 'last_name' => 'Pupil', 'national_id' => $nid]);
}

it('leaves a self-registered link unverified, and the parent sees the child only as awaiting', function () {
    $stranger = gateParent();
    $pupil = gatePupil('A123456');

    // The stranger registers "their child" with the real pupil's ID card.
    app(RegisterCourseStudentAction::class)->forChild($stranger->id, [
        'first_name' => 'Real', 'last_name' => 'Pupil', 'dob' => '2014-05-05',
        'gender' => 'female', 'national_id' => 'A123456',
    ], 'mother');

    $link = DB::table('guardian_student')->where('student_id', $pupil->id)->first();
    expect($link)->not->toBeNull()
        ->and($link->verification_status)->toBe('unverified')
        ->and($link->verified_at)->toBeNull();

    $list = app(ListGuardianChildrenAction::class);
    expect($list->executeForGuardianUserId($stranger->id))->toHaveCount(0)
        ->and($list->executePendingForGuardianUserId($stranger->id))->toHaveCount(1)
        ->and($list->executePendingForGuardianUserId($stranger->id)->first()->first_name)->toBe('Real');

    // The portal says so, by name, and shows nothing behind the link.
    $this->withoutLocalizationMiddleware()->actingAs($stranger)
        ->get(route('portal.children'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Children')
            ->has('children', 0)
            ->has('pending', 1)
            ->where('pending.0.first_name', 'Real')
            ->missing('pending.0.student_id')
            ->missing('pending.0.status'));
});

it('keeps an unverified link out of every family-facing answer', function () {
    $stranger = gateParent();
    $pupil = gatePupil('A222333');
    app(RegisterCourseStudentAction::class)->forChild($stranger->id, [
        'first_name' => 'Real', 'last_name' => 'Pupil', 'dob' => '2014-05-05', 'national_id' => 'A222333',
    ]);
    $guardian = ParentGuardian::query()->where('user_id', $stranger->id)->firstOrFail();

    expect($guardian->children()->count())->toBe(0)
        ->and($guardian->allLinkedChildren()->count())->toBe(1)
        ->and($stranger->courseStudents()->count())->toBe(0)
        ->and(app(ListCollectableChildrenAction::class)->execute($stranger->id))->toHaveCount(0)
        ->and(app(GuardianMayCollectStudentAction::class)->execute($stranger->id, $pupil->id))->toBeFalse()
        // The fan-out behind messages, digests, exam results and report cards.
        ->and(app(ListFamilyUserIdsForStudentsAction::class)->execute([$pupil->id]))->toBe([]);

    // And the family screens that authorise by child id refuse.
    $this->withoutLocalizationMiddleware()->actingAs($stranger)
        ->get(route('portal.attendance', ['student_id' => $pupil->id]))
        ->assertForbidden();
});

it('opens everything the moment the office verifies the link', function () {
    $parent = gateParent();
    $pupil = gatePupil('A333444');
    app(RegisterCourseStudentAction::class)->forChild($parent->id, [
        'first_name' => 'Real', 'last_name' => 'Pupil', 'dob' => '2014-05-05', 'national_id' => 'A333444',
    ], 'mother');
    $guardian = ParentGuardian::query()->where('user_id', $parent->id)->firstOrFail();
    $office = actingPeopleAdmin();

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->put(route('people.students.guardians.policy', [$pupil, $guardian]), ['verification_status' => 'verified'])
        ->assertRedirect();

    expect(app(ListGuardianChildrenAction::class)->executeForGuardianUserId($parent->id))->toHaveCount(1)
        ->and(app(ListGuardianChildrenAction::class)->executePendingForGuardianUserId($parent->id))->toHaveCount(0)
        ->and($guardian->children()->count())->toBe(1)
        ->and(app(ListFamilyUserIdsForStudentsAction::class)->execute([$pupil->id]))->toBe([$parent->id]);

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('portal.attendance', ['student_id' => $pupil->id]))
        ->assertOk();
});

it('closes again when the office rejects a link', function () {
    $parent = gateParent();
    $pupil = gatePupil('A444555');
    $office = actingPeopleAdmin();
    $guardian = makeGuardian();
    $guardian->forceFill(['user_id' => $parent->id])->save();
    app(\App\Domains\People\Actions\AttachGuardianAction::class)->execute($pupil, $guardian, 'father', actorId: $office->id);

    expect($guardian->children()->count())->toBe(1);

    app(RecordGuardianLinkPolicyAction::class)->execute($pupil, $guardian, ['verification_status' => 'rejected'], $office->id);

    expect($guardian->fresh()->children()->count())->toBe(0)
        ->and(app(ListGuardianChildrenAction::class)->executePendingForGuardianUserId($parent->id)->first()->verification_status)->toBe('rejected');
});

it('gives the office a queue of pupils whose parent links await a check', function () {
    $office = actingPeopleAdmin();
    $stranger = gateParent();
    $pupil = gatePupil('A555666');
    gatePupil('A666777'); // no self-registered link: not in the queue
    app(RegisterCourseStudentAction::class)->forChild($stranger->id, [
        'first_name' => 'Real', 'last_name' => 'Pupil', 'dob' => '2014-05-05', 'national_id' => 'A555666',
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('people.students.index', ['awaiting_verification' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Index')
            ->where('awaitingVerification', 1)
            ->has('students', 1)
            ->where('students.0.id', $pupil->id));

    // The profile's guardians tab shows the link as not checked, for the
    // office to decide there.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('people.students.show', ['student' => $pupil, 'tab' => 'guardians']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('guardians.0.verification_status', 'unverified')
            ->where('guardians.0.verification_label', 'Not checked'));
});

it('is not a gate for the office: the profile lists every link, verified or not', function () {
    $office = actingPeopleAdmin();
    $stranger = gateParent();
    $pupil = gatePupil('A777888');
    app(RegisterCourseStudentAction::class)->forChild($stranger->id, [
        'first_name' => 'Real', 'last_name' => 'Pupil', 'dob' => '2014-05-05', 'national_id' => 'A777888',
    ]);

    expect($pupil->guardians()->count())->toBe(1);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('people.students.show', ['student' => $pupil, 'tab' => 'guardians']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('guardians', 1));
});

it('does not touch an adult who registered themselves, who has no guardian link at all', function () {
    $adult = gateParent();
    Course::factory()->create();

    $me = app(RegisterCourseStudentAction::class)->forSelf($adult->id, [
        'first_name' => 'Adult', 'last_name' => 'Learner', 'dob' => '1990-01-01',
    ]);

    expect(DB::table('guardian_student')->where('student_id', $me['id'])->count())->toBe(0)
        ->and($adult->fresh()->student?->id)->toBe($me['id']);
});
