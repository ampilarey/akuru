import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * Appraisal cycles and the appraisals in them. Every word is the `hr` book's
 * (slice HR2, STATUS §5qm); an appraisal's state is named rather than printed
 * as its code. A refused cycle or appraisal is said under its form — a
 * refused cycle was said nowhere, and an appraisal only when its cycle was
 * the reason.
 */
export default function Appraisals({ years, staff, cycles, rows, statuses, t = {} }) {
    const statusName = (status) => t[`appraisal_status_${status}`] || status;
    const cycleForm = useForm({
        name: '',
        academic_year_id: years[0]?.id || '',
        opens_at: '',
        closes_at: '',
    });
    const form = useForm({
        cycle_id: cycles[0]?.id || '',
        staff_profile_id: staff[0]?.id || '',
        strengths: '',
        development_areas: '',
        status: statuses[0] || 'draft',
    });

    return (
        <AppShell title={t.appraisals_title || 'Appraisals'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/appraisals/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    cycleForm.post('/hr/appraisals/cycles', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" aria-label={t.appraisals_cycle_name || 'Cycle name'} placeholder={t.appraisals_cycle_name || 'Cycle name'} value={cycleForm.data.name} onChange={(e) => cycleForm.setData('name', e.target.value)} />
                <select className="form-input" aria-label={t.year || 'Year'} value={cycleForm.data.academic_year_id} onChange={(e) => cycleForm.setData('academic_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <input type="date" className="form-input" aria-label={t.appraisals_opens || 'Opens'} value={cycleForm.data.opens_at} onChange={(e) => cycleForm.setData('opens_at', e.target.value)} />
                <input type="date" className="form-input" aria-label={t.appraisals_closes || 'Closes'} value={cycleForm.data.closes_at} onChange={(e) => cycleForm.setData('closes_at', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={cycleForm.processing}>{t.appraisals_open_cycle || 'Open cycle'}</button>
                <FormErrors errors={cycleForm.errors} className="md:col-span-4" />
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        cycle_id: data.cycle_id || cycles[0]?.id || '',
                        staff_profile_id: data.staff_profile_id || staff[0]?.id || '',
                    }));
                    form.post('/hr/appraisals', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.appraisals_cycle || 'Cycle'} value={form.data.cycle_id || cycles[0]?.id || ''} onChange={(e) => form.setData('cycle_id', e.target.value)}>
                    {cycles.map((cycle) => <option key={cycle.id} value={cycle.id}>{cycle.name}</option>)}
                </select>
                <select className="form-input" aria-label={t.staff_member || 'Staff member'} value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <input className="form-input" aria-label={t.appraisals_strengths || 'Strengths'} placeholder={t.appraisals_strengths || 'Strengths'} value={form.data.strengths} onChange={(e) => form.setData('strengths', e.target.value)} />
                <input className="form-input" aria-label={t.appraisals_development || 'Development areas'} placeholder={t.appraisals_development || 'Development areas'} value={form.data.development_areas} onChange={(e) => form.setData('development_areas', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing || cycles.length === 0}>{t.appraisals_save || 'Save appraisal'}</button>
                <FormErrors errors={form.errors} className="md:col-span-4" />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.appraisals_cycle || 'Cycle'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.appraisals_none || 'No appraisals yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.cycle_name}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
