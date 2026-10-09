import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function Row({ type, t, locale }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: type.name,
        name_dhivehi: type.name_dhivehi ?? '',
        name_arabic: type.name_arabic ?? '',
        excuses_absence: type.excuses_absence,
        requires_evidence: type.requires_evidence,
        is_active: type.is_active,
        sort_order: type.sort_order,
    });

    return (
        <>
            <tr className={`border-t ${type.is_active ? '' : 'opacity-60'}`}>
                <td className="px-3 py-2">
                    <p className="font-medium">{{ dv: type.name_dhivehi, ar: type.name_arabic }[locale] || type.name}</p>
                    <p className="text-xs text-gray-500">{type.code}</p>
                </td>
                <td className="px-3 py-2 text-sm">
                    {type.excuses_absence
                        ? <span className="text-emerald-700">{t.types_excuses || 'Excuses the register'}</span>
                        : <span className="text-gray-600">{t.types_stays_absent || 'Stays absent'}</span>}
                </td>
                <td className="px-3 py-2 text-sm">{type.requires_evidence ? (t.types_document_required || 'Document required') : '—'}</td>
                <td className="px-3 py-2 text-sm">{type.is_active ? (t.types_offered || 'Offered') : (t.types_retired || 'Retired')}</td>
                <td className="px-3 py-2">
                    <button className="text-xs text-[#7C2D37] underline" onClick={() => setOpen(!open)}>{t.edit || 'Edit'}</button>
                </td>
            </tr>
            {open && (
                <tr className="border-t bg-[#FBF7F2]">
                    <td colSpan={5} className="px-3 py-3">
                        <form
                            className="grid gap-2 sm:grid-cols-3"
                            onSubmit={(e) => { e.preventDefault(); form.put(`/academics/absence-types/${type.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }}
                        >
                            <label className="text-sm">
                                <span className="mb-1 block text-gray-600">{t.types_name || 'Name'}</span>
                                <input className="form-input w-full" value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)} />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1 block text-gray-600">{t.types_dhivehi || 'Dhivehi'}</span>
                                <input className="form-input w-full" dir="rtl" value={form.data.name_dhivehi}
                                    onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1 block text-gray-600">{t.types_arabic || 'Arabic'}</span>
                                <input className="form-input w-full" dir="rtl" value={form.data.name_arabic}
                                    onChange={(e) => form.setData('name_arabic', e.target.value)} />
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.excuses_absence}
                                    onChange={(e) => form.setData('excuses_absence', e.target.checked)} />
                                {t.types_approving_excuses || 'Approving excuses the register'}
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.requires_evidence}
                                    onChange={(e) => form.setData('requires_evidence', e.target.checked)} />
                                {t.types_requires_document || 'Requires a document'}
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)} />
                                {t.types_offered_to_families || 'Offered to families'}
                            </label>
                            {form.errors.name && <p className="text-xs text-red-600 sm:col-span-3">{form.errors.name}</p>}
                            <button className="btn-primary justify-self-start text-sm sm:col-span-3" disabled={form.processing}>
                                {t.save || 'Save'}
                            </button>
                        </form>
                    </td>
                </tr>
            )}
        </>
    );
}

export default function Index({ types = [], t = {} }) {
    const locale = usePage().props.locale || 'en';
    const form = useForm({ name: '', name_dhivehi: '', name_arabic: '', excuses_absence: true, requires_evidence: false });

    // In the page's language (BACKLOG C21, slice OA1). A reason is named in
    // the page's language where the office has; its code stays as written.
    return (
        <AppShell title={t.types_title || 'Absence reasons'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.types_intro || 'The reasons a family can give when their child is away. Each one decides whether approving the note clears the register, and whether a document must be attached.'}
            </p>

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">{t.types_col_reason || 'Reason'}</th>
                            <th className="px-3 py-2 text-start">{t.types_col_on_approval || 'On approval'}</th>
                            <th className="px-3 py-2 text-start">{t.types_col_evidence || 'Evidence'}</th>
                            <th className="px-3 py-2 text-start">{t.col_status || 'Status'}</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>{types.map((type) => <Row key={type.id} type={type} t={t} locale={locale} />)}</tbody>
                </table>
            </div>

            <form
                className="grid gap-3 rounded-lg border bg-white p-4 sm:grid-cols-3"
                onSubmit={(e) => { e.preventDefault(); form.post('/academics/absence-types', { preserveScroll: true, onSuccess: () => form.reset() }); }}
            >
                <p className="text-sm font-semibold sm:col-span-3">{t.types_add_reason || 'Add a reason'}</p>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.types_name || 'Name'}</span>
                    <input className="form-input w-full" value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.types_dhivehi_optional || 'Dhivehi (optional)'}</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_dhivehi}
                        onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.types_arabic_optional || 'Arabic (optional)'}</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_arabic}
                        onChange={(e) => form.setData('name_arabic', e.target.value)} />
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.excuses_absence}
                        onChange={(e) => form.setData('excuses_absence', e.target.checked)} />
                    {t.types_approving_excuses || 'Approving excuses the register'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_evidence}
                        onChange={(e) => form.setData('requires_evidence', e.target.checked)} />
                    {t.types_requires_document || 'Requires a document'}
                </label>
                {form.errors.name && <p className="text-xs text-red-600 sm:col-span-3">{form.errors.name}</p>}
                {form.errors.code && <p className="text-xs text-red-600 sm:col-span-3">{form.errors.code}</p>}
                <button className="btn-primary justify-self-start text-sm sm:col-span-3" disabled={form.processing}>
                    {t.add || 'Add'}
                </button>
            </form>

            <p className="mt-4 text-xs text-gray-500">
                {t.types_retire_note || 'A reason that has been used is retired rather than deleted, and its code never changes — old notes point at it, and rewriting it would restate why a child was away last term.'}
            </p>
        </AppShell>
    );
}
