<?php

use App\Domains\Identity\Models\User;
use App\Domains\Settings\Models\OperatorCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an operator tick and untick shared checklist items with attribution', function () {
    $admin = actingPeopleAdmin(['operations.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.operations.index'))
        ->assertOk();

    // Tick — the row records who and when, and the page shows it to everyone.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.operations.toggle', 'w1'))
        ->assertSessionHasNoErrors();
    $check = OperatorCheck::query()->where('item_key', 'w1')->firstOrFail();
    expect((int) $check->checked_by)->toBe((int) $admin->id)
        ->and($check->checked_at)->not->toBeNull();

    // Untick removes the row (progress tracking, not a ledger).
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.operations.toggle', 'w1'))
        ->assertSessionHasNoErrors();
    expect(OperatorCheck::query()->where('item_key', 'w1')->exists())->toBeFalse();

    // Unknown keys are refused.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.operations.toggle', 'nope'))
        ->assertSessionHasErrors('item');

    // CSV export follows the every-listing-exports convention.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.operations.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('serves the feature walkthrough with the same shared tick store', function () {
    $admin = actingPeopleAdmin(['operations.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.operations.features'))
        ->assertOk();

    // Feature keys share the store; ops and feature pages count separately.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.operations.toggle', 'fw-pub1'))
        ->assertSessionHasNoErrors();
    expect(OperatorCheck::query()->where('item_key', 'fw-pub1')->exists())->toBeTrue();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.operations.features.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('forbids the checklist without operations.manage', function () {
    $user = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('admin.operations.index'))
        ->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('admin.operations.toggle', 'w1'))
        ->assertForbidden();
});
