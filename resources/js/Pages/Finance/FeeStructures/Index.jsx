import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * A year's fee structures: which fees each class pays. Every word is the
 * `finance` book's (slice FN1, STATUS §5qn); a structure's reach and state and
 * how often a fee falls due are named rather than printed as codes, a fee item
 * reads by the name the school gave it in the page's language, and the list
 * names a structure's classes — it printed their ids.
 *
 * The form takes as many fee items as the structure has: it had room for one,
 * so a structure could bill one fee. Choosing a line's item fills in that
 * item's amount, how often it falls due and whether it is mandatory — the
 * line kept the first item's. A refused copy from last year is said beside
 * its button, and a refused structure under its form.
 */
export default function Index({ years, yearId, classes, feeItems, structures, appliesTo, statuses, frequencies, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const frequencyName = (frequency) => t[`frequency_${frequency}`] || frequency;
    const statusName = (status) => t[`structure_status_${status}`] || status;
    const appliesName = (value) => t[`applies_${value}`] || value;
    const lineFor = (feeItem) => ({
        fee_item_id: feeItem?.id || '',
        amount: feeItem?.default_amount ?? '',
        frequency: feeItem?.frequency || frequencies[0] || 'one_time',
        due_day: 5,
        is_mandatory: feeItem ? Boolean(feeItem.is_mandatory) : true,
    });
    const form = useForm({
        academic_year_id: yearId || '',
        name: '',
        applies_to: appliesTo[0] || 'class',
        class_ids: [],
        status: 'draft',
        items: feeItems[0] ? [lineFor(feeItems[0])] : [],
    });
    const refusals = useRowRefusals(form);
    const classLabel = (id) => classes.find((row) => `${row.id}` === `${id}`)?.label || `${id}`;
    const listed = (names) => names.join(t.list_separator || ', ');

    const toggleClass = (id) => {
        const current = form.data.class_ids.map(String);
        const next = current.includes(String(id))
            ? form.data.class_ids.filter((value) => String(value) !== String(id))
            : [...form.data.class_ids, id];
        form.setData('class_ids', next);
    };

    const setItem = (index, key, value) => {
        const items = form.data.items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
        form.setData('items', items);
    };
    const chooseItem = (index, id) => {
        const feeItem = feeItems.find((row) => `${row.id}` === `${id}`);
        const items = form.data.items.map((item, i) => (i === index ? { ...lineFor(feeItem), due_day: item.due_day } : item));
        form.setData('items', items);
    };
    const addLine = () => form.setData('items', [...form.data.items, lineFor(feeItems[0])]);
    const removeLine = (index) => form.setData('items', form.data.items.filter((_, i) => i !== index));

    return (
        <AppShell title={t.structures_title || 'Fee structures'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/fee-structures', { academic_year_id: year.id })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <div className="flex flex-wrap items-start gap-2">
                    <div>
                        <button
                            type="button"
                            className="btn-secondary"
                            onClick={() => refusals.actOn('copy', () => router.post('/finance/fee-structures/copy-last-year', { academic_year_id: yearId }, { preserveScroll: true }))}
                        >
                            {t.structures_copy || 'Copy from last year'}
                        </button>
                        <FormErrors errors={refusals.errorsFor('copy')} className="mt-1" />
                    </div>
                    <a className="btn-secondary" href={`/finance/fee-structures/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
                </div>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/fee-structures', { preserveScroll: true });
                }}
                className="mb-4 space-y-3 rounded-lg border bg-white p-4"
            >
                <div className="grid gap-3 md:grid-cols-4">
                    <input className="form-input" aria-label={t.structures_name || 'Structure name'} placeholder={t.structures_name || 'Structure name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <select className="form-input" aria-label={t.structures_applies || 'Applies to'} value={form.data.applies_to} onChange={(e) => form.setData('applies_to', e.target.value)}>
                        {appliesTo.map((value) => <option key={value} value={value}>{appliesName(value)}</option>)}
                    </select>
                    <select className="form-input" aria-label={t.status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((value) => <option key={value} value={value}>{statusName(value)}</option>)}
                    </select>
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.structures_create || 'Create structure'}</button>
                </div>
                {form.data.applies_to === 'class' && (
                    <div className="flex flex-wrap gap-3 text-sm">
                        {classes.length === 0 && <span className="text-gray-500">{t.structures_no_classes || 'This year has no classes yet.'}</span>}
                        {classes.map((row) => (
                            <label key={row.id} className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.class_ids.map(String).includes(String(row.id))}
                                    onChange={() => toggleClass(row.id)}
                                />
                                {row.label}
                            </label>
                        ))}
                    </div>
                )}
                {form.data.items.map((item, index) => (
                    <div key={index} className="grid gap-3 md:grid-cols-6">
                        <select className="form-input" aria-label={t.structures_fee_item || 'Fee item'} value={item.fee_item_id} onChange={(e) => chooseItem(index, e.target.value)}>
                            {feeItems.map((feeItem) => <option key={feeItem.id} value={feeItem.id}>{named(feeItem)}</option>)}
                        </select>
                        <input className="form-input" inputMode="decimal" aria-label={t.amount || 'Amount'} placeholder={t.amount || 'Amount'} value={item.amount} onChange={(e) => setItem(index, 'amount', e.target.value)} />
                        <select className="form-input" aria-label={t.frequency || 'How often'} value={item.frequency} onChange={(e) => setItem(index, 'frequency', e.target.value)}>
                            {frequencies.map((frequency) => <option key={frequency} value={frequency}>{frequencyName(frequency)}</option>)}
                        </select>
                        <input className="form-input" inputMode="numeric" aria-label={t.structures_due_day || 'Due day'} placeholder={t.structures_due_day || 'Due day'} value={item.due_day ?? ''} onChange={(e) => setItem(index, 'due_day', e.target.value)} />
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={!!item.is_mandatory} onChange={(e) => setItem(index, 'is_mandatory', e.target.checked)} />
                            {t.mandatory || 'Mandatory'}
                        </label>
                        {form.data.items.length > 1 && (
                            <button type="button" className="btn-secondary" onClick={() => removeLine(index)}>{t.remove || 'Remove'}</button>
                        )}
                    </div>
                ))}
                {feeItems.length === 0 ? (
                    <p className="text-sm text-gray-500">{t.structures_no_fee_items || 'Add a fee item first: a structure bills the school’s fee items.'}</p>
                ) : (
                    <button type="button" className="btn-secondary" onClick={addLine}>{t.structures_add_line || 'Add a fee item'}</button>
                )}
                <FormErrors errors={form.errors} />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.structures_applies || 'Applies to'}</th>
                            <th className="px-3 py-2">{t.structures_classes || 'Classes'}</th>
                            <th className="px-3 py-2">{t.structures_items || 'Fee items'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {structures.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.structures_none || 'No fee structures yet.'}</td></tr>
                        )}
                        {structures.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{appliesName(row.applies_to)}</td>
                                <td className="px-3 py-2">{row.applies_to === 'all_classes' || (row.class_ids || []).length === 0 ? appliesName('all_classes') : listed(row.class_ids.map(classLabel))}</td>
                                <td className="px-3 py-2">{listed(row.items.map((line) => named(line))) || '—'}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
