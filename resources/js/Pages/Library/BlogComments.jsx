import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL5 (§6): the Library office's comments list — waiting and
 * reported first. Publish, refuse with a note the commenter reads, or hide.
 * Nothing shows on a post before it is published here.
 */
export default function BlogComments({ comments = [], statuses = [], status = '', t = {} }) {
    const refusals = useRowRefusals();
    const [notes, setNotes] = useState({});
    const decide = (id, decision) => refusals.actOn(`comment:${id}`, () => router.post(`/admin/library/comments/${id}/decide`, { decision, note: notes[id] || '' }, { preserveScroll: true }));
    const statusName = (value) => t[`comment_status_${value}`] || value;
    const col = { post: t.col_post || 'Post', comment: t.col_comment || 'Comment', status: t.col_status || 'Status', actions: t.col_actions || 'Actions' };

    return (
        <AppShell title={t.comments_admin_title || 'Blog comments'}>
            <p className="mb-4 text-sm text-gray-600">{t.comments_admin_intro || 'Every comment and reply waits here before readers see it. A reported comment is off the page until you decide.'}</p>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <select className="form-input" aria-label={col.status} value={status} onChange={(e) => router.get('/admin/library/comments', e.target.value ? { status: e.target.value } : {}, { preserveState: true })} data-testid="comments-filter">
                    <option value="">{t.comments_filter_waiting || 'Waiting and reported'}</option>
                    {statuses.map((value) => <option key={value} value={value}>{statusName(value)}</option>)}
                </select>
                <a className="btn-secondary" href={`/admin/library/comments/export${status ? `?status=${status}` : ''}`} data-testid="comments-export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="blog-comments">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.post}</th>
                            <th className="px-3 py-2">{col.comment}</th>
                            <th className="px-3 py-2">{col.status}</th>
                            <th className="px-3 py-2">{col.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {comments.length === 0 && <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.comments_admin_empty || 'No comments are waiting.'}</td></tr>}
                        {comments.map((c) => (
                            <tr key={c.id} className="border-t align-top" data-testid={`blog-comment-${c.id}`}>
                                <td className="px-3 py-2" data-label={col.post}>
                                    <a className="font-medium underline" href={`/library/${c.slug}`}>{c.post}</a>
                                    <p className="text-xs text-gray-600">{c.blog}</p>
                                </td>
                                <td className="px-3 py-2" data-label={col.comment}>
                                    <p className="text-xs text-gray-500">{c.commenter}{c.reply ? ` · ${t.comment_owner_reply || 'the blog’s reply'}` : ''} · {c.created_at}</p>
                                    <p className="whitespace-pre-line">{c.body}</p>
                                </td>
                                <td className="px-3 py-2" data-label={col.status}>
                                    <span data-testid={`blog-comment-status-${c.id}`}>{statusName(c.status)}</span>
                                    {c.report_count > 0 && <p className="text-xs text-amber-800">{(t.comment_reports || 'Reported :count times').replace(':count', c.report_count)}</p>}
                                    {c.decision_note && <p className="text-xs text-gray-600">{c.decision_note}</p>}
                                </td>
                                <td className="table-actions px-3 py-2" data-label={col.actions}>
                                    <div className="grid gap-2">
                                        <input className="form-input text-sm" placeholder={t.note_placeholder || 'Note (needed to refuse)'} aria-label={t.note_placeholder || 'Note (needed to refuse)'} value={notes[c.id] || ''} onChange={(e) => setNotes({ ...notes, [c.id]: e.target.value })} data-testid={`blog-comment-note-${c.id}`} />
                                        <div className="flex flex-wrap gap-2">
                                            {c.status !== 'published' && <button type="button" className="btn-primary" onClick={() => decide(c.id, 'publish')} data-testid={`blog-comment-publish-${c.id}`}>{t.comment_publish || 'Publish'}</button>}
                                            {c.status !== 'refused' && <button type="button" className="btn-secondary" onClick={() => decide(c.id, 'refuse')} data-testid={`blog-comment-refuse-${c.id}`}>{t.refuse || 'Refuse'}</button>}
                                            {c.status !== 'hidden' && <button type="button" className="btn-secondary" onClick={() => decide(c.id, 'hide')} data-testid={`blog-comment-hide-${c.id}`}>{t.comment_hide || 'Hide'}</button>}
                                        </div>
                                    </div>
                                    <FormErrors errors={refusals.errorsFor(`comment:${c.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
