import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL3a (D14): the Library office's list of blog page changes,
 * oldest first — what each page shows now beside what it would show. One
 * approval covers the whole set; sending it back needs a note the writer
 * reads.
 */
function Design({ design, t }) {
    const c = design.colors;

    return (
        <div className="text-xs">
            <p className="mb-1 flex items-center gap-1">
                {['page_bg', 'card_bg', 'accent', 'text'].map((slot) => <span key={slot} className="inline-block h-3 w-3 rounded border" style={{ background: c[slot] }} aria-hidden="true" />)}
                <span className="font-medium">{design.theme_name}</span>
                {design.accent && <span>· {design.accent}</span>}
                {design.font && <span>· {design.font}</span>}
            </p>
            {design.tagline && <p className="italic">{design.tagline}</p>}
            {design.about && <p className="mt-1 whitespace-pre-line text-gray-700">{design.about}</p>}
            <p className="mt-1 text-gray-600">{design.sections.filter((s) => s.on).map((s) => t[`section_${s.key}`] || s.key).join(' · ')}</p>
            {design.languages.length > 0 && <p className="text-gray-600">{design.languages.map((code) => t[`lang_${code}`] || code).join(' · ')}</p>}
        </div>
    );
}

export default function BlogPageChanges({ changes = [], t = {} }) {
    const refusals = useRowRefusals();
    const [notes, setNotes] = useState({});
    const decide = (id, decision) => refusals.actOn(`page:${id}`, () => router.post(`/admin/library/blog-pages/${id}/decide`, { decision, note: notes[id] || '' }, { preserveScroll: true }));
    const col = { blog: t.col_blog || 'Blog', now: t.col_page_now || 'Now', next: t.col_page_next || 'Would show', actions: t.col_actions || 'Actions' };

    return (
        <AppShell title={t.pages_title || 'Blog page changes'}>
            <p className="mb-4 text-sm text-gray-600">{t.pages_intro || 'Changes bloggers sent to their pages. Readers see a page as it was until you approve.'}</p>
            <div className="mb-4">
                <a className="btn-secondary" href="/admin/library/blog-pages/export" data-testid="blog-pages-export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="blog-pages">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.blog}</th>
                            <th className="px-3 py-2">{col.now}</th>
                            <th className="px-3 py-2">{col.next}</th>
                            <th className="px-3 py-2">{col.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {changes.length === 0 && <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.pages_empty || 'No page changes are waiting.'}</td></tr>}
                        {changes.map((c) => (
                            <tr key={c.id} className="border-t align-top" data-testid={`blog-page-${c.id}`}>
                                <td className="px-3 py-2" data-label={col.blog}>
                                    <a className="font-medium underline" href={`/library/authors/${c.slug}`}>{c.display_name}</a>
                                    {c.student && <p className="text-xs text-gray-600">{t.badge_student || 'Student writer'}</p>}
                                    <p className="text-xs text-gray-500">{c.submitted_at}</p>
                                </td>
                                <td className="px-3 py-2" data-label={col.now}><Design design={c.live} t={t} /></td>
                                <td className="px-3 py-2" data-label={col.next}><Design design={c.next} t={t} /></td>
                                <td className="table-actions px-3 py-2" data-label={col.actions}>
                                    <div className="grid gap-2">
                                        <input className="form-input text-sm" placeholder={t.note_placeholder || 'Note (needed to refuse)'} aria-label={t.note_placeholder || 'Note (needed to refuse)'} value={notes[c.id] || ''} onChange={(e) => setNotes({ ...notes, [c.id]: e.target.value })} data-testid={`blog-page-note-${c.id}`} />
                                        <div className="flex flex-wrap gap-2">
                                            <button type="button" className="btn-primary" onClick={() => decide(c.id, 'approve')} data-testid={`blog-page-approve-${c.id}`}>{t.approve || 'Approve'}</button>
                                            <button type="button" className="btn-secondary" onClick={() => decide(c.id, 'refuse')} data-testid={`blog-page-refuse-${c.id}`}>{t.refuse || 'Refuse'}</button>
                                        </div>
                                    </div>
                                    <FormErrors errors={refusals.errorsFor(`page:${c.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
