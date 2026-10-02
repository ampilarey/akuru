import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const KPIS = [
    ['total_users', 'numbers_kpi_total_users', 'Total Users', '👤', 'bg-indigo-50 text-indigo-700'],
    ['total_enrollments', 'numbers_kpi_enrollments', 'Enrollments', '📋', 'bg-emerald-50 text-emerald-700'],
    ['pending_enrollments', 'numbers_kpi_pending_payment', 'Pending Payment', '⏳', 'bg-amber-50 text-amber-700'],
    ['open_courses', 'numbers_kpi_open_courses', 'Open Courses', '📚', 'bg-yellow-50 text-yellow-800'],
    ['revenue_total', 'numbers_kpi_revenue', 'Revenue (MVR)', '💰', 'bg-pink-50 text-pink-800'],
    ['new_users_today', 'numbers_kpi_new_today', 'New Today', '🆕', 'bg-sky-50 text-sky-700'],
];
const OVERVIEW = [
    ['total_courses', 'numbers_overview_total_courses', 'Total Courses', 'text-indigo-700'],
    ['active_enrollments', 'numbers_overview_active', 'Active', 'text-emerald-700'],
    ['pending_enrollments', 'numbers_overview_pending', 'Pending', 'text-amber-700'],
    ['revenue_today', 'numbers_overview_revenue_today', "Today's Revenue", 'text-[#7C2D37]'],
    ['enrollments_today', 'numbers_overview_enrolled_today', 'Enrolled Today', 'text-sky-700'],
    ['new_users_this_month', 'numbers_overview_month_users', 'This Month (users)', 'text-pink-800'],
];
const STATUS_CLASS = { active: 'bg-emerald-100 text-emerald-800', pending: 'bg-amber-100 text-amber-800', pending_payment: 'bg-amber-100 text-amber-800', cancelled: 'bg-red-100 text-red-800', completed: 'bg-violet-100 text-violet-800' };
const HEALTH = [['database', 'numbers_health_database', 'Database', 'healthy'], ['storage', 'numbers_health_storage', 'Storage', 'healthy'], ['sms_gateway', 'numbers_health_sms', 'SMS Gateway', 'online']];
const PRAYERS = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
const ACTIONS = [
    ['courses', 'numbers_action_courses', 'View Courses', '📚', 'bg-indigo-50 border-indigo-200 text-indigo-700'],
    ['enrollments', 'numbers_action_enrollments', 'All Enrollments', '📋', 'bg-emerald-50 border-emerald-200 text-emerald-800'],
    ['users', 'numbers_action_users', 'Manage Users', '👥', 'bg-orange-50 border-orange-200 text-orange-800'],
    ['settings', 'numbers_action_settings', 'Site Settings', '⚙️', 'bg-sky-50 border-sky-200 text-sky-700'],
];

