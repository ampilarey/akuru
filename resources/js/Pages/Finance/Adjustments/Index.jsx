import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * A year's school-fee adjustments: sibling discounts, scholarships, waivers.
 * Every word is the `finance` book's (slice FN1, STATUS §5qn); an
 * adjustment's kind, basis, reach and state are named rather than printed as
 * codes. An adjustment for some kinds of fee asks which kinds — the form
 * offered the choice with no way to make it, so it saved an adjustment that
 * came off nothing. A refused adjustment is said under the form; only its
 * value's refusals were.
 */
export default function Index({ years, yearId, studentId, adjustments, suggestions, types, bases, appliesTo, statuses, itemTypes = [], t = {} }) {
    const typeName = (type) => t[`adjustment_type_${type}`] || type;
    const basisName = (basis) => t[`basis_${basis}`] || basis;
    const appliesName = (value) => t[`adjustment_applies_${value}`] || value;
    const statusName = (status) => t[`adjustment_status_${status}`] || status;
    const valueOf = (row) => (row.basis === 'percent'
        ? (t.adjustments_value_percent || ':value%')
        : (t.adjustments_value_fixed || ':value MVR')).replace(':value', row.value);
    const form = useForm({
        academic_year_id: yearId || '',
        student_id: studentId || '',
        type: types[0] || 'sibling_discount',
        basis: 'percent',
        value: '10',
        applies_to: 'all_items',
        item_types: [],
        status: 'approved',
        notes: '',
    });
    const toggleItemType = (type) => form.setData('item_types', form.data.item_types.includes(type)
        ? form.data.item_types.filter((value) => value !== type)
        : [...form.data.item_types, type]);

    return (
        <AppShell title={t.adjustments_title || 'Fee adjustments'}>
            <p className="mb-3 text-sm text-gray-600">
                {t.adjustments_intro || 'School-fee reductions only: a sibling discount, a scholarship, a waiver. Promotional discount codes are not set here.'}
            </p>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/adjustments', { academic_year_id: year.id, student_id: studentId || '' })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/finance/adjustments/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({ ...data, item_types: data.applies_to === 'item_types' ? data.item_types : [] }));
                    form.post('/finance/adjustments', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" inputMode="numeric" aria-label={t.adjustments_student_id || 'Student id'} placeholder={t.adjustments_student_id || 'Student id'} value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)} />
                <select className="form-input" aria-label={t.adjustments_type || 'Kind of adjustment'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{typeName(type)}</option>)}
                </select>
                <select className="form-input" aria-label={t.adjustments_basis || 'Percent or amount'} value={form.data.basis} onChange={(e) => form.setData('basis', e.target.value)}>
                    {bases.map((basis) => <option key={basis} value={basis}>{basisName(basis)}</option>)}
                </select>
                <input className="form-input" inputMode="decimal" aria-label={t.adjustments_value || 'Value'} placeholder={t.adjustments_value || 'Value'} value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} />
                <select className="form-input" aria-label={t.adjustments_applies || 'Applies to'} value={form.data.applies_to} onChange={(e) => form.setData('applies_to', e.target.value)}>
                    {appliesTo.map((value) => <option key={value} value={value}>{appliesName(value)}</option>)}
                </select>
                <select className="form-input" aria-label={t.status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{statusName(status)}</option>)}
                </select>
                {form.data.applies_to === 'item_types' && (
                    <fieldset className="flex flex-wrap gap-3 text-sm md:col-span-3">
                        <legend className="mb-1 text-gray-600">{t.adjustments_item_types || 'The kinds of fee it comes off'}</legend>
                        {itemTypes.map((type) => (
                            <label key={type} className="flex items-center gap-2">
                                <input type="checkbox" checked={form.data.item_types.includes(type)} onChange={() => toggleItemType(type)} />
                                {t[`fee_type_${type}`] || type}
                            </label>
                        ))}
                    </fieldset>
                )}
                <input className="form-input md:col-span-3" aria-label={t.adjustments_notes || 'Notes'} placeholder={t.adjustments_notes || 'Notes'} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                <button type="submit" className="btn-primary md:col-span-3" disabled={form.processing}>{t.adjustments_save || 'Save adjustment'}</button>
                <FormErrors errors={form.errors} className="md:col-span-3" />
            </form>

            {suggestions.length > 0 && (
                <div className="mb-4 rounded-lg border bg-white p-4 text-sm">
                    <div className="mb-2 font-medium">{t.adjustments_suggestions || 'Sibling suggestions'}</div>
                    {suggestions.map((row) => (
                        <div key={row.student_id}>{row.student_name} — {row.reason}</div>
                    ))}
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            <th className="px-3 py-2">{t.adjustments_type || 'Kind of adjustment'}</th>
                            <th className="px-3 py-2">{t.adjustments_value || 'Value'}</th>
                            <th className="px-3 py-2">{t.adjustments_applies || 'Applies to'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {adjustments.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.adjustments_none || 'No adjustments yet.'}</td></tr>
                        )}
                        {adjustments.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{typeName(row.type)}</td>
                                <td className="px-3 py-2">{valueOf(row)}</td>
                                <td className="px-3 py-2">{row.applies_to === 'item_types'
                                    ? (row.item_types || []).map((type) => t[`fee_type_${type}`] || type).join(t.list_separator || ', ')
                                    : appliesName(row.applies_to)}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
