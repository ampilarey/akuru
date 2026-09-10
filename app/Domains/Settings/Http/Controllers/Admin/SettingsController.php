<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'mail_mailer' => config('mail.default'),
            'mail_from' => config('mail.from.address'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_connection' => config('queue.default'),
        ];

        // SMS gateway configured?
        // Both badges used to read keys that could not answer the question.
        //
        // SMS checked `sms_gateway.url`, which carries a non-empty default, so
        // it was **always** green — an operator saw "Configured" with no API
        // key at all, and absence texts to families would have failed silently.
        // What SmsGatewayService actually needs is the Dhiraagu credentials or
        // the gateway api_key.
        //
        // BML checked `services.bml.api_key`, which does not exist — BML config
        // lives in config/bml.php — so it fell through to `env()`. Both deploy
        // scripts run `config:cache`, and Laravel then never loads .env, so
        // `env()` returns null and the badge was **always** amber on a
        // deployment. It read green locally, which is why it survived.
        $smsConfigured = ! empty(config('services.sms_gateway.api_key'))
            || (config('services.dhiraagu.enabled', false)
                && (! empty(config('services.dhiraagu.username')) || ! empty(config('services.dhiraagu.password'))));

        // What BmlPaymentProvider requires to initiate a payment at all.
        $bmlConfigured = ! empty(config('bml.api_key')) && ! empty(config('bml.base_url'));

        // Initiating is not confirming. Since the webhook now fails closed,
        // a deployment with an api_key but no webhook secret takes money and
        // never grants access — worth saying out loud on this screen.
        $bmlWebhookReady = ! empty(config('bml.webhook_secret'))
            || (bool) config('bml.webhook_allow_unsigned', false);

        return view('admin.settings.index', compact('settings', 'smsConfigured', 'bmlConfigured', 'bmlWebhookReady'));
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');

        return back()->with('success', 'All caches cleared successfully.');
    }
}
