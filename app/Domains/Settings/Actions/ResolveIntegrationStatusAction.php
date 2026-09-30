<?php

namespace App\Domains\Settings\Actions;

use Illuminate\Support\Facades\Route;

/**
 * What the System Settings screen reports (docs/ADMIN_PANEL.md; C9 slice 1,
 * STATUS §5jb): the application's configuration in one glance, and whether
 * the two integrations that touch families and money are really set up.
 *
 * Both badges used to read keys that could not answer the question. SMS
 * checked `sms_gateway.url`, which carries a non-empty default, so it was
 * ALWAYS green — an operator saw "Configured" with no API key at all, and
 * absence texts to families would have failed silently. BML checked
 * `services.bml.api_key`, which does not exist (BML config lives in
 * config/bml.php), so it fell through to `env()`, which is null once
 * `config:cache` has run — ALWAYS amber on a deployment, green locally,
 * which is why it survived. Each badge now reads what the code it describes
 * actually requires (`IntegrationStatusBadgesTest`).
 */
class ResolveIntegrationStatusAction
{
    /**
     * The screen's quick links, by the route that must exist for each.
     *
     * @var array<string, string>
     */
    private const LINKS = [
        'admin.users.index' => '/admin/users',
        'admin.enrollments.index' => '/admin/enrollments',
        'admin.courses.index' => '/admin/courses',
        'analytics.index' => '/analytics',
        'analytics.reports' => '/analytics/reports',
        'admin.pages.index' => '/admin/public-site/pages',
    ];

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        // What SmsGatewayService actually needs: the Dhiraagu credentials or
        // the gateway api_key.
        $smsConfigured = ! empty(config('services.sms_gateway.api_key'))
            || (config('services.dhiraagu.enabled', false)
                && (! empty(config('services.dhiraagu.username')) || ! empty(config('services.dhiraagu.password'))));

        // What BmlPaymentProvider requires to initiate a payment at all.
        $bmlConfigured = ! empty(config('bml.api_key')) && ! empty(config('bml.base_url'));

        // Initiating is not confirming. Since the webhook fails closed, a
        // deployment with an api_key but no webhook secret takes money and
        // never grants access — worth saying out loud on this screen.
        // §5lw: BML signs with the API key, so a configured key is enough.
        $bmlWebhookReady = ! empty(config('bml.webhook_secret'))
            || ! empty(config('bml.api_key'))
            || (bool) config('bml.webhook_allow_unsigned', false);

        $links = [];
        foreach (self::LINKS as $route => $href) {
            if (Route::has($route)) {
                $links[] = ['key' => $route, 'href' => $href];
            }
        }

        return [
            'settings' => [
                'app_name' => (string) config('app.name'),
                'app_env' => (string) config('app.env'),
                'app_url' => (string) config('app.url'),
                'mail_mailer' => (string) config('mail.default'),
                'mail_from' => (string) config('mail.from.address'),
                'cache_driver' => (string) config('cache.default'),
                'session_driver' => (string) config('session.driver'),
                'queue_connection' => (string) config('queue.default'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'sms_configured' => $smsConfigured,
            'bml_configured' => $bmlConfigured,
            'bml_webhook_ready' => $bmlWebhookReady,
            'configuration_cached' => app()->configurationIsCached(),
            'links' => $links,
        ];
    }
}
