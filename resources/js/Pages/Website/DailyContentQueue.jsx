import { Link, router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The approval queue (W23 maker–checker; C9 slice 9, STATUS §5jk): every
 * draft nobody has approved, with *Schedule* and *Approve & publish* for a
 * reviewer who is not its creator. Each row keeps a real form `action`, as
 * the Blade page had, and posts as an Inertia request. Every string is a
 * key in the admin tranche.
 */
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

export default function DailyContentQueue({ items = [], t = {} }) {
    const { flash = {}, errors = {}, auth = {} } = usePage().props;
    const viewerId = auth.user?.id;
    const firstError = Object.values(errors)[0];
    const typeLabel = (type) => t[`subs_type_${type}`] || humanize(type);
    const preview = (item) => {
        if (item.content_type === 'hadith') return `${item.hadith_collection || ''} ${item.hadith_number || ''} · ${item.hadith_grading || ''}`;
        if (item.content_type === 'ayah') return item.ayah?.meanings?.en || (t.subs_type_ayah || 'Ayah');
        return item.text_en || '';
    };
    const approve = (item, status) => (e) => {
        e.preventDefault();
        router.post(`/admin/public-site/daily-content/${item.id}/approve`, { status }, { preserveScroll: true });
    };

    return (
        <AppShell title={t.daily_queue_title || 'Approval queue'}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/daily-content" className="underline" data-testid="queue-calendar-link">{t.daily_link_calendar || 'Calendar →'}</Link></p>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="queue-flash">✓ {flash.success}</p>}
            {(flash.error || firstError) && <p className="mb-4 rounded bg-red-50 p-3 text-red-700" data-testid="queue-error">✗ {flash.error || firstError}</p>}
            <p className="mb-4 text-sm text-gray-600">{t.daily_queue_intro || 'The creator cannot approve their own item.'}</p>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="queue-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.daily_col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.daily_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.daily_col_preview || 'Preview'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.daily_col_decision || 'Decision'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="4">{t.daily_queue_empty || 'Nothing waiting.'}</td></tr>}
                        {items.map((item) => (
                            <tr key={item.id} className="border-t align-top" data-testid="queue-row">
                                <td className="whitespace-nowrap px-3 py-2" dir="ltr">{item.publish_date}</td>
                                <td className="px-3 py-2">{typeLabel(item.content_type)}</td>
                                <td className="px-3 py-2">{preview(item)}</td>
                                <td className="px-3 py-2">
                                    {Number(item.created_by) === Number(viewerId)
                                        ? <span className="text-gray-500" data-testid="queue-waiting">{t.daily_waiting_reviewer || 'Waiting for another reviewer'}</span>
                                        : (
                                            <form action={`/admin/public-site/daily-content/${item.id}/approve`} method="post" className="flex flex-wrap gap-2" onSubmit={(e) => e.preventDefault()}>
                                                <button className="btn-secondary" type="button" onClick={approve(item, 'scheduled')} data-testid="queue-schedule">{t.daily_schedule || 'Schedule'}</button>
                                                <button className="btn-primary" type="button" onClick={approve(item, 'published')} data-testid="queue-publish">{t.daily_approve_publish || 'Approve & publish'}</button>
                                            </form>
                                        )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
