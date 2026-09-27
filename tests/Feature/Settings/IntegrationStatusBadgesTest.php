<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The admin settings screen shows a green "Configured" or amber "Not
 * Configured" badge for SMS and BML. Both read keys that could not answer the
 * question, and both were wrong — in opposite directions.
 *
 *   - **SMS was always green.** It checked `services.sms_gateway.url`, which
 *     carries a non-empty default (`https://akuru.edu.mv/api/v2`), so
 *     `! empty()` could never be false. An operator saw "Configured" with no
 *     API key at all. This is the dangerous direction: this school texts
 *     families when a child is absent, and false reassurance means nobody goes
 *     looking when those texts silently fail.
 *   - **BML was always amber on a deployment.** It checked
 *     `services.bml.api_key`, which does not exist — BML config lives in
 *     `config/bml.php` — then fell through to `env('BML_API_KEY')`. Both deploy
 *     scripts run `config:cache`, after which Laravel never loads `.env`, so
 *     `env()` returns null. It read correctly on a developer machine, which is
 *     exactly why it survived.
 *
 * Each badge now reads what the code it describes actually requires:
 * `SmsGatewayService` needs the Dhiraagu credentials or the gateway api_key,
 * and `BmlPaymentProvider::initiate` needs `bml.api_key` and `bml.base_url`.
 *
 * Since C9 slice 1 (STATUS §5jb) the screen is an Inertia page, so the badges
 * are props: `sms_configured`, `bml_configured`, `bml_webhook_ready`.
 */
function settingsPage(array $config): \Illuminate\Testing\TestResponse
{
    config($config);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return test()->withoutLocalizationMiddleware()->actingAs($admin->fresh())
        ->get(route('admin.settings.index'));
}

it('does not call SMS configured when only the defaulted url is set', function () {
    // The regression. url is non-empty by default; api_key is what matters.
    settingsPage([
        'services.sms_gateway.url' => 'https://akuru.edu.mv/api/v2',
        'services.sms_gateway.api_key' => '',
        'services.dhiraagu.enabled' => false,
    ])->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/Index')->where('sms_configured', false));
});

it('calls SMS configured with a gateway key, or with Dhiraagu credentials', function () {
    settingsPage([
        'services.sms_gateway.api_key' => 'a-real-key',
        'services.dhiraagu.enabled' => false,
    ])->assertOk()->assertInertia(fn (Assert $page) => $page->where('sms_configured', true));

    // The other path SmsGatewayService can take.
    settingsPage([
        'services.sms_gateway.api_key' => '',
        'services.dhiraagu.enabled' => true,
        'services.dhiraagu.username' => 'akuru',
        'services.dhiraagu.password' => null,
    ])->assertOk()->assertInertia(fn (Assert $page) => $page->where('sms_configured', true));

    // Enabled but with no credentials is not configured.
    settingsPage([
        'services.sms_gateway.api_key' => '',
        'services.dhiraagu.enabled' => true,
        'services.dhiraagu.username' => null,
        'services.dhiraagu.password' => null,
    ])->assertOk()->assertInertia(fn (Assert $page) => $page->where('sms_configured', false));
});

it('reads the BML key from config/bml.php rather than a key that does not exist', function () {
    // config:cache makes env() null on a deployment, so the badge must come
    // from config alone. services.bml.* is deliberately left unset here: if
    // anything still reads it, this fails.
    settingsPage([
        'bml.api_key' => 'a-real-key',
        'bml.base_url' => 'https://api.example.mv',
    ])->assertOk()->assertInertia(fn (Assert $page) => $page->where('bml_configured', true));

    settingsPage([
        'bml.api_key' => null,
        'bml.base_url' => 'https://api.example.mv',
    ])->assertOk()->assertInertia(fn (Assert $page) => $page->where('bml_configured', false));
});

it('warns when BML can take a payment but never confirm one', function () {
    // The trap the webhook fix (§5bp) creates: an api_key is enough to send a
    // family to the payment page, but with no webhook secret the payment can
    // never be confirmed, so they pay and get nothing. The page says so in
    // every language the panel speaks.
    settingsPage([
        'bml.api_key' => 'a-real-key',
        'bml.base_url' => 'https://api.example.mv',
        'bml.webhook_secret' => null,
        'bml.webhook_allow_unsigned' => false,
    ])->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('bml_configured', true)
        ->where('bml_webhook_ready', false)
        ->where('t.system_settings_bml_no_webhook', 'No webhook secret — payments will not confirm.'));

    settingsPage([
        'bml.api_key' => 'a-real-key',
        'bml.base_url' => 'https://api.example.mv',
        'bml.webhook_secret' => 'a-secret',
    ])->assertOk()->assertInertia(fn (Assert $page) => $page->where('bml_webhook_ready', true));

    foreach (['dv', 'ar'] as $locale) {
        expect(trans('admin.system_settings_bml_no_webhook', [], $locale))->not->toBe('No webhook secret — payments will not confirm.');
    }
});

it('carries the application info and the quick links that exist', function () {
    settingsPage([])->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('settings.php_version', PHP_VERSION)
        ->has('settings.laravel_version')
        ->has('configuration_cached')
        ->has('links', fn (Assert $links) => $links->each(fn (Assert $link) => $link->hasAll(['key', 'href']))));
});

it('falls back to the config default for tardies per absence', function () {
    // The fourth attendance setting had no config entry, so it was the only
    // one a school could not set globally.
    config(['academics.attendance_tardies_per_absence' => 3]);

    expect(config()->has('academics.attendance_tardies_per_absence'))->toBeTrue()
        ->and(app(\App\Domains\Academics\Actions\ResolveAttendanceSettingsAction::class)->execute()['tardies_per_absence'])
        ->toBe(3);
});
