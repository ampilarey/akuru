import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * Daily content (W23; docs/ADMIN_PANEL.md; C9 slice 9, STATUS §5jk): the
 * month's calendar of ayah, hadith, saying and reminder items, the list
 * behind it, the filters as an Inertia visit, a CSV carrying them, and the
 * theme-batch form that plants a run of reminder drafts. Maker–checker: a
 * second reviewer approves from the queue. Every string is a key in the
 * admin tranche.
 */
const TYPES = ['ayah', 'hadith', 'saying', 'reminder'];
const STATUSES = ['draft', 'scheduled', 'published', 'archived'];
const STATUS_TONES = { published: 'text-green-700', draft: 'text-amber-700', scheduled: 'text-gray-700', archived: 'text-gray-400' };
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

/** The weeks of a month, Sunday first, padded to whole weeks; dates as Y-m-d. */
function monthGrid(month) {
    const [y, m] = month.split('-').map(Number);
    const first = new Date(Date.UTC(y, m - 1, 1));
    const start = new Date(first);
    start.setUTCDate(1 - first.getUTCDay());
    const last = new Date(Date.UTC(y, m, 0));
    const end = new Date(last);
    end.setUTCDate(last.getUTCDate() + (6 - last.getUTCDay()));
    const days = [];
    for (const d = new Date(start); d <= end; d.setUTCDate(d.getUTCDate() + 1)) {
        days.push({ key: d.toISOString().slice(0, 10), day: d.getUTCDate(), inMonth: d.getUTCMonth() === m - 1 });
    }
    return days;
}

