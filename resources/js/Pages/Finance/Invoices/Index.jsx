import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * A year's invoices: generating the drafts and issuing them. Every word is
 * the `finance` book's (slice FN1, STATUS §5qn); an invoice's, a structure's
 * and a plan's state are named rather than printed as codes, a year's period
 * is named (it printed *year-2026*), and an optional fee reads by the name the
 * school gave it in the page's language. A refused run is said under its
 * form — only a missing structure was, so a period ending before it starts
 * was refused with nothing on the page — and a refused issue beside its
 * button.
 */
export default function Index({ years, yearId, structures, structureId, invoices, period_start = '', period_end = '', monthlyMode = 'per_month', t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const statusName = (status) => t[`invoice_status_${status}`] || status;
    const structureStatus = (status) => t[`structure_status_${status}`] || status;
    const planStatus = (status) => t[`plan_status_${status}`] || status;
    const periodName = (key) => {
        const year = /^year-(\d{4})$/.exec(key || '');
        if (year) return (t.invoices_period_year || 'Year :year').replace(':year', year[1]);
        const term = /^term-(\d+)$/.exec(key || '');
        if (term) return (t.invoices_period_term || 'Term :term').replace(':term', term[1]);
        return key || '—';
    };
    const form = useForm({
        academic_year_id: yearId || '',
        fee_structure_id: structureId || structures[0]?.id || '',
        period_start: period_start || '',
        period_end: period_end || '',
        monthly_mode: monthlyMode,
        include_optional: false,
        optional_item_ids: [],
    });
    const refusals = useRowRefusals(form);

    const drafts = invoices.filter((row) => row.status === 'draft');
    // S4.2: "optional items appear at invoice generation as toggles" — one per
    // optional item on the chosen structure, beside the all-or-nothing box.
    const chosen = structures.find((row) => String(row.id) === String(form.data.fee_structure_id));
    const optionalItems = (chosen?.items || []).filter((item) => !item.is_mandatory);
    const toggleOptional = (id) => {
        const current = form.data.optional_item_ids.map(String);
        form.setData('optional_item_ids', current.includes(String(id))
            ? form.data.optional_item_ids.filter((value) => String(value) !== String(id))
            : [...form.data.optional_item_ids, id]);
    };

    const issueAll = () => refusals.actOn('issue', () => router.post('/finance/invoices/issue', {
        invoice_ids: drafts.map((row) => row.id),
        academic_year_id: yearId,
        fee_structure_id: structureId || form.data.fee_structure_id,
    }, { preserveScroll: true }));

    return (
        <AppShell title={t.invoices_title || 'Invoices'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/invoices', { academic_year_id: year.id })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <div className="flex flex-wrap items-start gap-2">
                    <div>
                        <button type="button" className="btn-secondary" onClick={issueAll} disabled={drafts.length === 0}>{t.invoices_issue || 'Issue drafts'}</button>
                        <FormErrors errors={refusals.errorsFor('issue')} className="mt-1" />
                    </div>
                    <a className="btn-secondary" href={`/finance/invoices/export?academic_year_id=${yearId || ''}&fee_structure_id=${structureId || ''}`}>{t.export_csv || 'Export CSV'}</a>
                </div>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/invoices/generate', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" aria-label={t.invoices_structure || 'Fee structure'} value={form.data.fee_structure_id} onChange={(e) => form.setData('fee_structure_id', e.target.value)}>
                    <option value="">{t.invoices_structure || 'Fee structure'}</option>
                    {structures.map((row) => <option key={row.id} value={row.id}>{row.name} ({structureStatus(row.status)})</option>)}
                </select>
                <input className="form-input" type="date" aria-label={t.invoices_period_start || 'Period starts'} value={form.data.period_start} onChange={(e) => form.setData('period_start', e.target.value)} />
                <input className="form-input" type="date" aria-label={t.invoices_period_end || 'Period ends'} value={form.data.period_end} onChange={(e) => form.setData('period_end', e.target.value)} />
                <select className="form-input" aria-label={t.invoices_monthly || 'Monthly fees'} value={form.data.monthly_mode} onChange={(e) => form.setData('monthly_mode', e.target.value)}>
                    <option value="per_month">{t.invoices_mode_per_month || 'One invoice per month'}</option>
                    <option value="consolidated">{t.invoices_mode_consolidated || 'Consolidate monthly items'}</option>
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={!!form.data.include_optional} onChange={(e) => form.setData('include_optional', e.target.checked)} />
                    {t.invoices_include_optional || 'Include all optional items'}
                </label>
                {!form.data.include_optional && optionalItems.length > 0 && (
                    <div className="flex flex-wrap gap-3 text-sm md:col-span-3">
                        <span className="text-gray-600">{t.invoices_only_these || 'Or only these:'}</span>
                        {optionalItems.map((item) => (
                            <label key={item.id} className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.optional_item_ids.map(String).includes(String(item.fee_item_id))}
                                    onChange={() => toggleOptional(item.fee_item_id)}
                                />
                                {named(item) || (t.invoices_item || 'Item :id').replace(':id', item.fee_item_id)} ({item.amount})
                            </label>
                        ))}
                    </div>
                )}
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.invoices_generate || 'Generate drafts'}</button>
                <FormErrors errors={form.errors} className="md:col-span-3" />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.invoices_col_number || 'Number'}</th>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            <th className="px-3 py-2">{t.invoices_col_period || 'Period'}</th>
                            <th className="px-3 py-2">{t.invoices_col_due || 'Due'}</th>
                            <th className="px-3 py-2">{t.invoices_col_total || 'Total'}</th>
                            <th className="px-3 py-2">{t.invoices_col_paid || 'Paid'}</th>
                            <th className="px-3 py-2">{t.invoices_col_plan || 'Plan'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={8}>{t.invoices_none || 'No invoices for this year.'}</td></tr>
                        )}
                        {invoices.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.invoice_number}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{periodName(row.period_key)}</td>
                                <td className="px-3 py-2">{row.due_date}</td>
                                <td className="px-3 py-2">{row.total_amount}</td>
                                <td className="px-3 py-2">{row.paid_amount}</td>
                                <td className="px-3 py-2">{row.plan
                                    ? (t.invoices_plan_progress || ':paid of :total installments paid · :status')
                                        .replace(':paid', row.plan.paid).replace(':total', row.plan.total).replace(':status', planStatus(row.plan.status))
                                    : '—'}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
