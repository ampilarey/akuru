<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\RecordGuardianLinkPolicyAction;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use App\Support\Navigation\ResolveWorkspacesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * docs/SIGN_IN_PLAN.md ID2c (finding F14). A parent who registers their
 * children on the website is linked to them as a guardian — unverified, by
 * design (STATUS §5gk) — and nothing ever made them a `parent`, so they held
 * no Family workspace. The office verifying the link now grants the role;
 * registering alone does not, because the role reads the notices the
 * school sends families.
 */
function websiteParent(): array
{
    $user = User::factory()->create();
    $child = app(RegisterCourseStudentAction::class)->forChild((int) $user->id, [
        'first_name' => 'Maryam', 'last_name' => 'Rasheed', 'dob' => '2016-04-01', 'national_id' => 'A123456',
    ], 'mother');

    return [$user, Student::query()->findOrFail($child['id']), ParentGuardian::query()->where('user_id', $user->id)->firstOrFail()];
}

it('makes a website parent a parent when the office verifies the link, and not before', function () {
    [$user, $child, $guardian] = websiteParent();

    // Registered, awaiting the office: no role, no Family.
    expect($user->fresh()->hasRole('parent'))->toBeFalse()
        ->and(array_column(app(ResolveWorkspacesAction::class)->execute($user->fresh())['list'], 'key'))->not->toContain('family');

    $office = User::factory()->create();
    app(RecordGuardianLinkPolicyAction::class)->execute($child, $guardian, ['verification_status' => 'verified'], (int) $office->id);

    $parent = $user->fresh();
    expect($parent->hasRole('parent'))->toBeTrue()
        ->and(array_column(app(ResolveWorkspacesAction::class)->execute($parent)['list'], 'key'))->toBe(['family']);
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('dashboard'))->assertRedirect(route('portal.home'));
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('title', 'Parent Dashboard')->where('students.0.name', 'Maryam Rasheed'));

    // Setting the link back closes the child's records (the gate) but leaves
    // the role, which only the role screen takes away.
    app(RecordGuardianLinkPolicyAction::class)->execute($child, $guardian, ['verification_status' => 'unverified'], (int) $office->id);
    expect($user->fresh()->hasRole('parent'))->toBeTrue();
    $this->withoutLocalizationMiddleware()->actingAs($user->fresh())->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('students', 0));
});

it('makes the login of a guardian the office attaches a parent, verified as it goes', function () {
    $guardian = makeGuardian();
    $student = makeStudent();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Father, true);

    expect(User::query()->findOrFail($guardian->user_id)->hasRole('parent'))->toBeTrue();

    // A second child verified for the same guardian grants nothing twice.
    app(AttachGuardianAction::class)->execute(makeStudent(), $guardian, GuardianRelationship::Father);
    expect(DB::table('model_has_roles')->where('model_id', $guardian->user_id)->count())->toBe(1);
});

it('backfills parent for every login that already holds a verified link, and nobody else', function () {
    [$verified, $child, $guardian] = websiteParent();
    DB::table('guardian_student')->where('guardian_id', $guardian->id)->update(['verification_status' => 'verified']);
    [$awaiting] = websiteParent();
    expect($verified->fresh()->hasRole('parent'))->toBeFalse();

    (include database_path('migrations/2026_09_28_000001_grant_parent_to_verified_guardians.php'))->up();

    expect($verified->fresh()->hasRole('parent'))->toBeTrue()
        ->and($awaiting->fresh()->hasRole('parent'))->toBeFalse();
    // Running it again changes nothing.
    (include database_path('migrations/2026_09_28_000001_grant_parent_to_verified_guardians.php'))->up();
    expect(DB::table('model_has_roles')->where('model_id', $verified->id)->count())->toBe(1);
});
