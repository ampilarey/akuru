import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * The staff noticeboard: compose a notice and see every notice, including
 * the unpublished and the expired. Families read the published ones on
 * /portal/announcements. This replaced the Blade announcements screens on
 * 2026-09-22 (S2 DoD "legacy announcement Blade screens removed").
 *
 * In the page's language (BACKLOG C21, slice OA3). A notice's type,
 * priority and audience are codes, named here — with the same words the
 * family's noticeboard uses; a notice reads in the page's language where the
 * office wrote it in that language.
 */
export default function Index({ announcements = [], types = [], priorities = [], audiences = [], classes = [], t = {} }) {
    const locale = usePage().props.locale || 'en';
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        title: '',
        title_dhivehi: '',
        title_arabic: '',
        content: '',
        content_dhivehi: '',
        content_arabic: '',
        type: types[0] || 'general',
        priority: 'medium',
        target_audience: ['all'],
        target_classes: [],
        publish_date: today,
        expiry_date: '',
    });

    const toggle = (key, value) => {
        const current = form.data[key];
        form.setData(key, current.includes(value) ? current.filter((v) => v !== value) : [...current, value]);
    };
    const typeName = (type) => t[`notice_type_${type}`] || type;
    const priorityName = (priority) => t[`notice_priority_${priority}`] || priority;
    const audienceName = (audience) => t[`notice_audience_${audience}`] || audience;
    const inLocale = (row, field) => ({ dv: row[`${field}_dhivehi`], ar: row[`${field}_arabic`] }[locale]) || row[field];

    return (
        <AppShell title={t.notices_title || 'Announcements'}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/announcements', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <h2 className="font-semibold md:col-span-3">{t.notices_new || 'New notice'}</h2>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.title || 'Title'}</span>
                    <input className="form-input w-full" aria-label={t.title || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </label>
                <label className="block text-sm" dir="rtl">
                    <span className="mb-1 block text-gray-600">{t.notices_title_dv || 'Title (Dhivehi)'}</span>
                    <input className="form-input w-full" aria-label={t.notices_title_dv || 'Title (Dhivehi)'} value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                </label>
                <label className="block text-sm" dir="rtl">
                    <span className="mb-1 block text-gray-600">{t.notices_title_ar || 'Title (Arabic)'}</span>
                    <input className="form-input w-full" aria-label={t.notices_title_ar || 'Title (Arabic)'} value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">{t.notices_notice || 'Notice'}</span>
                    <textarea className="form-input w-full" rows={4} aria-label={t.notices_notice || 'Notice'} value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-3" dir="rtl">
                    <span className="mb-1 block text-gray-600">{t.notices_text_dv || 'Notice in Dhivehi (optional)'}</span>
                    <textarea className="form-input w-full" rows={2} aria-label={t.notices_text_dv || 'Notice in Dhivehi (optional)'} value={form.data.content_dhivehi} onChange={(e) => form.setData('content_dhivehi', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-3" dir="rtl">
                    <span className="mb-1 block text-gray-600">{t.notices_text_ar || 'Notice in Arabic (optional)'}</span>
                    <textarea className="form-input w-full" rows={2} aria-label={t.notices_text_ar || 'Notice in Arabic (optional)'} value={form.data.content_arabic} onChange={(e) => form.setData('content_arabic', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.type || 'Type'}</span>
                    <select className="form-input w-full" aria-label={t.type || 'Type'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{typeName(type)}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.notices_priority || 'Priority'}</span>
                    <select className="form-input w-full" aria-label={t.notices_priority || 'Priority'} value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>
                        {priorities.map((priority) => <option key={priority} value={priority}>{priorityName(priority)}</option>)}
                    </select>
                </label>
                <div className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.notices_audience || 'Audience'}</span>
                    <div className="flex flex-wrap gap-3">
                        {audiences.map((audience) => (
                            <label key={audience} className="inline-flex items-center gap-1">
                                <input type="checkbox" checked={form.data.target_audience.includes(audience)} onChange={() => toggle('target_audience', audience)} />
                                {audienceName(audience)}
                            </label>
                        ))}
                    </div>
                </div>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.notices_publish_on || 'Publish on'}</span>
                    <input className="form-input w-full" type="date" aria-label={t.notices_publish_on || 'Publish on'} value={form.data.publish_date} onChange={(e) => form.setData('publish_date', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.notices_expires || 'Expires (optional)'}</span>
                    <input className="form-input w-full" type="date" aria-label={t.notices_expires || 'Expires (optional)'} value={form.data.expiry_date} onChange={(e) => form.setData('expiry_date', e.target.value)} />
                </label>
                <div className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.notices_classes || 'Only these classes (optional)'}</span>
                    <div className="flex max-h-28 flex-wrap gap-2 overflow-y-auto">
                        {classes.map((c) => (
                            <label key={c.id} className="inline-flex items-center gap-1">
                                <input type="checkbox" checked={form.data.target_classes.includes(c.id)} onChange={() => toggle('target_classes', c.id)} />
                                {c.label}
                            </label>
                        ))}
                    </div>
                </div>
                <div className="md:col-span-3">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.notices_publish || 'Publish notice'}</button>
                </div>
                <FormErrors errors={form.errors} />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.notices_col_published || 'Published'}</th>
                            <th className="px-3 py-2">{t.col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.notices_priority || 'Priority'}</th>
                            <th className="px-3 py-2">{t.notices_audience || 'Audience'}</th>
                            <th className="px-3 py-2">{t.notices_col_expires || 'Expires'}</th>
                            <th className="px-3 py-2">{t.notices_col_by || 'By'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {announcements.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>{t.notices_none || 'No notices yet.'}</td></tr>
                        )}
                        {announcements.map((row) => (
                            <tr key={row.id} className="border-t align-top">
                                <td className="px-3 py-2 whitespace-nowrap">{row.publish_date}</td>
                                <td className="px-3 py-2">
                                    <p className="font-medium">{inLocale(row, 'title')}</p>
                                    <p className="whitespace-pre-wrap text-xs text-gray-600">{inLocale(row, 'content')}</p>
                                </td>
                                <td className="px-3 py-2">{typeName(row.type)}</td>
                                <td className="px-3 py-2">{priorityName(row.priority)}</td>
                                <td className="px-3 py-2">
                                    {row.target_audience.map(audienceName).join(', ')}
                                    {row.target_classes.length ? ` · ${(t.notices_classes_count || ':count class(es)').replace(':count', row.target_classes.length)}` : ''}
                                </td>
                                <td className="px-3 py-2">{row.expiry_date || '—'}</td>
                                <td className="px-3 py-2">{row.author || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
