<?php

use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\RecordConsentAction;
use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Enums\ConsentSource;
use App\Domains\People\Enums\ConsentType;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Models\Consent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('records consent as a new row and never updates granted in place', function () {
    $actor = actingPeopleAdmin();
    $student = makeStudent();

    $first = app(RecordConsentAction::class)->execute(
        ConsentPersonType::Student,
        $student->id,
        ConsentType::PhotoMediaUse,
        true,
        $actor->id,
        ConsentSource::Admin,
    );

    $second = app(RecordConsentAction::class)->execute(
        ConsentPersonType::Student,
        $student->id,
        ConsentType::PhotoMediaUse,
        false,
        $actor->id,
        ConsentSource::Portal,
    );

    expect($second->id)->not->toBe($first->id)
        ->and($first->fresh()->granted)->toBeTrue()
        ->and($first->fresh()->revoked_at)->toBeNull()
        ->and($second->granted)->toBeFalse()
        ->and($second->revoked_at)->not->toBeNull()
        ->and(Consent::query()->where('person_id', $student->id)->count())->toBe(2);

    $unchanged = app(RecordConsentAction::class)->execute(
        ConsentPersonType::Student,
        $student->id,
        ConsentType::PhotoMediaUse,
        false,
        $actor->id,
        ConsentSource::Portal,
    );

    expect($unchanged->id)->toBe($second->id)
        ->and(Consent::query()->where('person_id', $student->id)->count())->toBe(2);
});

it('lets a guardian see only their own children in the portal', function () {
    $mine = makeStudent(['first_name' => 'Mine']);
    $theirs = makeStudent(['first_name' => 'Theirs']);
    $myGuardian = makeGuardian();
    $otherGuardian = makeGuardian();

    app(AttachGuardianAction::class)->execute($mine, $myGuardian, GuardianRelationship::Father, true);
    app(AttachGuardianAction::class)->execute($theirs, $otherGuardian, GuardianRelationship::Mother, true);

    $this->withoutLocalizationMiddleware()
        ->actingAs($myGuardian->user)
        ->get(route('portal.children'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Children')
            ->has('children', 1)
            ->where('children.0.id', $mine->id)
        );
});
