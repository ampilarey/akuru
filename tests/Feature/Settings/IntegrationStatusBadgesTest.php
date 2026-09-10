<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $page = settingsPage([
        'services.sms_gateway.url' => 'https://akuru.edu.mv/api/v2',
        'services.sms_gateway.api_key' => '',
        'services.dhiraagu.enabled' => false,
    ]);

    $page->assertOk()->assertViewHas('smsConfigured', false);
});

it('calls SMS configured with a gateway key, or with Dhiraagu credentials', function () {
    settingsPage([
        'services.sms_gateway.api_key' => 'a-real-key',
        'services.dhiraagu.enabled' => false,
    ])->assertOk()->assertViewHas('smsConfigured', true);

    // The other path SmsGatewayService can take.
    settingsPage([
        'services.sms_gateway.api_key' => '',
        'services.dhiraagu.enabled' => true,
        'services.dhiraagu.username' => 'akuru',
        'services.dhiraagu.password' => null,
    ])->assertOk()->assertViewHas('smsConfigured', true);

    // Enabled but with no credentials is not configured.
    settingsPage([
        'services.sms_gateway.api_key' => '',
        'services.dhiraagu.enabled' => true,
        'services.dhiraagu.username' => null,
        'services.dhiraagu.password' => null,
    ])->assertOk()->assertViewHas('smsConfigured', false);
});

it('reads the BML key from config/bml.php rather than a key that does not exist', function () {
    // config:cache makes env() null on a deployment, so the badge must come
    // from config alone. services.bml.* is deliberately left unset here: if
    // anything still reads it, this fails.
    settingsPage([
        'bml.api_key' => 'a-real-key',
        'bml.base_url' => 'https://api.example.mv',
    ])->assertOk()->assertViewHas('bmlConfigured', true);

    settingsPage([
        'bml.api_key' => null,
        'bml.base_url' => 'https://api.example.mv',
    ])->assertOk()->assertViewHas('bmlConfigured', false);
});

it('warns when BML can take a payment but never confirm one', function () {
    // The trap the webhook fix (§5bp) creates: an api_key is enough to send a
    // family to the payment page, but with no webhook secret the payment can
    // never be confirmed, so they pay and get nothing.
    settingsPage([
        'bml.api_key' => 'a-real-key',
        'bml.base_url' => 'https://api.example.mv',
        'bml.webhook_secret' => null,
        'bml.webhook_allow_unsigned' => false,
    ])->assertOk()
        ->assertViewHas('bmlWebhookReady', false)
        ->assertSee('payments will not confirm', false);

    settingsPage([
        'bml.api_key' => 'a-real-key',
        'bml.base_url' => 'https://api.example.mv',
        'bml.webhook_secret' => 'a-secret',
    ])->assertOk()
        ->assertViewHas('bmlWebhookReady', true)
        ->assertDontSee('payments will not confirm', false);
});

it('falls back to the config default for tardies per absence', function () {
    // The fourth attendance setting had no config entry, so it was the only
    // one a school could not set globally.
    config(['academics.attendance_tardies_per_absence' => 3]);

    expect(config()->has('academics.attendance_tardies_per_absence'))->toBeTrue()
        ->and(app(\App\Domains\Academics\Actions\ResolveAttendanceSettingsAction::class)->execute()['tardies_per_absence'])
        ->toBe(3);
});
