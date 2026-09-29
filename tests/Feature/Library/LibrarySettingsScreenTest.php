<?php

use App\Domains\Library\Actions\ListWriterEarningsSummaryAction;
use App\Domains\Library\Actions\ResolveLibrarySettingAction;
use App\Domains\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B12 (LIBRARY_PLAN §42, STATUS §5ip): the Library's money rules on a
 * screen, in force at once, with the deploy's config as the default.
 */
function settingsAs($user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('shows the deploy defaults until the office saves, then what the office saved', function () {
    config(['library.refund_window_days' => 7, 'library.default_writer_commission' => 70, 'library.gift_cards.min' => 50]);
    $admin = actingSystemAdmin(['library.manage']);

    settingsAs($admin)->get(route('admin.library.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Library/Settings')
            ->where('settings.refund_window_days.value', 7)
            ->where('settings.refund_window_days.default', 7)
            ->where('settings.payouts_enabled.value', false)
            ->where('t.library_settings_title', 'Digital Library settings'));

    settingsAs($admin)->from(route('admin.library.settings'))
        ->put(route('admin.library.settings.update'), [
            'refund_window_days' => 10, 'default_writer_commission' => 60, 'min_payout' => 200,
            'gift_card_min' => 100, 'gift_card_max' => 2000, 'gift_card_expiry_months' => 12, 'research_reviews_required' => 2, 'payouts_enabled' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.library.settings'))
        ->assertSessionHas('success', 'Library settings saved.');

    $resolve = app(ResolveLibrarySettingAction::class);
    expect($resolve->execute('refund_window_days'))->toBe(10)
        ->and($resolve->execute('default_writer_commission'))->toBe(60)
        ->and($resolve->execute('min_payout'))->toBe(200)
        ->and($resolve->execute('gift_card_min'))->toBe(100)
        ->and($resolve->execute('research_reviews_required'))->toBe(2)
        ->and(Setting::query()->where('key', 'library.min_payout')->value('group'))->toBe('library');

    // A real reader of a knob sees the office's number, not the deploy's.
    $writer = \App\Domains\Identity\Models\User::factory()->create();
    $application = app(\App\Domains\Library\Actions\ApplyAsWriterAction::class)->execute($writer->id, ['display_name' => 'Ustadha Aminath', 'agreement_accepted' => true]);
    app(\App\Domains\Library\Actions\DecideWriterApplicationAction::class)->execute($application->id, \App\Domains\Identity\Models\User::factory()->create()->id, true);
    expect(app(ListWriterEarningsSummaryAction::class)->execute($writer->id)['min_payout'])->toEqual(200);

    // And the screen reads it back, default still beside it.
    settingsAs($admin)->get(route('admin.library.settings'))
        ->assertInertia(fn ($page) => $page->where('settings.default_writer_commission.value', 60)->where('settings.default_writer_commission.default', 70));
});

it('refuses a share over a hundred and a gift-card floor above its ceiling', function () {
    $admin = actingSystemAdmin(['library.manage']);
    $base = ['refund_window_days' => 7, 'default_writer_commission' => 70, 'min_payout' => 100, 'gift_card_min' => 50, 'gift_card_max' => 5000, 'gift_card_expiry_months' => 0, 'research_reviews_required' => 1, 'payouts_enabled' => false];

    settingsAs($admin)->put(route('admin.library.settings.update'), ['default_writer_commission' => 150] + $base)
        ->assertSessionHasErrors('default_writer_commission');
    settingsAs($admin)->put(route('admin.library.settings.update'), ['gift_card_min' => 600, 'gift_card_max' => 500] + $base)
        ->assertSessionHasErrors('gift_card_max');
    settingsAs($admin)->put(route('admin.library.settings.update'), ['refund_window_days' => 'soon'] + $base)
        ->assertSessionHasErrors('refund_window_days');
    // R3 (D2): peer review cannot be switched off by asking for no accepts.
    settingsAs($admin)->put(route('admin.library.settings.update'), ['research_reviews_required' => 0] + $base)
        ->assertSessionHasErrors('research_reviews_required');
    expect(fn () => app(\App\Domains\Library\Actions\SaveLibrarySettingsAction::class)->execute(['research_reviews_required' => 0]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(Setting::query()->where('key', 'like', 'library.%')->count())->toBe(0);
});

it('is the system admin\'s, like the rest of the Library office', function () {
    settingsAs(actingPeopleAdmin(['library.manage']))->get(route('admin.library.settings'))->assertForbidden();
    settingsAs(actingPeopleAdmin(['library.manage']))->put(route('admin.library.settings.update'), [])->assertForbidden();
    settingsAs(\App\Domains\Identity\Models\User::factory()->create())->get(route('admin.library.settings'))->assertForbidden();

    // The office's own page links here.
    settingsAs(actingSystemAdmin(['library.manage']))->get(route('admin.library.index'))->assertOk();
});
