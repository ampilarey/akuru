import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * System Settings: whether mail, SMS and payments are set up, the cache
 * utility, and the analytics screens (their only door). Server details sit
 * in one closed line. Secrets stay in the environment file.
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

/**
 * STATUS §5su: Dhivehi and Arabic each switch on or off; English always on.
 * The names are each language's own, as in the language menu.
 */
const NATIVE = { dv: 'ދިވެހި', ar: 'العربية' };

function Languages({ languages, t }) {
    const form = useForm({ ...languages });

    return (
        <form
            className="mb-6 rounded-lg border bg-white"
            data-testid="languages"
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/languages', { preserveScroll: true });
            }}
        >
            <h2 className="border-b px-4 py-3 text-sm font-semibold">{t.system_settings_languages || 'Languages'}</h2>
            <div className="space-y-3 px-4 py-4">
                <p className="text-sm text-gray-600">{t.system_settings_languages_help || 'English is always on. A language switched off leaves the menus and its addresses open in English.'}</p>
                <p className="text-sm text-gray-500">{t.system_settings_language_en_always || 'English — always on'}</p>
                {Object.keys(languages).map((code) => (
                    <label key={code} className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={!!form.data[code]} onChange={(e) => form.setData(code, e.target.checked)} data-testid={`language-${code}`} />
                        <span lang={code} dir="auto" className="font-medium">{NATIVE[code] || code}</span>
                        <span className="text-xs text-gray-500">— {t.system_settings_language_on || 'On'}</span>
                        {form.errors[code] && <span className="text-xs text-red-700" role="alert">{form.errors[code]}</span>}
                    </label>
                ))}
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="languages-save">
                    {t.system_settings_languages_save || 'Save languages'}
                </button>
            </div>
        </form>
    );
}

const fillIn = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');

/** BK2 (STATUS §5ur): the nightly backups — the last good one, and red when it is too long ago. */
function Backups({ backup, t }) {
    if (!backup) return null;
    const good = backup.last_good;
    const failed = backup.last && backup.last.status !== 'succeeded' && backup.last.status !== 'running';
    return (
        <section className={`mb-6 rounded-lg border p-4 ${backup.stale ? 'border-red-300 bg-red-50' : 'bg-white'}`} data-testid="backup-status" data-stale={backup.stale ? 'yes' : 'no'}>
            <h2 className="text-sm font-semibold">{t.backup_heading}</h2>
            {!backup.enabled ? <p className="mt-1 text-sm text-amber-800">{t.backup_off}</p> : (
                <p className={`mt-1 text-sm ${backup.stale ? 'font-semibold text-red-800' : 'text-gray-800'}`} role={backup.stale ? 'alert' : undefined} data-testid="backup-last-good">
                    {good ? fillIn(t.backup_last_good, { at: good.at, size: good.size_mb }) : t.backup_never}
                    {backup.stale && good && <> {fillIn(t.backup_stale, { hours: 26 })}</>}
                </p>
            )}
            {good && <p className="mt-1 text-sm text-gray-600">{good.offsite ? t.backup_where_both : t.backup_where_here}</p>}
            {failed && <p className="mt-1 text-sm text-red-800" data-testid="backup-last-failed">{fillIn(t.backup_last_failed, { at: backup.last.at, why: backup.last.error || '—' })}</p>}
            {!backup.offsite_configured && <p className="mt-1 text-sm text-amber-800" data-testid="backup-no-offsite">{t.backup_no_offsite}</p>}
            {backup.uses_app_key && <p className="mt-1 text-sm text-amber-800" data-testid="backup-app-key">{t.backup_app_key}</p>}
        </section>
    );
}

export default function Index({ settings, sms_configured, bml_configured, configuration_cached, routes_cached = false, backup = null, languages = {}, links = [], t = {} }) {
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
            <p className="mb-4 text-sm text-gray-600">{t.system_settings_intro || 'Whether mail, text messages, payments and the cache are set up.'}</p>
            <div className="mb-6 grid gap-3 md:grid-cols-2 lg:grid-cols-4" data-testid="integration-status">
                <Card icon="📩" label={t.system_settings_mail || 'Mail driver'} value={cap(settings.mail_mailer)} note={settings.mail_from} />
                <Card icon={sms_configured ? '✅' : '⚠️'} label={t.system_settings_sms || 'SMS gateway'} value={configured(sms_configured)} tone={tone(sms_configured)} note={t.system_settings_sms_note || 'Dhiraagu username and password, or SMS_GATEWAY_API_KEY.'} testId="sms-status" />
                <Card
                    icon={bml_configured ? '✅' : '⚠️'}
                    label={t.system_settings_bml || 'BML payment'}
                    value={configured(bml_configured)}
                    tone={tone(bml_configured)}
                    note={t.system_settings_bml_note || 'Check .env BML_API_KEY'}
                    testId="bml-status"
                />
                <Card icon="🗄️" label={t.system_settings_cache || 'Cache / session'} value={`${cap(settings.cache_driver)} / ${cap(settings.session_driver)}`} note={`${t.system_settings_queue || 'Queue'}: ${cap(settings.queue_connection)}`} />
            </div>

            <Backups backup={backup} t={t} />

            <details className="mb-6 rounded-lg border bg-white" data-testid="application-info">
                <summary className="cursor-pointer px-4 py-3 text-sm font-semibold">{t.system_settings_about || 'About this server'}</summary>
                {info.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-4 border-t px-4 py-2 text-sm">
                        <span className="text-gray-500">{label}</span>
                        <span className="font-medium">{value}</span>
                    </div>
                ))}
            </details>

            <Languages languages={languages} t={t} />

            {routes_cached && <p className="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert" data-testid="routes-cached">{t.system_settings_routes_cached}</p>}
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
