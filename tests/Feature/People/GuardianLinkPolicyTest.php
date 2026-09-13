<?php

use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\RecordGuardianLinkPolicyAction;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Models\ParentGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §9 "Parent-Child Relationship" lists nine things the `guardian_student`
 * pivot must support:
 *
 *   > Multiple children per guardian · Multiple guardians per child ·
 *   > Relationship type · **Consent status** · **Verification status** ·
 *   > **`verified_at`** · **`created_by`** · **Notes**
 *
 * The table has every one of them — migration `1A.7` added the last five. But
 * `AttachGuardianAction` wrote four columns (relationship plus the
 * primary/pickup/financial flags) and left the rest at their defaults, and no
 * other code path touched them. So **every link in the database has read
 * `unknown` / `unverified` since the table shipped**, with `verified_at`,
 * `created_by` and `notes` NULL on every row ever created.
 *
 * A field that can only ever hold its default is supported in name only. This
 * is the same taxonomy as §36's `submission_kind` and §23's `suspended`: a
 * column the schema offers and the code cannot reach.
 *
 * §9 also names five example relationship types — "Father · Mother · Guardian ·
 * **Sponsor** · Other" — and the enum had every one but sponsor.
 *
 * **Verification is a record, not a gate.** Nothing here filters
 * `/portal/children` on `verification_status`, and the test below pins that:
 * every existing link is `unverified`, so enforcing it would hide every child
 * from every parent overnight.
 */
uses(RefreshDatabase::class);

function policyStudent(): object
{
    return makeStudent(['first_name' => 'Link', 'last_name' => 'Child']);
}

function policyGuardian(): ParentGuardian
{
    // `makeGuardian()` already builds a valid row, user included — several
    // columns on `parent_guardians` are NOT NULL with no default.
    return makeGuardian();
}

it('records who created the link, which nothing ever wrote', function () {
    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();

    app(AttachGuardianAction::class)->execute(
        $student, $guardian, GuardianRelationship::Mother,
        actorId: $admin->id,
    );

    $pivot = DB::table('guardian_student')
        ->where('student_id', $student->id)->where('guardian_id', $guardian->id)->first();

    expect((int) $pivot->created_by)->toBe($admin->id);
    // The honest starting state for a link somebody has only just made.
    expect($pivot->consent_status)->toBe('unknown');
    expect($pivot->verification_status)->toBe('unverified');
});

it('lets staff record consent and verification, and stamps verified_at with it', function () {
    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Father, actorId: $admin->id);

    $result = app(RecordGuardianLinkPolicyAction::class)->execute($student, $guardian, [
        'consent_status' => 'granted',
        'verification_status' => 'verified',
        'notes' => 'ID card seen at the office.',
    ], $admin->id);

    expect($result['consent_status'])->toBe('granted');
    expect($result['verification_status'])->toBe('verified');
    expect($result['notes'])->toBe('ID card seen at the office.');
    // §9 pairs the status with `verified_at`.
    expect($result['verified_at'])->not->toBeNull();
});

it('clears verified_at when a link goes back to unchecked', function () {
    // A timestamp left behind would claim somebody verified a link that is no
    // longer verified, which is worse than no timestamp at all.
    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Father, actorId: $admin->id);

    $policy = app(RecordGuardianLinkPolicyAction::class);
    $policy->execute($student, $guardian, ['verification_status' => 'verified'], $admin->id);
    $after = $policy->execute($student, $guardian, ['verification_status' => 'unverified'], $admin->id);

    expect($after['verified_at'])->toBeNull();
});

it('never overwrites the original creator', function () {
    // `created_by` is who built the link. A later editor is not that person.
    $creator = actingPeopleAdmin();
    $editor = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Guardian, actorId: $creator->id);

    $result = app(RecordGuardianLinkPolicyAction::class)
        ->execute($student, $guardian, ['consent_status' => 'refused'], $editor->id);

    expect($result['created_by'])->toBe($creator->id);
});

it('refuses a status it cannot store', function () {
    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Other, actorId: $admin->id);

    app(RecordGuardianLinkPolicyAction::class)
        ->execute($student, $guardian, ['consent_status' => 'maybe'], $admin->id);
})->throws(\Illuminate\Validation\ValidationException::class);

it('refuses a guardian who is not linked to this student', function () {
    $admin = actingPeopleAdmin();

    app(RecordGuardianLinkPolicyAction::class)
        ->execute(policyStudent(), policyGuardian(), ['consent_status' => 'granted'], $admin->id);
})->throws(\Illuminate\Validation\ValidationException::class);

it('carries §9 sponsor, which the enum was missing', function () {
    // §9 names it explicitly. A sponsor pays without standing in a parent's
    // place, so folding it into `other` loses the one fact it is looked up for.
    expect(GuardianRelationship::tryFrom('sponsor'))->toBe(GuardianRelationship::Sponsor);

    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'sponsor', actorId: $admin->id);

    expect(DB::table('guardian_student')
        ->where('student_id', $student->id)->value('relationship'))->toBe('sponsor');
});

it('shows the four §9 fields on the guardians tab', function () {
    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Mother, actorId: $admin->id);
    app(RecordGuardianLinkPolicyAction::class)
        ->execute($student, $guardian, ['consent_status' => 'granted', 'verification_status' => 'verified'], $admin->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/people/students/'.$student->id.'?tab=guardians')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Show')
            ->where('guardians.0.consent_status', 'granted')
            ->where('guardians.0.verification_status', 'verified')
            ->where('guardians.0.verification_label', 'Verified')
            ->has('consentStatuses', 4)
            ->has('verificationStatuses', 3));
});

it('saves the record over HTTP', function () {
    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Mother, actorId: $admin->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->put('/people/students/'.$student->id.'/guardians/'.$guardian->id, [
            'consent_status' => 'withdrawn',
            'verification_status' => 'rejected',
            'notes' => 'Could not confirm.',
        ])
        ->assertRedirect();

    $pivot = DB::table('guardian_student')
        ->where('student_id', $student->id)->where('guardian_id', $guardian->id)->first();

    expect($pivot->consent_status)->toBe('withdrawn');
    expect($pivot->verification_status)->toBe('rejected');
    expect($pivot->notes)->toBe('Could not confirm.');
    expect($pivot->verified_at)->toBeNull();
});

it('does not let verification gate the parent portal', function () {
    // Deliberate, and pinned. Every link in the database is `unverified`
    // because nothing could ever set it, so filtering the portal on this
    // column would hide every child from every parent overnight. Turning it
    // into an access rule is a separate decision with its own backfill.
    $admin = actingPeopleAdmin();
    $student = policyStudent();
    $guardian = policyGuardian();
    // makeGuardian() already attaches a user.
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Mother, actorId: $admin->id);

    $children = app(\App\Domains\People\Actions\ListGuardianChildrenAction::class)
        ->executeForGuardianUserId((int) $guardian->user_id);

    expect($children)->toHaveCount(1);
});
