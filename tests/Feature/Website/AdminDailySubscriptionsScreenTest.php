<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The daily subscriptions screen (docs/ADMIN_PANEL.md; C9 slice 7, STATUS
 * §5ji): an Inertia page with every string keyed EN/DV/AR, behind
 * `daily_content.manage` as before.
 */
it('renders the subscriber metrics as props with the keyed strings, and keeps its permission gate', function () {
    $super = actingSystemAdmin(['daily_content.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.daily-subscriptions.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/DailySubscriptions')
            ->where('metrics.totals.sms_active', 0)->where('metrics.types.ayah', 0)->where('metrics.failures', [])->where('metrics.rows', [])
            ->where('t.subs_title', 'Daily subscriptions')->where('t.subs_type_hadith', 'Hadith'));

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['subs_title', 'subs_intro', 'subs_active', 'subs_failures', 'subs_none'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // The website is the system admin's (ADR-040 slice 2): the educational admin is refused at the route.
    $admin = \App\Domains\Identity\Models\User::factory()->create();
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.daily-subscriptions.index'))->assertForbidden();
});
