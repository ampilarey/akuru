import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * Moodle parity slice M3 (STATUS §5oj): one discussion topic, its replies in
 * order, a reply box, and — for a moderator — pin, lock and hide.
 */
function when(iso) {
    return iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
}

function Byline({ item, t }) {
    return (
        <p className="mb-2 flex flex-wrap items-center gap-2 text-xs text-gray-600">
            <span className="font-medium text-gray-800">{item.author || '—'}</span>
            {item.author_is_teacher && <span className="rounded bg-[#7C2D37] px-1 text-white">{t.teacher || 'Teacher'}</span>}
            <span>{when(item.created_at)}</span>
            {item.hidden && <span className="rounded bg-red-50 px-1 text-red-800">{t.hidden || 'Hidden'}</span>}
        </p>
    );
}

export default function Topic({ course, role, topic, posts = [], can_reply: canReply, t = {} }) {
    const form = useForm({ body: '' });
    const moderator = role === 'moderator';
    const base = `/learn/courses/${course.id}/forum`;
    // A refused moderation step was shown nowhere (slice CT6b-2b); it is said
    // beside the topic or the post it was pressed on.
    const refusals = useRowRefusals(form);
    const moderate = (action) => refusals.actOn('topic', () => router.post(`${base}/${topic.id}/moderate`, { action }, { preserveScroll: true }));
    const moderatePost = (post, action) => refusals.actOn(`post:${post.id}`, () => router.post(`${base}/posts/${post.id}/moderate`, { action }, { preserveScroll: true }));

    return (
        <AppShell title={`${topic.title} · ${course.title}`}>
            <a className="mb-4 inline-block text-sm text-[#7C2D37] hover:underline" href={base}>← {t.back || 'All topics'}</a>

            <article className={`mb-4 rounded-lg border bg-white p-4 ${topic.hidden ? 'opacity-60' : ''}`} data-testid="forum-topic">
                <h1 className="mb-1 text-lg font-medium">{topic.title}</h1>
                <div className="mb-2 flex flex-wrap gap-2 text-xs">
                    {topic.pinned && <span className="rounded bg-[#F3EBE0] px-1">{t.pinned || 'Pinned'}</span>}
                    {topic.locked && <span className="rounded bg-gray-100 px-1">{t.locked_badge || 'Locked'}</span>}
                </div>
                <Byline item={topic} t={t} />
                <p className="whitespace-pre-wrap text-sm">{topic.body}</p>
                {moderator && (
                    <div className="mt-3 flex flex-wrap gap-2" data-testid="forum-moderate">
                        <button type="button" className="btn-secondary" onClick={() => moderate(topic.pinned ? 'unpin' : 'pin')}>{topic.pinned ? (t.unpin || 'Unpin') : (t.pin || 'Pin')}</button>
                        <button type="button" className="btn-secondary" onClick={() => moderate(topic.locked ? 'unlock' : 'lock')}>{topic.locked ? (t.unlock || 'Unlock') : (t.lock || 'Lock')}</button>
                        <button type="button" className="btn-secondary" onClick={() => moderate(topic.hidden ? 'show' : 'hide')}>{topic.hidden ? (t.show || 'Show') : (t.hide || 'Hide')}</button>
                    </div>
                )}
                <FormErrors errors={refusals.errorsFor('topic')} className="mt-2" />
            </article>

            <ul className="mb-6 space-y-3" data-testid="forum-posts">
                {posts.map((post) => (
                    <li key={post.id} className={`rounded-lg border bg-white p-3 ${post.hidden ? 'opacity-60' : ''}`}>
                        <Byline item={post} t={t} />
                        <p className="whitespace-pre-wrap text-sm">{post.body}</p>
                        {moderator && (
                            <button type="button" className="mt-2 text-xs text-[#7C2D37] hover:underline" onClick={() => moderatePost(post, post.hidden ? 'show' : 'hide')}>
                                {post.hidden ? (t.show || 'Show') : (t.hide || 'Hide')}
                            </button>
                        )}
                        <FormErrors errors={refusals.errorsFor(`post:${post.id}`)} className="mt-1" />
                    </li>
                ))}
            </ul>

            {topic.locked && !moderator && <p className="mb-3 text-sm text-gray-600" data-testid="forum-locked">{t.locked_note}</p>}
            {canReply && (
                <form
                    className="space-y-3 rounded-lg border bg-white p-4"
                    data-testid="forum-reply"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`${base}/${topic.id}/replies`, { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                >
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.your_reply || 'Your reply'}</span>
                        <textarea className="form-input w-full" name="body" rows={3} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} maxLength={10000} required />
                    </label>
                    <FormErrors errors={form.errors} />
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.reply || 'Reply'}</button>
                </form>
            )}
        </AppShell>
    );
}
