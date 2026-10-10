import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * Applications for the school's job postings, and hiring from them. Every
 * word is the `hr` book's (slice HR2, STATUS §5qm); an application's state is
 * named rather than printed as its code, and a posting reads by the title the
 * school gave it in the page's language. A refused Hire is said under its row
 * — an applicant with no email was refused with nothing on the page.
 */
export default function Applications({ postings, statuses, rows, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const titled = (row) => ({ dv: row?.title_dhivehi, ar: row?.title_arabic }[locale]) || row?.title;
    const statusName = (status) => t[`application_status_${status}`] || status;
    const postingTitle = (id, fallback) => titled(postings.find((posting) => `${posting.id}` === `${id}`)) || fallback;
    const form = useForm({
        job_posting_id: postings[0]?.id || '',
        name: '',
        email: '',
        mobile: '',
        cover_note: '',
        status: 'received',
    });
    const refusals = useRowRefusals(form);

    return (
        <AppShell title={t.applications_title || 'Job applications'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/applications/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/applications', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.applications_job || 'Job'} value={form.data.job_posting_id} onChange={(e) => form.setData('job_posting_id', e.target.value)}>
                    {postings.map((posting) => <option key={posting.id} value={posting.id}>{titled(posting)}</option>)}
                </select>
                <input className="form-input" aria-label={t.name || 'Name'} placeholder={t.name || 'Name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" dir="ltr" aria-label={t.applications_email || 'Email'} placeholder={t.applications_email || 'Email'} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                <input className="form-input" dir="ltr" aria-label={t.applications_mobile || 'Mobile'} placeholder={t.applications_mobile || 'Mobile'} value={form.data.mobile} onChange={(e) => form.setData('mobile', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.applications_record || 'Record application'}</button>
                <FormErrors errors={form.errors} className="md:col-span-4" />
            </form>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.name || 'Name'}</th>
                            <th className="px-3 py-2">{t.applications_job || 'Job'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.applications_hire || 'Hire'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.applications_none || 'No applications yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{postingTitle(row.job_posting_id, row.job_title)}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                                <td className="px-3 py-2 text-end">
                                    {row.status !== 'hired' && (
                                        <button type="button" className="btn-secondary" onClick={() => refusals.actOn(`application:${row.id}`, () => router.post(`/hr/applications/${row.id}/hire`, {}, { preserveScroll: true }))}>{t.applications_hire || 'Hire'}</button>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`application:${row.id}`)} className="mt-1 text-start" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
