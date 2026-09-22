import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * The staff noticeboard: compose a notice and see every notice, including
 * the unpublished and the expired. Families read the published ones on
 * /portal/announcements. This replaced the Blade announcements screens on
 * 2026-09-22 (S2 DoD "legacy announcement Blade screens removed").
 */
export default function Index({ announcements = [], types = [], priorities = [], audiences = [], classes = [] }) {
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

    return (
        <AppShell title="Announcements">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/announcements', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <h2 className="font-semibold md:col-span-3">New notice</h2>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Title</span>
                    <input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </label>
                <label className="block text-sm" dir="rtl">
                    <span className="mb-1 block text-gray-600">ސުރުޚީ (Dhivehi)</span>
                    <input className="form-input w-full" value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                </label>
                <label className="block text-sm" dir="rtl">
                    <span className="mb-1 block text-gray-600">العنوان (Arabic)</span>
                    <input className="form-input w-full" value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">Notice</span>
                    <textarea className="form-input w-full" rows={4} value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-3" dir="rtl">
                    <span className="mb-1 block text-gray-600">Dhivehi (optional)</span>
                    <textarea className="form-input w-full" rows={2} value={form.data.content_dhivehi} onChange={(e) => form.setData('content_dhivehi', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-3" dir="rtl">
                    <span className="mb-1 block text-gray-600">Arabic (optional)</span>
                    <textarea className="form-input w-full" rows={2} value={form.data.content_arabic} onChange={(e) => form.setData('content_arabic', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Type</span>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((t) => <option key={t} value={t}>{t}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Priority</span>
                    <select className="form-input w-full" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>
                        {priorities.map((p) => <option key={p} value={p}>{p}</option>)}
                    </select>
                </label>
                <div className="text-sm">
                    <span className="mb-1 block text-gray-600">Audience</span>
                    <div className="flex flex-wrap gap-3">
                        {audiences.map((a) => (
                            <label key={a} className="inline-flex items-center gap-1">
                                <input type="checkbox" checked={form.data.target_audience.includes(a)} onChange={() => toggle('target_audience', a)} />
                                {a}
                            </label>
                        ))}
                    </div>
                </div>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Publish on</span>
                    <input className="form-input w-full" type="date" value={form.data.publish_date} onChange={(e) => form.setData('publish_date', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Expires (optional)</span>
                    <input className="form-input w-full" type="date" value={form.data.expiry_date} onChange={(e) => form.setData('expiry_date', e.target.value)} />
                </label>
                <div className="text-sm">
                    <span className="mb-1 block text-gray-600">Only these classes (optional)</span>
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
                    <button type="submit" className="btn-primary" disabled={form.processing}>Publish notice</button>
                </div>
                <FormErrors errors={form.errors} />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Published</th>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Priority</th>
                            <th className="px-3 py-2">Audience</th>
                            <th className="px-3 py-2">Expires</th>
                            <th className="px-3 py-2">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        {announcements.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>No notices yet.</td></tr>
                        )}
                        {announcements.map((row) => (
                            <tr key={row.id} className="border-t align-top">
                                <td className="px-3 py-2 whitespace-nowrap">{row.publish_date}</td>
                                <td className="px-3 py-2">
                                    <p className="font-medium">{row.title}</p>
                                    <p className="whitespace-pre-wrap text-xs text-gray-600">{row.content}</p>
                                </td>
                                <td className="px-3 py-2">{row.type}</td>
                                <td className="px-3 py-2">{row.priority}</td>
                                <td className="px-3 py-2">{row.target_audience.join(', ')}{row.target_classes.length ? ` · ${row.target_classes.length} class(es)` : ''}</td>
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