export default function DailyContent({ items = [], filters = {}, month, t = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [form, setForm] = useState({ month: month || '', content_type: filters.content_type || '', status: filters.status || '', theme_tag: filters.theme_tag || '', q: filters.q || '' });
    const [batch, setBatch] = useState({ publish_date: '', days: '30', theme_tag: '', attribution: '', text_en: '', text_dv: '' });
    const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });
    const setB = (key) => (e) => setBatch({ ...batch, [key]: e.target.value });
    const active = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const query = new URLSearchParams(active).toString();
    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/public-site/daily-content', active, { preserveState: true, preserveScroll: true });
    };
    const submitBatch = (e) => {
        e.preventDefault();
        router.post('/admin/public-site/daily-content/batch', batch, { preserveScroll: true });
    };
    const typeLabel = (type) => t[`subs_type_${type}`] || humanize(type);
    const statusLabel = (s) => t[`daily_status_${s}`] || humanize(s);
    const preview = (item) => {
        if (item.content_type === 'ayah') return item.ayah?.meanings?.en || `${t.subs_type_ayah || 'Ayah'} ${item.quran_ayah_id ?? ''}`;
        if (item.content_type === 'hadith') return `${item.hadith_collection || ''} ${item.hadith_number || ''}`.trim();
        const text = item.text_en || item.attribution || '';
        return text.length > 80 ? `${text.slice(0, 80)}…` : text;
    };
    const byDate = items.reduce((acc, item) => { (acc[item.publish_date] ||= []).push(item); return acc; }, {});
    const days = monthGrid(month);
    const firstError = Object.values(errors)[0];
    const monthTitle = new Date(`${month}-01T00:00:00Z`).toLocaleDateString(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' });

    return (
        <AppShell title={t.daily_title || 'Daily content'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/admin/public-site/daily-content/queue" className="underline" data-testid="daily-queue-link">{t.daily_link_queue || 'Approval queue →'}</Link>
                <Link href="/admin/public-site/daily-subscriptions" className="underline">{t.daily_link_subscriptions || 'Subscriptions →'}</Link>
                <Link href="/admin/public-site/research" className="underline">{t.subs_link_research || 'Research →'}</Link>
                {/* Prayer times are still Blade: a full page load. */}
                <a href="/admin/prayer-times/islands" className="underline">{t.subs_link_prayer || 'Prayer times →'}</a>
                <Link href="/admin/public-site/leads" className="underline">{t.funnel_link_leads || 'Leads →'}</Link>
                <Link href="/admin/public-site/funnel" className="underline">{t.leads_link_funnel || 'Funnel →'}</Link>
                <a href={`/admin/public-site/daily-content/export${query ? `?${query}` : ''}`} className="ms-auto underline" data-testid="export-csv">{t.daily_export || 'Export CSV'}</a>
                <Link href="/admin/public-site/daily-content/create" className="btn-primary" data-testid="daily-new">{t.daily_new || 'New item'}</Link>
            </div>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="daily-flash">✓ {flash.success}</p>}
            {(flash.error || firstError) && <p className="mb-4 rounded bg-red-50 p-3 text-red-700" data-testid="daily-error">✗ {flash.error || firstError}</p>}
            <p className="mb-4 text-sm text-gray-600">{t.daily_intro || 'Maker–checker: a second reviewer with daily_content.approve must approve before schedule/publish. Hadith needs collection, number, grading, and grading source. No auto-generation.'}</p>

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-3" data-testid="daily-filter">
                <label className="text-xs text-gray-600">{t.daily_month || 'Month'}<input type="month" name="month" className="form-input mt-1 block" value={form.month} onChange={set('month')} /></label>
                <label className="text-xs text-gray-600">{t.daily_type || 'Type'}
                    <select name="content_type" className="form-input mt-1 block" value={form.content_type} onChange={set('content_type')}>
                        <option value="">{t.leads_all || 'All'}</option>
                        {TYPES.map((type) => <option key={type} value={type}>{typeLabel(type)}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-600">{t.daily_status || 'Status'}
                    <select name="status" className="form-input mt-1 block" value={form.status} onChange={set('status')}>
                        <option value="">{t.leads_all || 'All'}</option>
                        {STATUSES.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-600">{t.daily_theme || 'Theme'}<input type="text" name="theme_tag" className="form-input mt-1 block w-32" value={form.theme_tag} onChange={set('theme_tag')} /></label>
                <label className="text-xs text-gray-600">{t.daily_search || 'Search archive'}<input type="text" name="q" className="form-input mt-1 block w-40" value={form.q} onChange={set('q')} /></label>
                <button type="submit" className="btn-primary">{t.leads_filter || 'Filter'}</button>
            </form>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="daily-calendar">
                <h2 className="mb-3 text-base font-semibold text-gray-800">{monthTitle}</h2>
                <div className="grid grid-cols-7 gap-1 text-sm" dir="ltr">
                    {['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'].map((d) => <div key={d} className="py-1 text-center text-xs text-gray-500">{t[`daily_day_${d}`] || humanize(d)}</div>)}
                    {days.map((cell) => (
                        <div key={cell.key} className={`min-h-[5.5rem] rounded p-1 ${cell.inMonth ? 'border border-gray-200 bg-white' : 'border border-transparent bg-gray-50 text-gray-400'}`} data-date={cell.key}>
                            <div className="mb-1 text-xs text-gray-500">{cell.day}</div>
                            {(byDate[cell.key] || []).map((item) => (
                                <Link key={item.id} href={`/admin/public-site/daily-content/${item.id}/edit`} className={`mb-0.5 block truncate text-[11px] leading-tight ${STATUS_TONES[item.status] || 'text-gray-700'}`} data-testid="daily-cell">{typeLabel(item.content_type)}</Link>
                            ))}
                        </div>
                    ))}
                </div>
            </section>

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white" data-testid="daily-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.daily_col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.daily_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.daily_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.daily_col_preview || 'Preview'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.research_edit || 'Edit'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="5">{t.daily_none || 'No items this month.'}</td></tr>}
                        {items.map((item) => (
                            <tr key={item.id} className="border-t align-top" data-testid="daily-row">
                                <td className="whitespace-nowrap px-3 py-2 text-gray-900" dir="ltr">{item.publish_date}</td>
                                <td className="px-3 py-2 text-gray-900">{typeLabel(item.content_type)}</td>
                                <td className="px-3 py-2 text-gray-700" data-testid="daily-row-status">{statusLabel(item.status)}</td>
                                <td className="px-3 py-2 text-gray-700">{preview(item)}</td>
                                <td className="whitespace-nowrap px-3 py-2"><Link href={`/admin/public-site/daily-content/${item.id}/edit`} className="text-xs font-semibold text-[#1D4E89] underline" data-testid="daily-edit">{t.research_edit || 'Edit'}</Link></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <section className="rounded-lg border bg-white p-5" data-testid="daily-batch">
                <h2 className="mb-3 text-base font-semibold text-gray-800">{t.daily_batch_title || 'Theme batch (reminder drafts)'}</h2>
                <form onSubmit={submitBatch} className="grid gap-4 md:grid-cols-2">
                    <label className="text-xs text-gray-600">{t.daily_batch_start || 'Start date'}<input type="date" name="publish_date" required className="form-input mt-1 block w-full" value={batch.publish_date} onChange={setB('publish_date')} /></label>
                    <label className="text-xs text-gray-600">{t.daily_batch_days || 'Days (1–40)'}<input type="number" name="days" min="1" max="40" className="form-input mt-1 block w-full" value={batch.days} onChange={setB('days')} /></label>
                    <label className="text-xs text-gray-600">{t.daily_theme_tag || 'Theme tag'}<input type="text" name="theme_tag" placeholder="ramadan" className="form-input mt-1 block w-full" value={batch.theme_tag} onChange={setB('theme_tag')} /></label>
                    <label className="text-xs text-gray-600">{t.daily_attribution || 'Attribution'}<input type="text" name="attribution" className="form-input mt-1 block w-full" value={batch.attribution} onChange={setB('attribution')} /></label>
                    <label className="text-xs text-gray-600 md:col-span-2">{t.daily_english || 'English'}<textarea name="text_en" rows="2" className="form-input mt-1 block w-full" value={batch.text_en} onChange={setB('text_en')} /></label>
                    <label className="text-xs text-gray-600 md:col-span-2">{t.daily_dhivehi || 'Dhivehi'}<textarea name="text_dv" rows="2" dir="rtl" className="form-input mt-1 block w-full" value={batch.text_dv} onChange={setB('text_dv')} /></label>
                    <div><button className="btn-primary" type="submit" data-testid="daily-batch-submit">{t.daily_batch_create || 'Create drafts'}</button></div>
                </form>
            </section>
        </AppShell>
    );
}
