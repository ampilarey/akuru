<?php

use App\Domains\Identity\Models\User;
use App\Domains\Settings\Actions\ListFeatureWalkthroughAction;
use App\Domains\Settings\Models\FeatureTestNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * System → Feature testing: the owner's checklist of every main feature
 * (2026-09-28, "tick and comment and later can be seen"). Each mark —
 * works, broken or blocked, with a comment — is kept, so a feature's
 * history stays readable after it is marked again.
 */
it('lists every feature untested, keeps each mark as history, and shows the latest', function () {
    $admin = actingSystemAdmin(['operations.manage']);
    $total = count(ListFeatureWalkthroughAction::itemKeys());
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($admin);

    $as()->get(route('admin.operations.features'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Features')
            ->where('total', $total)
            ->where('counts.untested', $total)
            ->where('t.ft_title', 'Feature testing')
            ->where('sections.0.items.0.key', 'ft-signin-1'));

    $as()->post(route('admin.operations.features.record', 'ft-signin-1'), ['status' => 'broken', 'comment' => 'The OTP SMS never arrived.'])
        ->assertRedirect()->assertSessionHas('success');
    $as()->post(route('admin.operations.features.record', 'ft-signin-1'), ['status' => 'works', 'comment' => 'Fixed after the SMS key was set.'])
        ->assertSessionHasNoErrors();

    $as()->get(route('admin.operations.features'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.ft-signin-1.status', 'works')
            ->where('results.ft-signin-1.by', $admin->name)
            ->where('counts.works', 1)
            ->where('counts.untested', $total - 1)
            ->has('history.ft-signin-1', 2)
            ->where('history.ft-signin-1.1.status', 'broken')
            ->where('history.ft-signin-1.1.comment', 'The OTP SMS never arrived.'));

    $csv = $as()->get(route('admin.operations.features.export'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('ft-signin-1')->toContain('works')->toContain('Fixed after the SMS key was set.');
});

it('asks for a comment when a feature is broken or blocked, and refuses an unknown feature or status', function () {
    $admin = actingSystemAdmin(['operations.manage']);
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($admin);

    $as()->post(route('admin.operations.features.record', 'ft-signin-1'), ['status' => 'broken', 'comment' => ''])->assertSessionHasErrors('comment');
    $as()->post(route('admin.operations.features.record', 'ft-signin-1'), ['status' => 'maybe'])->assertSessionHasErrors('status');
    $as()->post(route('admin.operations.features.record', 'nope'), ['status' => 'works'])->assertSessionHasErrors('item');
    // "Works" needs no comment.
    $as()->post(route('admin.operations.features.record', 'ft-signin-2'), ['status' => 'works'])->assertSessionHasNoErrors();

    expect(FeatureTestNote::query()->count())->toBe(1);
});

it('is the System admin\'s: nobody else may read or mark it', function () {
    $user = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('admin.operations.features'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($user)->post(route('admin.operations.features.record', 'ft-signin-1'), ['status' => 'works'])->assertForbidden();
    expect(FeatureTestNote::query()->count())->toBe(0);
});
