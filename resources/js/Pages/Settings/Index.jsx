import { router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * System Settings (docs/ADMIN_PANEL.md; C9 slice 1, STATUS §5jb): the
 * application's configuration in one glance, whether SMS and BML are really
 * set up, the cache utility, and the quick links. Every string is a key in
 * the admin tranche, so the screen reads in Dhivehi and Arabic too.
 */
function Card({ icon, label, value, note, tone = 'text-gray-900', testId }) {
    return (
        <div className="flex items-center gap-3 rounded-lg border bg-white p-4" data-testid={testId}>
            <div className="text-2xl" aria-hidden="true">{icon}</div>
            <div className="min-w-0">
                <p className="text-xs text-gray-500">{label}</p>
                <p className={`text-sm font-bold ${tone}`}>{value}</p>
                {note && <p className="text-xs text-gray-400">{note}</p>}
            </div>
        </div>
    );
}

export default function Index({ settings, sms_configured, bml_configured, bml_webhook_ready, configuration_cached, links = [], t = {} }) {
    const { flash = {} } = usePage().props;
    const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : '—');
    const configured = (on) => (on ? t.system_settings_configured || 'Configured' : t.system_settings_not_configured || 'Not configured');
    const tone = (on) => (on ? 'text-green-800' : 'text-amber-800');
    const info = [
        [t.system_settings_app_name || 'App name', settings.app_name],
        [t.system_settings_environment || 'Environment', settings.app_env],
        [t.system_settings_app_url || 'App URL', settings.app_url],
        [t.system_settings_php || 'PHP version', settings.php_version],
        [t.system_settings_laravel || 'Laravel version', settings.laravel_version],
    ];
    const linkLabel = (key) => t[`system_settings_link_${key.replace(/\./g, '_')}`] || key;

    return (
        <AppShell title={t.system_settings_title || 'System Settings'}>
            <p className="mb-4 text-sm text-gray-600">{t.system_settings_intro || 'Application configuration overview and utilities.'}</p>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="settings-flash">✓ {flash.success}</p>}

            <div className="mb-6 grid gap-3 md:grid-cols-2 lg:grid-cols-4" data-testid="integration-status">
                <Card icon="📩" label={t.system_settings_mail || 'Mail driver'} value={cap(settings.mail_mailer)} note={settings.mail_from} />
                <Card icon={sms_configured ? '✅' : '⚠️'} label={t.system_settings_sms || 'SMS gateway'} value={configured(sms_configured)} tone={tone(sms_configured)} note={t.system_settings_sms_note || 'Check .env SMS_GATEWAY_API_KEY'} testId="sms-status" />
                <Card
                    icon={bml_configured ? '✅' : '⚠️'}
                    label={t.system_settings_bml || 'BML payment'}
                    value={configured(bml_configured)}
                    tone={tone(bml_configured)}
                    note={bml_configured && !bml_webhook_ready
                        ? `⚠️ ${t.system_settings_bml_no_webhook || 'No webhook secret — payments will not confirm.'} ${t.system_settings_bml_webhook_note || 'Set .env BML_WEBHOOK_SECRET'}`
                        : t.system_settings_bml_note || 'Check .env BML_API_KEY'}
                    testId="bml-status"
                />
                <Card icon="🗄️" label={t.system_settings_cache || 'Cache / session'} value={`${cap(settings.cache_driver)} / ${cap(settings.session_driver)}`} note={`${t.system_settings_queue || 'Queue'}: ${cap(settings.queue_connection)}`} />
            </div>

            <div className="mb-6 overflow-hidden rounded-lg border bg-white" data-testid="application-info">
                <h2 className="border-b px-4 py-3 text-sm font-semibold">{t.system_settings_application_info || 'Application info'}</h2>
                {info.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-4 border-t px-4 py-2 text-sm">
                        <span className="text-gray-500">{label}</span>
                        <span className="font-medium">{value}</span>
                    </div>
                ))}
            </div>

            <div className="mb-6 rounded-lg border bg-white" data-testid="cache-management">
                <h2 className="border-b px-4 py-3 text-sm font-semibold">{t.system_settings_cache_management || 'Cache management'}</h2>
                <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-4">
                    <p className="text-sm text-gray-600">
                        {t.system_settings_cache_help || 'Clear all caches (application, views, routes, config). Safe to run at any time.'}
                        {configuration_cached && <> {t.system_settings_cache_cached || 'The configuration is cached on this host; it is rebuilt, not left off.'}</>}
                    </p>
                    <button type="button" className="btn-primary" onClick={() => router.post('/admin/settings/clear-cache', {}, { preserveScroll: true })} data-testid="clear-caches">
                        {t.system_settings_clear_caches || 'Clear all caches'}
                    </button>
                </div>
            </div>

            <div className="rounded-lg border bg-white" data-testid="quick-links">
                <h2 className="border-b px-4 py-3 text-sm font-semibold">{t.system_settings_quick_links || 'Quick links'}</h2>
                <div className="flex flex-wrap gap-2 px-4 py-4">
                    {links.map((link) => (
                        <a key={link.key} href={link.href} className="btn-secondary">{linkLabel(link.key)}</a>
                    ))}
                </div>
            </div>
        </AppShell>
    );
}
