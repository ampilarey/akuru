import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

export default function Appraisals({ staff, appraisals, observations, cpd, cpdSummary = null, t = {} }) {
    // In the page's language (BACKLOG C21, slice PT4). A cycle's name, an
    // observation's summary and a course's title are what the school wrote.
    const col = {
        cycle: t.col_cycle || 'Cycle',
        status: t.col_status || 'Status',
        acknowledge: t.appraisals_acknowledge || 'Acknowledge',
        date: t.col_date || 'Date',
        class: t.col_class || 'Class',
        summary: t.col_summary || 'Summary',
        title: t.col_title || 'Title',
        hours: t.col_hours || 'Hours',
    };
    const cpdLine = cpdSummary
        ? (cpdSummary.records_this_year === 1
            ? (t.appraisals_cpd_one || ':hours hours this academic year across 1 record; :total hours all time.')
            : (t.appraisals_cpd_many || ':hours hours this academic year across :records records; :total hours all time.'))
            .replace(':hours', cpdSummary.hours_this_year)
            .replace(':records', cpdSummary.records_this_year)
            .replace(':total', cpdSummary.hours_total)
        : null;

    return (
        <AppShell title={t.appraisals_title || 'My performance'}>
            {!staff && <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.staff_no_profile || 'No staff profile is linked to this account.'}</p>}
            {staff && (
                <>
                    <h2 className="mb-2 font-medium">{t.appraisals_heading || 'Appraisals'}</h2>
                    <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">{col.cycle}</th>
                                    <th className="px-3 py-2">{col.status}</th>
                                    <th className="px-3 py-2">{col.acknowledge}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {appraisals.map((row) => (
                                    <AppraisalRow key={row.id} row={row} t={t} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <h2 className="mb-2 font-medium">{t.appraisals_observations || 'Shared observations'}</h2>
                    <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">{col.date}</th>
                                    <th className="px-3 py-2">{col.class}</th>
                                    <th className="px-3 py-2">{col.summary}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {observations.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-3 py-2">{row.date}</td>
                                        <td className="px-3 py-2">{row.class_name || '—'}</td>
                                        <td className="px-3 py-2">{row.summary}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <h2 className="mb-2 font-medium">{t.appraisals_cpd || 'Professional development'}</h2>
                    {cpdLine && (
                        <p className="mb-2 text-sm text-gray-700" data-testid="cpd-summary">{cpdLine}</p>
                    )}
                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">{col.title}</th>
                                    <th className="px-3 py-2">{col.hours}</th>
                                    <th className="px-3 py-2">{col.date}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {cpd.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-3 py-2">{row.title}</td>
                                        <td className="px-3 py-2">{row.hours}</td>
                                        <td className="px-3 py-2">{row.date}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </AppShell>
    );
}

function AppraisalRow({ row, t }) {
    const form = useForm({ staff_comment: '' });

    return (
        <tr className="border-t">
            <td className="px-3 py-2">{row.cycle_name}</td>
            <td className="px-3 py-2">{t[`appraisal_status_${row.status}`] || row.status}</td>
            <td className="px-3 py-2">
                {row.status !== 'acknowledged' && (
                    <form
                        className="flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/portal/appraisals/${row.id}/acknowledge`);
                        }}
                    >
                        <input
                            className="form-input"
                            placeholder={t.appraisals_comment || 'Comment'}
                            aria-label={t.appraisals_comment || 'Comment'}
                            value={form.data.staff_comment}
                            onChange={(e) => form.setData('staff_comment', e.target.value)}
                        />
                        <button type="submit" className="btn-secondary" disabled={form.processing}>{t.appraisals_acknowledge || 'Acknowledge'}</button>
                        <FormErrors errors={form.errors} />
                    </form>
                )}
            </td>
        </tr>
    );
}
