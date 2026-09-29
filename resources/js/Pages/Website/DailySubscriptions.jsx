import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Daily content subscribers (W24; docs/ADMIN_PANEL.md; C9 slice 7, STATUS
 * §5ji): how many take the daily ayah, hadith, saying or reminder by SMS,
 * email or push, the delivery failures, and every subscription. Read-only,
 * with a CSV. Every string is a key in the admin tranche.
 */
const CHANNELS = ['sms', 'email', 'push'];
const TYPES = ['ayah', 'hadith', 'saying', 'reminder'];
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

export default function DailySubscriptions({ metrics = { totals: {}, types: {}, failures: [], rows: [] }, t = {} }) {
    const typeLabel = (type) => t[`subs_type_${type}`] || humanize(type);
    const channelLabel = (c) => t[`subs_channel_${c}`] || c.toUpperCase();
    const statusLabel = (s) => t[`subs_status_${s}`] || humanize(s);

    return (
        <AppShell title={t.subs_title || 'Daily subscriptions'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                {/* Daily content, research and prayer times are still Blade: full page loads. Leads is an Inertia visit. */}
                <a href="/admin/public-site/daily-content" className="underline">{t.leads_link_daily || 'Daily content →'}</a>
                <a href="/admin/library" className="underline">{t.subs_link_research || 'Research →'}</a>
                <a href="/admin/prayer-times/islands" className="underline">{t.subs_link_prayer || 'Prayer times →'}</a>
                <Link href="/admin/public-site/leads" className="underline" data-testid="subs-leads-link">{t.funnel_link_leads || 'Leads →'}</Link>
                <a href="/admin/public-site/daily-subscriptions/export" className="ms-auto underline" data-testid="export-csv">{t.subs_export || 'Export CSV'}</a>
            </div>
            <p className="mb-4 text-sm text-gray-600">{t.subs_intro || 'Opt-in only. Push rows are stored but not sent. Empty days are skipped silently so a later publish can still deliver.'}</p>

            <div className="mb-6 grid gap-4 md:grid-cols-3">
                {CHANNELS.map((channel) => (
                    <div key={channel} className="rounded-lg border bg-white p-4" data-metric-channel={channel}>
                        <h2 className="mb-2 text-xs font-semibold uppercase text-gray-500">{channelLabel(channel)}</h2>
                        <p className="text-2xl font-bold text-gray-900">{(t.subs_active || ':count active').replace(':count', metrics.totals[`${channel}_active`] ?? 0)}</p>
                        <p className="text-sm text-gray-500">{(t.subs_paused || ':count paused').replace(':count', metrics.totals[`${channel}_paused`] ?? 0)}</p>
                    </div>
                ))}
            </div>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="subs-types">
                <h2 className="mb-2 text-base font-semibold text-gray-800">{t.subs_per_type || 'Subscribers per type'}</h2>
                <p className="text-sm text-gray-700">{TYPES.map((type) => `${typeLabel(type)} ${metrics.types[type] ?? 0}`).join(' · ')}</p>
            </section>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="subs-failures">
                <h2 className="mb-2 text-base font-semibold text-gray-800">{t.subs_failures || 'Delivery failures'}</h2>
                {metrics.failures.length === 0 && <p className="text-sm text-gray-500">{t.subs_no_failures || 'No delivery failures recorded.'}</p>}
                {metrics.failures.map((f) => (
                    <p key={f.id} data-delivery-failure={f.id} className="mb-1 text-sm text-red-800">#{f.subscription_id} {channelLabel(f.channel)} {f.send_date} — {f.error}</p>
                ))}
            </section>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="subs-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.subs_col_id || 'Id'}</th>
                            <th className="px-3 py-2">{t.subs_col_user || 'User'}</th>
                            <th className="px-3 py-2">{t.subs_col_channel || 'Channel'}</th>
                            <th className="px-3 py-2">{t.subs_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.subs_col_types || 'Types'}</th>
                            <th className="px-3 py-2">{t.subs_col_time || 'Time'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {metrics.rows.length === 0 && <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="6">{t.subs_none || 'No subscribers yet.'}</td></tr>}
                        {metrics.rows.map((row) => (
                            <tr key={row.id} className="border-t" data-testid="subs-row">
                                <td className="px-3 py-2 text-gray-500">{row.id}</td>
                                <td className="px-3 py-2">{row.user_id}</td>
                                <td className="px-3 py-2">{channelLabel(row.channel)}</td>
                                <td className="px-3 py-2">{statusLabel(row.status)}</td>
                                <td className="px-3 py-2">{(row.content_types || []).map(typeLabel).join(', ')}</td>
                                <td className="whitespace-nowrap px-3 py-2" dir="ltr">{row.send_time}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