const humanize = (value) => String(value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

/**
 * The super admin's full dashboard — today's numbers (C9 slice 13, STATUS
 * §5jo). `/admin` is the home and leads with the headline figures; this
 * page keeps the rest: the KPIs, the last ten enrolments, system health, the
 * prayer card, the enrolment overview and the quick actions. Every string is
 * a key in the admin tranche; the numbers are data.
 */
export default function NumbersDashboard({ stats = {}, health = {}, recent = [], today = '', islamic_date = {}, prayer_times = {}, current_prayer = {}, home = '/admin', links = {}, t = {} }) {
    const hijri = `${islamic_date.day} ${islamic_date.month_name} ${islamic_date.year}`;
    const statusLabel = (status) => t[`numbers_status_${status}`] || humanize(status);
    const healthLabel = (value) => t[`numbers_health_${value}`] || humanize(value);

    return (
        <AppShell title={t.numbers_title || 'Super Admin Dashboard'}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gradient-to-br from-[#3D1219] to-[#7C2D37] px-5 py-4 text-white">
                <div>
                    <h2 className="text-lg font-extrabold">{t.numbers_title || 'Super Admin Dashboard'}</h2>
                    <p className="text-xs text-white/70">{today} · {hijri} {t.numbers_ah || 'AH'}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {/* This screen is the numbers; the Institute home is where things are managed (the owner, 2026-09-26). */}
                    <Link href={home} className="rounded-lg bg-white px-3 py-2 text-xs font-bold text-[#7C2D37]" data-testid="open-admin-panel">{t.numbers_institute || '🏠 Institute →'}</Link>
                    <span className="rounded-full border border-white/25 bg-white/15 px-3 py-1 text-[0.7rem] font-bold tracking-wider">{t.numbers_badge || 'SUPER ADMIN'}</span>
                </div>
            </div>
            <p className="mb-5 text-xs text-gray-500" data-testid="dashboard-hint">
                {t.numbers_hint || 'This dashboard is today’s numbers. To run the website, the shops or the system, open the'}{' '}
                <Link href={home} className="font-semibold text-[#7C2D37] underline">{t.numbers_hint_home || 'Institute home'}</Link>.
            </p>

            <div className="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" data-testid="numbers-kpis">
                {KPIS.map(([key, tKey, fallback, icon, classes]) => (
                    <div key={key} className={`rounded-xl border border-black/5 p-4 ${classes}`} data-testid={`kpi-${key}`}>
                        <div className="mb-2 text-2xl">{icon}</div>
                        <div className="text-2xl font-extrabold leading-none" dir="ltr">{stats[key]}</div>
                        <div className="mt-1 text-xs text-gray-500">{t[tKey] || fallback}</div>
                    </div>
                ))}
            </div>

            <div className="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="overflow-hidden rounded-xl border bg-white">
                    <div className="flex items-center justify-between border-b px-5 py-4">
                        <h3 className="text-sm font-bold text-gray-900">{t.numbers_recent_title || 'Recent Enrollments'}</h3>
                        <span className="text-xs text-gray-500">{t.numbers_recent_last || 'Last 10'}</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="table-stack min-w-full text-sm" data-testid="recent-enrollments">
                            <thead className="bg-gray-50 text-xs text-gray-500">
                                <tr>
                                    <th className="px-4 py-2 text-start">{t.numbers_col_student || 'Student'}</th>
                                    <th className="px-4 py-2 text-start">{t.numbers_col_course || 'Course'}</th>
                                    <th className="px-4 py-2 text-start">{t.numbers_col_status || 'Status'}</th>
                                    <th className="px-4 py-2 text-start">{t.numbers_col_fee || 'Fee'}</th>
                                    <th className="px-4 py-2 text-start">{t.numbers_col_date || 'Date'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent.length === 0 && <tr><td className="px-4 py-8 text-center text-gray-400" colSpan="5">{t.numbers_none || 'No enrollments yet.'}</td></tr>}
                                {recent.map((row) => (
                                    <tr key={row.id} className="border-t" data-testid="recent-row">
                                        <td data-label={t.numbers_col_student || 'Student'} className="px-4 py-2 font-medium text-gray-900">{row.student || '—'}</td>
                                        <td data-label={t.numbers_col_course || 'Course'} className="max-w-[180px] truncate px-4 py-2 text-gray-700">{row.course || '—'}</td>
                                        <td data-label={t.numbers_col_status || 'Status'} className="px-4 py-2"><span className={`rounded-full px-2 py-0.5 text-xs font-bold ${STATUS_CLASS[row.status] || 'bg-gray-100 text-gray-700'}`}>{statusLabel(row.status)}</span></td>
                                        <td data-label={t.numbers_col_fee || 'Fee'} className="whitespace-nowrap px-4 py-2 text-gray-700" dir="ltr">{row.fee ? `MVR ${row.fee}` : (t.numbers_free || 'Free')}</td>
                                        <td data-label={t.numbers_col_date || 'Date'} className="whitespace-nowrap px-4 py-2 text-gray-400">{row.date}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-col gap-4">
                    <div className="rounded-xl border bg-white p-5" data-testid="system-health">
                        <h3 className="mb-3 text-sm font-bold text-gray-900">{t.numbers_health_title || 'System Health'}</h3>
                        {HEALTH.map(([key, tKey, fallback, okValue]) => {
                            const ok = health[key] === okValue;
                            return (
                                <div key={key} className="flex items-center justify-between border-b py-2 last:border-b-0">
                                    <span className="text-sm text-gray-700">{t[tKey] || fallback}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${ok ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}`}>{ok ? '✓' : '✗'} {healthLabel(health[key])}</span>
                                </div>
                            );
                        })}
                        <div className="mt-3 rounded-lg bg-gray-50 p-2 text-center">
                            <span className="text-xs text-gray-500">{t.numbers_db_size || 'Database size'}</span>
                            <div className="text-lg font-bold text-gray-700" dir="ltr">{stats.database_size} MB</div>
                        </div>
                    </div>
                    <div className="rounded-xl bg-gradient-to-br from-[#3D1219] to-[#7C2D37] p-5 text-white" data-testid="prayer-card">
                        <h3 className="text-sm font-bold">{t.numbers_prayer_title || 'Prayer Times'}</h3>
                        <p className="mb-3 text-xs text-white/60">{hijri}</p>
                        {current_prayer?.prayer && (
                            <div className="mb-3 flex items-center justify-between rounded-lg bg-white/10 px-3 py-2">
                                <span className="text-xs text-white/80">{t.numbers_current_prayer || 'Current Prayer'}</span>
                                <span className="text-sm font-bold text-[#F9C74F]">{t[`numbers_prayer_${current_prayer.prayer}`] || humanize(current_prayer.prayer)}{current_prayer.time ? ` · ${current_prayer.time}` : ''}</span>
                            </div>
                        )}
                        {PRAYERS.filter((key) => prayer_times[key]).map((key) => (
                            <div key={key} className="flex justify-between border-b border-white/10 py-1 text-xs last:border-b-0">
                                <span className="text-white/65">{t[`numbers_prayer_${key}`] || humanize(key)}</span>
                                <span className="font-semibold text-white/90" dir="ltr">{prayer_times[key]}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div className="rounded-xl border bg-white p-5">
                    <h3 className="mb-4 text-sm font-bold text-gray-900">{t.numbers_overview_title || 'Enrollment Overview'}</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {OVERVIEW.map(([key, tKey, fallback, color]) => (
                            <div key={key} className="rounded-lg border bg-gray-50 p-3">
                                <div className={`text-xl font-extrabold ${color}`} dir="ltr">{key === 'revenue_today' ? `MVR ${stats[key]}` : stats[key]}</div>
                                <div className="mt-0.5 text-xs text-gray-500">{t[tKey] || fallback}</div>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="rounded-xl border bg-white p-5">
                    <h3 className="mb-4 text-sm font-bold text-gray-900">{t.numbers_actions_title || 'Quick Actions'}</h3>
                    <div className="grid grid-cols-2 gap-3" data-testid="quick-actions">
                        {ACTIONS.map(([key, tKey, fallback, icon, classes]) => (
                            <Link key={key} href={links[key] || '#'} className={`flex items-center gap-2 rounded-lg border p-3 text-sm font-semibold hover:opacity-80 ${classes}`}>
                                <span className="text-lg">{icon}</span>{t[tKey] || fallback}
                            </Link>
                        ))}
                        <a href={links.website || '/'} className="flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 p-3 text-sm font-semibold text-violet-800 hover:opacity-80"><span className="text-lg">🌐</span>{t.numbers_action_website || 'View Website'}</a>
                        <button type="button" onClick={() => router.post(links.logout || '/logout')} className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-start text-sm font-semibold text-red-800 hover:opacity-80"><span className="text-lg">🔒</span>{t.numbers_action_logout || 'Logout'}</button>
                    </div>
                </div>
            </div>
        </AppShell>
    );
}
