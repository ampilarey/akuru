import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL6a: the Library office's tips — every paid tip and refund,
 * the writer's share, and the thank-you notes waiting to be checked. A note
 * reaches the writer only once it is published here.
 */
export default function BlogTips({ tips = [], notes = false, t = {} }) {
    const refusals = useRowRefusals();
    const decide = (id, decision) => refusals.actOn(`tip:${id}`, () => router.post(`/admin/library/tips/${id}/note`, { decision }, { preserveScroll: true }));
    const col = { tip: t.col_tip || 'Tip', blog: t.col_blog || 'Blog', note: t.col_note || 'Note', actions: t.col_actions || 'Actions' };

    return (
        <AppShell title={t.tips_admin_title || 'Blog tips'}>
            <p className="mb-4 text-sm text-gray-600">{t.tips_admin_intro || 'Tips are paid on the bank’s confirmation, never the return page. A refund adds a reversing row; nothing is edited.'}</p>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={notes} onChange={(e) => router.get('/admin/library/tips', e.target.checked ? { notes: 1 } : {}, { preserveState: true })} data-testid="tips-notes-filter" />
                    {t.tips_filter_notes || 'Only notes waiting to be checked'}
                </label>
                <a className="btn-secondary" href="/admin/library/tips/export" data-testid="tips-export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="blog-tips-admin">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.tip}</th>
                            <th className="px-3 py-2">{col.blog}</th>
                            <th className="px-3 py-2">{col.note}</th>
                            <th className="px-3 py-2">{col.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tips.length === 0 && <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.tips_none || 'No tips yet.'}</td></tr>}
                        {tips.map((tip) => (
                            <tr key={tip.id} className="border-t align-top" data-testid={`admin-tip-${tip.id}`}>
                                <td className="px-3 py-2" data-label={col.tip}>
                                    <p className="font-medium">MVR {tip.amount.toFixed(2)} · {t[`tip_method_${tip.method}`] || tip.method}</p>
                                    <p className="text-xs text-gray-500">{tip.at} · {tip.from}</p>
                                    {tip.refund_of && <p className="text-xs text-amber-800">{(t.tips_refund_of || 'refund of tip :id').replace(':id', tip.refund_of)}</p>}
                                    {tip.writer_share !== null && <p className="text-xs text-gray-600">{(t.tips_writer_share || 'writer’s share MVR :amount').replace(':amount', tip.writer_share.toFixed(2))} · {tip.earning_status}</p>}
                                </td>
                                <td className="px-3 py-2" data-label={col.blog}>{tip.blog}{tip.post ? <p className="text-xs text-gray-600">{tip.post}</p> : null}</td>
                                <td className="px-3 py-2" data-label={col.note}>
                                    {tip.note ? <p className="italic">{tip.note}</p> : '—'}
                                    {tip.note && <p className="text-xs text-gray-500" data-testid={`admin-tip-note-status-${tip.id}`}>{t[`comment_status_${tip.note_status}`] || tip.note_status}</p>}
                                </td>
                                <td className="table-actions px-3 py-2" data-label={col.actions}>
                                    {tip.note && tip.note_status === 'pending' && (
                                        <div className="flex flex-wrap gap-2">
                                            <button type="button" className="btn-primary" onClick={() => decide(tip.id, 'publish')} data-testid={`admin-tip-publish-${tip.id}`}>{t.comment_publish || 'Publish'}</button>
                                            <button type="button" className="btn-secondary" onClick={() => decide(tip.id, 'refuse')} data-testid={`admin-tip-refuse-${tip.id}`}>{t.refuse || 'Refuse'}</button>
                                        </div>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`tip:${tip.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
