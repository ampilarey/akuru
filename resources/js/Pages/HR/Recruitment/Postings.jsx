import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

const EMPLOYMENT = ['full_time', 'part_time', 'contract', 'volunteer'];

/**
 * Job postings. Every word is the `hr` book's (slice HR2, STATUS §5qm); a
 * posting's state and kind of work are named rather than printed as codes,
 * and a posting reads by the title the school gave it in the page's
 * language — the form takes its Dhivehi and Arabic titles, which the posting
 * had a place for and no box.
 */
export default function Postings({ rows, statuses, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const titled = (row) => ({ dv: row?.title_dhivehi, ar: row?.title_arabic }[locale]) || row?.title;
    const statusName = (status) => t[`posting_status_${status}`] || status;
    const form = useForm({
        title: '',
        title_arabic: '',
        title_dhivehi: '',
        department: '',
        employment_type: 'full_time',
        status: 'draft',
        public: false,
        closes_at: '',
        description: '',
    });

    return (
        <AppShell title={t.postings_title || 'Job postings'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/postings/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/postings', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" aria-label={t.title_en || 'Title (EN)'} placeholder={t.title_en || 'Title (EN)'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.title_dv || 'Title (DV)'} placeholder={t.title_dv || 'Title (DV)'} value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.title_ar || 'Title (AR)'} placeholder={t.title_ar || 'Title (AR)'} value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                <input className="form-input" aria-label={t.department || 'Department'} placeholder={t.department || 'Department'} value={form.data.department} onChange={(e) => form.setData('department', e.target.value)} />
                <select className="form-input" aria-label={t.postings_employment || 'Kind of work'} value={form.data.employment_type} onChange={(e) => form.setData('employment_type', e.target.value)}>
                    {EMPLOYMENT.map((kind) => <option key={kind} value={kind}>{t[`employment_${kind}`] || kind}</option>)}
                </select>
                <select className="form-input" aria-label={t.status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{statusName(status)}</option>)}
                </select>
                <input type="date" className="form-input" aria-label={t.postings_closes || 'Closes'} value={form.data.closes_at} onChange={(e) => form.setData('closes_at', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.public} onChange={(e) => form.setData('public', e.target.checked)} />
                    {t.postings_public || 'Public'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.postings_save || 'Save posting'}</button>
                <FormErrors errors={form.errors} className="md:col-span-4" />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.postings_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.department || 'Department'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.postings_public || 'Public'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.postings_none || 'No job postings yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{titled(row)}</td>
                                <td className="px-3 py-2">{row.department || '—'}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                                <td className="px-3 py-2">{row.public ? (t.yes || 'yes') : (t.no || 'no')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
