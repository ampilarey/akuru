import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * Moodle parity slice M3 (STATUS §5oj): a course's discussion forum — its
 * topics, pinned first, and a form to start one. Learners and teachers of the
 * course only; teachers moderate.
 */
function when(iso) {
    if (!iso) {
        return '';
    }
    const date = new Date(iso);
    return date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

export default function Index({ course, role, topics = [], t = {} }) {
    const form = useForm({ title: '', body: '' });

    return (
        <AppShell title={`${t.title || 'Discussion'} · ${course.title}`}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <a className="text-sm text-[#7C2D37] hover:underline" href={`/learn/courses/${course.id}`}>← {course.title}</a>
            </div>
            <p className="mb-2 text-sm text-gray-600">{t.intro}</p>
            {role === 'moderator' && <p className="mb-4 text-xs text-gray-500" data-testid="forum-moderator">{t.moderator_note}</p>}

            <form
                className="mb-6 space-y-3 rounded-lg border bg-white p-4"
                data-testid="forum-new-topic"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/learn/courses/${course.id}/forum`, { onSuccess: () => form.reset() });
                }}
            >
                <h2 className="font-medium">{t.new_topic || 'Start a topic'}</h2>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.topic_title || 'Title'}</span>
                    <input className="form-input w-full" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} maxLength={200} required />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.topic_body || 'What would you like to say?'}</span>
                    <textarea className="form-input w-full" name="body" rows={4} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} maxLength={10000} required />
                </label>
                <FormErrors errors={form.errors} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.post || 'Post'}</button>
            </form>

            {topics.length === 0 && <p className="text-sm text-gray-500">{t.none || 'No topics yet. Start the first one.'}</p>}
            <ul className="space-y-2" data-testid="forum-topics">
                {topics.map((topic) => (
                    <li key={topic.id} className={`rounded-lg border bg-white p-3 ${topic.hidden ? 'opacity-60' : ''}`}>
                        <a className="font-medium text-[#7C2D37] hover:underline" href={`/learn/courses/${course.id}/forum/${topic.id}`}>{topic.title}</a>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-600">
                            {topic.pinned && <span className="rounded bg-[#F3EBE0] px-1">{t.pinned || 'Pinned'}</span>}
                            {topic.locked && <span className="rounded bg-gray-100 px-1">{t.locked_badge || 'Locked'}</span>}
                            {topic.hidden && <span className="rounded bg-red-50 px-1 text-red-800">{t.hidden || 'Hidden'}</span>}
                            <span>{(t.by || 'by :name').replace(':name', topic.author || '—')}</span>
                            {topic.author_is_teacher && <span className="rounded bg-[#7C2D37] px-1 text-white">{t.teacher || 'Teacher'}</span>}
                            <span>· {topic.replies === 1 ? (t.reply_one || '1 reply') : (t.replies || ':count replies').replace(':count', topic.replies)}</span>
                            {topic.last_post_at && <span>· {(t.last_post || 'Last post :when').replace(':when', when(topic.last_post_at))}</span>}
                        </div>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
