import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL1: the Library office's list of students' blog requests. The
 * student's real name and number sit beside the pen name, so the office
 * knows whom it approves. Approving needs a parent's yes under 18;
 * refusing needs a note the student reads.
 */
export default function BlogRequests({ requests = [], statuses = [], status = '', t = {} }) {
    const refusals = useRowRefusals();
    const [notes, setNotes] = useState({});
    const statusName = (value) => t[`status_${value}`] || value;
    const decide = (id, decision) => refusals.actOn(`request:${id}`, () => router.post(`/admin/library/blog-requests/${id}/decide`, { decision, note: notes[id] || '' }, { preserveScroll: true }));
    const col = {
        pen: t.col_pen_name || 'Pen name',
        blog: t.col_blog || 'Blog',
        student: t.col_student || 'Student',
        age: t.col_under_18 || 'Under 18',
        status: t.col_status || 'Status',
        parent: t.col_parent || 'Parent',
        asked: t.col_asked || 'Asked',
        actions: t.col_actions || 'Actions',
    };

    return (
        <AppShell title={t.admin_title || 'Blog requests'}>
            <p className="mb-4 text-sm text-gray-600">{t.admin_intro || 'Students who asked to start a blog. Under 18, a parent agrees first.'}</p>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <select className="form-input" aria-label={col.status} value={status} onChange={(e) => router.get('/admin/library/blog-requests', e.target.value ? { status: e.target.value } : {}, { preserveState: true })} data-testid="blog-requests-filter">
                    <option value="">{t.filter_all || 'All'}</option>
                    {statuses.map((value) => <option key={value} value={value}>{statusName(value)}</option>)}
                </select>
                <a className="btn-secondary" href="/admin/library/blog-requests/export" data-testid="blog-requests-export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="blog-requests">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.pen}</th>
                            <th className="px-3 py-2">{col.blog}</th>
                            <th className="px-3 py-2">{col.student}</th>
                            <th className="px-3 py-2">{col.age}</th>
                            <th className="px-3 py-2">{col.status}</th>
                            <th className="px-3 py-2">{col.parent}</th>
                            <th className="px-3 py-2">{col.asked}</th>
                            <th className="px-3 py-2">{col.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {requests.length === 0 && <tr><td className="px-3 py-4 text-gray-500" colSpan={8}>{t.admin_empty || 'No requests.'}</td></tr>}
                        {requests.map((r) => (
                            <tr key={r.id} className="border-t align-top" data-testid={`blog-request-${r.id}`}>
                                <td className="px-3 py-2 font-medium" data-label={col.pen}>{r.pen_name}</td>
                                <td className="px-3 py-2" data-label={col.blog}>
                                    <p className="font-medium">{r.blog_title}</p>
                                    <p className="text-xs text-gray-600">{r.about}</p>
                                </td>
                                <td className="px-3 py-2" data-label={col.student}>{r.student_name}{r.student_number ? ` · ${r.student_number}` : ''}</td>
                                <td className="px-3 py-2" data-label={col.age}>{r.under_18 ? t.yes || 'Yes' : t.no || 'No'}</td>
                                <td className="px-3 py-2" data-label={col.status}>
                                    <span data-testid={`blog-request-status-${r.id}`}>{statusName(r.status)}</span>
                                    {r.no_guardian && <p className="mt-1 text-xs text-amber-800">{t.no_guardian_flag || 'No verified parent on the record'}</p>}
                                    {r.decision_note && <p className="mt-1 text-xs text-gray-600">{r.decision_note}</p>}
                                </td>
                                <td className="px-3 py-2" data-label={col.parent}>
                                    {r.guardian_name || '—'}
                                    {r.guardian_name && (
                                        <p className="text-xs text-gray-600">
                                            {r.show_photo ? t.photo_yes || 'Photo allowed' : t.photo_no || 'No photo'} · {r.show_school ? t.school_yes || 'School name allowed' : t.school_no || 'No school name'}
                                        </p>
                                    )}
                                </td>
                                <td className="px-3 py-2" data-label={col.asked}>{r.created_at}</td>
                                <td className="table-actions px-3 py-2" data-label={col.actions}>
                                    {['waiting_office', 'waiting_parent'].includes(r.status) && (
                                        <div className="grid gap-2">
                                            <input className="form-input text-sm" placeholder={t.note_placeholder || 'Note (needed to refuse)'} aria-label={t.note_placeholder || 'Note (needed to refuse)'} value={notes[r.id] || ''} onChange={(e) => setNotes({ ...notes, [r.id]: e.target.value })} data-testid={`blog-request-note-${r.id}`} />
                                            <div className="flex flex-wrap gap-2">
                                                {r.status === 'waiting_office' && <button type="button" className="btn-primary" onClick={() => decide(r.id, 'approve')} data-testid={`blog-request-approve-${r.id}`}>{t.approve || 'Approve'}</button>}
                                                <button type="button" className="btn-secondary" onClick={() => decide(r.id, 'refuse')} data-testid={`blog-request-refuse-${r.id}`}>{t.refuse || 'Refuse'}</button>
                                            </div>
                                        </div>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`request:${r.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
