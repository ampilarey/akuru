import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * A member of staff's professional development: the records and the hours
 * they add up to. Every word is the `hr` book's (slice HR2, STATUS §5qm); a
 * course's title and provider are the office's.
 */
export default function Cpd({ staff, rows, summary = [], t = {} }) {
    const form = useForm({
        staff_profile_id: staff[0]?.id || '',
        title: '',
        provider: '',
        hours: '',
        date: '',
    });

    return (
        <AppShell title={t.cpd_title || 'CPD records'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/cpd/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/cpd', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <select className="form-input" aria-label={t.staff_member || 'Staff member'} value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <input className="form-input" aria-label={t.cpd_course || 'Title'} placeholder={t.cpd_course || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input" aria-label={t.cpd_provider || 'Provider'} placeholder={t.cpd_provider || 'Provider'} value={form.data.provider} onChange={(e) => form.setData('provider', e.target.value)} />
                <input className="form-input" aria-label={t.cpd_hours || 'Hours'} placeholder={t.cpd_hours || 'Hours'} value={form.data.hours} onChange={(e) => form.setData('hours', e.target.value)} />
                <input type="date" className="form-input" aria-label={t.date || 'Date'} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.cpd_save || 'Save CPD'}</button>
                <FormErrors errors={form.errors} className="md:col-span-5" />
            </form>
            <div className="mb-4">
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="font-medium">{t.cpd_hours_per_staff || 'Hours per staff member'}</h2>
                    <a className="btn-secondary" href="/hr/cpd/summary/export">{t.cpd_export_summary || 'Export summary CSV'}</a>
                </div>
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm" data-testid="cpd-summary">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                                <th className="px-3 py-2">{t.cpd_hours_year || 'Hours this year'}</th>
                                <th className="px-3 py-2">{t.cpd_records_year || 'Records this year'}</th>
                                <th className="px-3 py-2">{t.cpd_hours_total || 'Hours all time'}</th>
                                <th className="px-3 py-2">{t.cpd_records_total || 'Records all time'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {summary.length === 0 && (
                                <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.cpd_none || 'No CPD recorded yet.'}</td></tr>
                            )}
                            {summary.map((row) => (
                                <tr key={row.staff_profile_id} className="border-t">
                                    <td className="px-3 py-2">{row.staff_name}</td>
                                    <td className="px-3 py-2">{row.hours_this_year}</td>
                                    <td className="px-3 py-2">{row.records_this_year}</td>
                                    <td className="px-3 py-2">{row.hours_total}</td>
                                    <td className="px-3 py-2">{row.records_total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            <h2 className="mb-2 font-medium">{t.cpd_records || 'Records'}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.cpd_course || 'Title'}</th>
                            <th className="px-3 py-2">{t.cpd_provider || 'Provider'}</th>
                            <th className="px-3 py-2">{t.cpd_hours || 'Hours'}</th>
                            <th className="px-3 py-2">{t.date || 'Date'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.provider}</td>
                                <td className="px-3 py-2">{row.hours}</td>
                                <td className="px-3 py-2">{row.date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
