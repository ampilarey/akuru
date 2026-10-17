import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL2a (§6): a class teacher checks their students' blog posts
 * first, oldest first. Approving sends a post on to the Library office;
 * sending back or refusing needs a note the student reads. The text was
 * sanitised when it was saved (SaveLibraryItemAction), as the peer
 * reviewer's screen shows it.
 */
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.split(`:${key}`).join(String(value ?? '')), String(text));

export default function TeacherBlogPosts({ posts = [], t = {} }) {
    const refusals = useRowRefusals();
    const [notes, setNotes] = useState({});
    const decide = (id, decision) => refusals.actOn(`post:${id}`, () => router.post(`/teach/blog-posts/${id}/decide`, { decision, comment: notes[id] || '' }, { preserveScroll: true }));

    return (
        <AppShell title={t.teacher_title || 'Students’ blog posts'}>
            <p className="mb-4 max-w-3xl text-sm text-gray-600">{t.teacher_intro || 'Posts your students sent, oldest first.'}</p>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            {posts.length === 0 && <p className="rounded-lg border bg-white p-4 text-sm text-gray-500" data-testid="teacher-posts-empty">{t.teacher_empty || 'No posts are waiting for you.'}</p>}
            <div className="grid max-w-3xl gap-4">
                {posts.map((p) => (
                    <article key={p.id} className="rounded-lg border bg-white p-4" data-testid={`teacher-post-${p.id}`}>
                        <h2 className="text-lg font-semibold">{p.title}</h2>
                        <p className="text-sm text-gray-600">
                            {fill(t.teacher_by || ':pen, written by :student', { pen: p.pen_name, student: p.student_name || '—' })}
                            {p.series ? ` · ${fill(t.series_line || ':series, part :n', { series: p.series, n: p.series_position })}` : ''}
                            {` · ${p.submitted_at}`}
                        </p>
                        <div className="mt-2 max-h-96 overflow-y-auto rounded border bg-gray-50 p-3 text-sm" dangerouslySetInnerHTML={{ __html: p.body || '' }} />
                        <textarea
                            className="form-input mt-3 w-full text-sm"
                            rows={2}
                            placeholder={t.teacher_comment_placeholder || 'Note to the student (needed to send back or refuse)'}
                            aria-label={t.teacher_comment_placeholder || 'Note to the student (needed to send back or refuse)'}
                            value={notes[p.id] || ''}
                            onChange={(e) => setNotes({ ...notes, [p.id]: e.target.value })}
                            data-testid={`teacher-post-note-${p.id}`}
                        />
                        <div className="mt-2 flex flex-wrap gap-2">
                            <button type="button" className="btn-primary" onClick={() => decide(p.id, 'approved')} data-testid={`teacher-post-approve-${p.id}`}>{t.btn_teacher_approve || 'Approve, send to the office'}</button>
                            <button type="button" className="btn-secondary" onClick={() => decide(p.id, 'changes_requested')} data-testid={`teacher-post-changes-${p.id}`}>{t.btn_teacher_changes || 'Send back for changes'}</button>
                            <button type="button" className="btn-secondary" onClick={() => decide(p.id, 'rejected')} data-testid={`teacher-post-refuse-${p.id}`}>{t.btn_teacher_refuse || 'Refuse'}</button>
                        </div>
                        <FormErrors errors={refusals.errorsFor(`post:${p.id}`)} className="mt-1" />
                    </article>
                ))}
            </div>
        </AppShell>
    );
}
