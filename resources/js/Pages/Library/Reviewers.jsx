import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * RESEARCH_ARTICLES_PLAN R3b: the peer-reviewer pool. Everyone holding the
 * reviewer role, with what they have open, what they have finished and how
 * long they take; add by email, remove when nothing is open.
 */
export default function Reviewers({ reviewers = [], t = {} }) {
    const { errors = {} } = usePage().props;
    const form = useForm({ email: '' });
    const remove = (reviewer) => {
        if (window.confirm((t.library_reviewers_remove_confirm || 'Remove :name from the reviewer pool?').replace(':name', reviewer.name))) {
            router.delete(`/admin/library/reviewers/${reviewer.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppShell title={t.library_reviewers_title || 'Peer reviewers'}>
            <p className="mb-4 text-sm text-gray-600">{t.library_reviewers_intro || 'The people who peer-review research. Assigning someone by email on a submission also adds them here.'}</p>
            <FormErrors errors={errors} className="mb-4" />
            <form
                onSubmit={(e) => { e.preventDefault(); form.post('/admin/library/reviewers', { preserveScroll: true, onSuccess: () => form.reset() }); }}
                className="mb-4 flex flex-wrap items-center gap-2"
                data-testid="reviewer-add"
            >
                <input className="form-input w-64" type="email" placeholder={t.library_reviewers_email || 'Email of an Akuru account'} aria-label={t.library_reviewers_email || 'Email of an Akuru account'} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.library_reviewers_add || 'Add reviewer'}</button>
                <a className="ms-auto text-sm underline" href="/admin/library/reviewers/export">{t.library_insights_export || 'Export CSV'}</a>
                <a className="text-sm underline" href="/admin/library">{t.library_settings_back || 'Back to the Library office'}</a>
            </form>
            {/* `relative`: the Remove column's sr-only heading is positioned, and
                an unpositioned scroller lets it escape to the page, which then
                measures 79px wider than a phone (ADMIN_PANEL.md M1, the same
                shape as STATUS §5jq's enrolments list). */}
            <div className="relative overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm" data-testid="reviewer-pool">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.library_reviewers_name || 'Reviewer'}</th>
                            <th className="px-3 py-2">{t.library_reviewers_open || 'Open'}</th>
                            <th className="px-3 py-2">{t.library_reviewers_overdue || 'Overdue'}</th>
                            <th className="px-3 py-2">{t.library_reviewers_done || 'Reported'}</th>
                            <th className="px-3 py-2">{t.library_reviewers_days || 'Average days to report'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.library_reviewers_remove || 'Remove'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {reviewers.length === 0 && (
                            <tr><td colSpan="6" className="px-3 py-4 text-gray-500">{t.library_reviewers_empty || 'No reviewers yet.'}</td></tr>
                        )}
                        {reviewers.map((reviewer) => (
                            <tr key={reviewer.id} className="border-t" data-testid="reviewer-row">
                                <td className="px-3 py-2"><span className="font-medium">{reviewer.name}</span><span className="block text-xs text-gray-500">{reviewer.email}</span></td>
                                <td className="px-3 py-2">{reviewer.open}</td>
                                <td className={`px-3 py-2 ${reviewer.overdue ? 'font-semibold text-red-700' : ''}`}>{reviewer.overdue}</td>
                                <td className="px-3 py-2">{reviewer.done}</td>
                                <td className="px-3 py-2">{reviewer.average_days ?? '—'}</td>
                                <td className="px-3 py-2 text-end">
                                    <button type="button" className="text-sm text-red-600 disabled:text-gray-400" disabled={reviewer.open > 0} title={reviewer.open > 0 ? (t.library_reviewers_busy || 'Has a report open') : undefined} onClick={() => remove(reviewer)}>{t.library_reviewers_remove || 'Remove'}</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
