import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * The school's fee items. Every word is the `finance` book's (slice FN1,
 * STATUS §5qn); a fee's kind and how often it falls due are named rather than
 * printed as codes, and an item reads by the name the school gave it in the
 * page's language. A refused item is said under the form — only its name's
 * and amount's refusals were.
 */
export default function Index({ items, types, frequencies, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const typeName = (type) => t[`fee_type_${type}`] || type;
    const frequencyName = (frequency) => t[`frequency_${frequency}`] || frequency;
    const form = useForm({
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        default_amount: '',
        type: types[0] || 'tuition',
        frequency: frequencies[0] || 'one_time',
        is_mandatory: true,
        is_active: true,
    });

    return (
        <AppShell title={t.fee_items_title || 'Fee items'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/finance/fee-items/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/fee-items', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" aria-label={t.name_en || 'Name (EN)'} placeholder={t.name_en || 'Name (EN)'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.name_dv || 'Name (DV)'} placeholder={t.name_dv || 'Name (DV)'} value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.name_ar || 'Name (AR)'} placeholder={t.name_ar || 'Name (AR)'} value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                <input className="form-input" inputMode="decimal" aria-label={t.amount || 'Amount'} placeholder={t.amount || 'Amount'} value={form.data.default_amount} onChange={(e) => form.setData('default_amount', e.target.value)} />
                <select className="form-input" aria-label={t.fee_items_type || 'Kind of fee'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{typeName(type)}</option>)}
                </select>
                <select className="form-input" aria-label={t.frequency || 'How often'} value={form.data.frequency} onChange={(e) => form.setData('frequency', e.target.value)}>
                    {frequencies.map((frequency) => <option key={frequency} value={frequency}>{frequencyName(frequency)}</option>)}
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={!!form.data.is_mandatory} onChange={(e) => form.setData('is_mandatory', e.target.checked)} />
                    {t.mandatory || 'Mandatory'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.fee_items_create || 'Create fee item'}</button>
                <FormErrors errors={form.errors} className="md:col-span-4" />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.amount || 'Amount'}</th>
                            <th className="px-3 py-2">{t.fee_items_type || 'Kind of fee'}</th>
                            <th className="px-3 py-2">{t.frequency || 'How often'}</th>
                            <th className="px-3 py-2">{t.mandatory || 'Mandatory'}</th>
                            <th className="px-3 py-2">{t.fee_items_active || 'Active'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.fee_items_none || 'No fee items yet.'}</td></tr>
                        )}
                        {items.map((item) => (
                            <tr key={item.id} className="border-t">
                                <td className="px-3 py-2">{named(item)}</td>
                                <td className="px-3 py-2">{item.default_amount} {item.currency}</td>
                                <td className="px-3 py-2">{typeName(item.type)}</td>
                                <td className="px-3 py-2">{frequencyName(item.frequency)}</td>
                                <td className="px-3 py-2">{item.is_mandatory ? (t.yes || 'yes') : (t.no || 'no')}</td>
                                <td className="px-3 py-2">{item.is_active ? (t.yes || 'yes') : (t.no || 'no')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
