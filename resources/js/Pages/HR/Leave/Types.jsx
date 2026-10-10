import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * The school's leave types, one to a code. Every word is the `hr` book's
 * (slice HR1, STATUS §5ql); a type's code is named rather than printed, and a
 * type reads by the name the school gave it in the page's language — the form
 * takes its Dhivehi and Arabic names, which the type had a place for and no
 * box. The form changes the type of the code chosen, its values filled in
 * from the list: it only ever made a new one, and as the school starts with
 * every code, saving it could only fail (a 500 on the code's unique index).
 */
export default function Types({ types, codes, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const codeName = (code) => t[`leave_code_${code}`] || code;
    const typeOf = (code) => types.find((row) => row.code === code);
    const valuesOf = (code) => {
        const type = typeOf(code);
        return {
            code,
            name: type?.name ?? '',
            name_arabic: type?.name_arabic ?? '',
            name_dhivehi: type?.name_dhivehi ?? '',
            days_per_year: String(type?.days_per_year ?? 0),
            carry_over_max: String(type?.carry_over_max ?? 0),
            requires_document: Boolean(type?.requires_document),
            paid: type ? Boolean(type.paid) : true,
            active: type ? Boolean(type.active) : true,
        };
    };
    const form = useForm(valuesOf(codes[0] || 'annual'));
    const existing = typeOf(form.data.code);
    const refused = form.errors.name || form.errors.code || form.errors.days_per_year || form.errors.carry_over_max;

    return (
        <AppShell title={t.types_title || 'Leave types'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/leave-types/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    if (existing) {
                        form.put(`/hr/leave-types/${existing.id}`, { preserveScroll: true });
                    } else {
                        form.post('/hr/leave-types', { preserveScroll: true });
                    }
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.types_code || 'Code'} value={form.data.code} onChange={(e) => form.setData(valuesOf(e.target.value))}>
                    {codes.map((code) => <option key={code} value={code}>{codeName(code)}</option>)}
                </select>
                <input className="form-input" aria-label={t.name_en || 'Name (EN)'} placeholder={t.name_en || 'Name (EN)'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.name_dv || 'Name (DV)'} placeholder={t.name_dv || 'Name (DV)'} value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.name_ar || 'Name (AR)'} placeholder={t.name_ar || 'Name (AR)'} value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                <input className="form-input" aria-label={t.types_days_year || 'Days / year'} placeholder={t.types_days_year || 'Days / year'} value={form.data.days_per_year} onChange={(e) => form.setData('days_per_year', e.target.value)} />
                <input className="form-input" aria-label={t.types_carry_max || 'Carry-over max'} placeholder={t.types_carry_max || 'Carry-over max'} value={form.data.carry_over_max} onChange={(e) => form.setData('carry_over_max', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_document} onChange={(e) => form.setData('requires_document', e.target.checked)} />
                    {t.types_requires_document || 'Needs a supporting document'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.paid} onChange={(e) => form.setData('paid', e.target.checked)} />
                    {t.types_paid || 'Paid leave'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} />
                    {t.types_active || 'Offered to staff'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.types_save || 'Save leave type'}</button>
                {refused && <span className="text-xs text-red-600 md:col-span-4">{refused}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.types_code || 'Code'}</th>
                            <th className="px-3 py-2">{t.name || 'Name'}</th>
                            <th className="px-3 py-2">{t.types_col_days || 'Days'}</th>
                            <th className="px-3 py-2">{t.types_col_carry || 'Carry'}</th>
                            <th className="px-3 py-2">{t.types_col_paid || 'Paid'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {types.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.types_none || 'No leave types yet.'}</td></tr>
                        )}
                        {types.map((type) => (
                            <tr key={type.id} className="border-t">
                                <td className="px-3 py-2">{codeName(type.code)}</td>
                                <td className="px-3 py-2">{named(type)}</td>
                                <td className="px-3 py-2">{type.days_per_year}</td>
                                <td className="px-3 py-2">{type.carry_over_max}</td>
                                <td className="px-3 py-2">{type.paid ? (t.yes || 'yes') : (t.no || 'no')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
