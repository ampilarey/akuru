import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import BodyEditor from '../../Components/BodyEditor';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL1: *My blog*. A student asks to start a blog, follows the
 * request through their parent and the office, and sees the blog once it is
 * approved. BL2a: the blogger writes posts, in series, and sends each to be
 * checked — a student's to the class teacher, then the office. Every word is
 * in the `blog` phrase book (EN/DV/AR).
 */
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.split(`:${key}`).join(String(value ?? '')), String(text));

function StartForm({ t, under18, guardianLinked }) {
    const form = useForm({ pen_name: '', blog_title: '', about: '' });

    return (
        <form
            className="grid max-w-xl gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/my-blog', { preserveScroll: true });
            }}
            data-testid="blog-start-form"
        >
            <h2 className="text-lg font-semibold">{t.start_heading || 'Start a blog'}</h2>
            <p className="text-sm text-gray-600">{t.start_help || 'Tell us what your blog will be called and what you will write about. Nothing is published until it is checked.'}</p>
            {under18 && (
                <p className="rounded bg-amber-50 p-3 text-sm text-amber-900" data-testid="blog-under18">
                    {guardianLinked ? t.under18_note || 'You are under 18, so a parent agrees first. We will ask them in the family portal.' : t.no_guardian || 'You are under 18, and no parent is linked to your record yet. Ask the school office to link and verify a parent first.'}
                </p>
            )}
            <label className="block text-sm">
                <span className="mb-1 block font-medium">{t.field_pen_name || 'Pen name'}</span>
                <input className="form-input w-full" value={form.data.pen_name} onChange={(e) => form.setData('pen_name', e.target.value)} maxLength={40} data-testid="blog-pen-name" />
                <span className="mt-1 block text-xs text-gray-500">{t.pen_name_help || 'The name readers will see. Use a pen name or your first name, not your full name.'}</span>
            </label>
            <label className="block text-sm">
                <span className="mb-1 block font-medium">{t.field_blog_title || 'Blog title'}</span>
                <input className="form-input w-full" value={form.data.blog_title} onChange={(e) => form.setData('blog_title', e.target.value)} maxLength={120} data-testid="blog-title" />
            </label>
            <label className="block text-sm">
                <span className="mb-1 block font-medium">{t.field_about || 'What you will write about'}</span>
                <textarea className="form-input w-full" rows={3} value={form.data.about} onChange={(e) => form.setData('about', e.target.value)} maxLength={300} data-testid="blog-about" />
            </label>
            <FormErrors errors={form.errors} />
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing} data-testid="blog-start-submit">{t.start_submit || 'Ask to start my blog'}</button>
        </form>
    );
}

const editorLabels = (c) => ({
    toolbar: c.library_editor_toolbar,
    bold: c.library_editor_bold,
    italic: c.library_editor_italic,
    heading: c.library_editor_heading,
    subheading: c.library_editor_subheading,
    bullets: c.library_editor_bullets,
    numbers: c.library_editor_numbers,
    quote: c.library_editor_quote,
    link: c.library_editor_link,
    link_prompt: c.library_editor_link_prompt,
    page_break: c.library_editor_page_break,
    source: c.library_editor_source,
});

const blank = { title: '', body: '', language: 'en', series_id: '', new_series: '', pinned: false, own_work: false, cover: null };

function PostForm({ t, series, post, onDone }) {
    const common = usePage().props.i18n?.common || {};
    const live = post?.status === 'published';
    const form = useForm(post ? { ...blank, title: post.edit_title || post.title, body: post.body || '', language: post.language || 'en', series_id: post.series_id || '', pinned: post.pinned, own_work: post.own_work } : blank);
    const save = (e) => {
        e.preventDefault();
        form.post(post ? `/my-blog/posts/${post.id}` : '/my-blog/posts', { preserveScroll: true, forceFormData: true, onSuccess: () => { form.reset(); onDone(); } });
    };

    return (
        <form className="grid gap-3 rounded-lg border bg-white p-4" onSubmit={save} data-testid="post-form">
            <h3 className="text-base font-semibold">{post ? t.edit_post || 'Edit post' : t.new_post || 'New post'}</h3>
            {live && <p className="rounded bg-amber-50 p-2 text-sm text-amber-900" data-testid="post-change-help">{t.change_help || 'This post is published. Your edit is checked before it replaces what readers see.'}</p>}
            <label className="block text-sm">
                <span className="mb-1 block font-medium">{t.field_post_title || 'Title'}</span>
                <input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} maxLength={160} data-testid="post-title" />
            </label>
            <div className="block text-sm">
                <span className="mb-1 block font-medium">{t.field_post_body || 'Text'}</span>
                <BodyEditor value={form.data.body} onChange={(html) => form.setData('body', html)} placeholder={t.field_post_body || 'Text'} labels={editorLabels(common)} testId="post-body" />
            </div>
            {!live && <div className="grid gap-3 sm:grid-cols-3">
                <label className="block text-sm">
                    <span className="mb-1 block font-medium">{t.field_language || 'Language'}</span>
                    <select className="form-input w-full" value={form.data.language} onChange={(e) => form.setData('language', e.target.value)} data-testid="post-language">
                        {['en', 'dv', 'ar'].map((code) => <option key={code} value={code}>{t[`lang_${code}`] || code}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block font-medium">{t.field_series || 'Series'}</span>
                    <select className="form-input w-full" value={form.data.series_id} onChange={(e) => form.setData('series_id', e.target.value)} data-testid="post-series">
                        <option value="">{t.no_series || 'No series'}</option>
                        {series.map((s) => <option key={s.id} value={s.id}>{s.title}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block font-medium">{t.field_new_series || 'Or start a new series'}</span>
                    <input className="form-input w-full" value={form.data.new_series} onChange={(e) => form.setData('new_series', e.target.value)} maxLength={120} data-testid="post-new-series" />
                </label>
            </div>}
            <label className="block text-sm">
                <span className="mb-1 block font-medium">{t.field_cover || 'Cover image'}</span>
                <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('cover', e.target.files[0] || null)} data-testid="post-cover" />
            </label>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.data.pinned} onChange={(e) => form.setData('pinned', e.target.checked)} data-testid="post-pinned" />
                {t.field_pinned || 'Pin to my page'}
            </label>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.data.own_work} onChange={(e) => form.setData('own_work', e.target.checked)} data-testid="post-own-work" />
                {t.field_own_work || 'This is my own work, and I may publish it'}
            </label>
            <FormErrors errors={form.errors} />
            <div className="flex flex-wrap gap-2">
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="post-save">{t.save_draft || 'Save draft'}</button>
                {post && <button type="button" className="btn-secondary" onClick={onDone}>{t.cancel_edit || 'Cancel'}</button>}
            </div>
        </form>
    );
}

function Posts({ t, posts, series }) {
    const refusals = useRowRefusals();
    const [editing, setEditing] = useState(null);
    const statusKey = (p) => (p.status === 'submitted' ? `post_status_submitted_${p.review_stage === 'teacher' ? 'teacher' : 'office'}` : `post_status_${p.status}`);
    // BL2c: a published post's waiting edit.
    const changeKey = (p) => (p.review_stage ? `change_status_${p.review_stage === 'teacher' ? 'teacher' : 'office'}` : 'change_status_draft');
    const submit = (id) => refusals.actOn(`post:${id}`, () => router.post(`/my-blog/posts/${id}/submit`, {}, { preserveScroll: true }));

    return (
        <section className="mt-6 grid max-w-3xl gap-4" data-testid="blog-posts">
            <div>
                <h2 className="text-lg font-semibold">{t.posts_heading || 'My posts'}</h2>
                <p className="text-sm text-gray-600">{t.posts_intro || 'Every post is checked before anyone can read it.'}</p>
            </div>
            {editing === null && <PostForm key="new" t={t} series={series} post={null} onDone={() => setEditing(null)} />}
            {editing !== null && <PostForm key={editing.id} t={t} series={series} post={editing} onDone={() => setEditing(null)} />}
            {posts.length === 0 && <p className="text-sm text-gray-500">{t.no_posts || 'No posts yet.'}</p>}
            <ul className="grid gap-2">
                {posts.map((p) => (
                    <li key={p.id} className="rounded-lg border bg-white p-3" data-testid={`post-${p.id}`} data-status={p.status} data-stage={p.review_stage || ''}>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{p.title}</span>
                            {p.pinned && <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">{t.pinned_badge || 'Pinned'}</span>}
                            <span className="rounded bg-gray-100 px-2 py-0.5 text-xs" data-testid={`post-status-${p.id}`}>{t[statusKey(p)] || p.status}</span>
                            {p.status === 'published' && p.has_change && <span className="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-900" data-testid={`post-change-${p.id}`} data-stage={p.review_stage || ''}>{t[changeKey(p)] || 'Edit waiting'}</span>}
                        </div>
                        {p.series && <p className="text-xs text-gray-600">{fill(t.series_line || ':series, part :n', { series: p.series, n: p.series_position })}</p>}
                        {p.note && (['changes_requested', 'rejected'].includes(p.status) || (p.status === 'published' && p.has_change && !p.review_stage)) && <p className="mt-1 text-sm text-amber-900" data-testid={`post-note-${p.id}`}>{fill(t.post_note || 'Note: :note', { note: p.note })}</p>}
                        <div className="mt-2 flex flex-wrap gap-3 text-sm">
                            {p.editable && <button type="button" className="underline" onClick={() => setEditing(p)} data-testid={`post-edit-${p.id}`}>{t.edit || 'Edit'}</button>}
                            {p.editable && (p.status !== 'published' || p.has_change) && <button type="button" className="btn-primary" onClick={() => submit(p.id)} data-testid={`post-submit-${p.id}`}>{t.submit_post || 'Send for checking'}</button>}
                            {p.status === 'published' && <a className="underline" href={`/library/${p.slug}`}>{t.view_post || 'Read it'}</a>}
                        </div>
                        <FormErrors errors={refusals.errorsFor(`post:${p.id}`)} className="mt-1" />
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function MyBlog({ state, blog = null, request = null, posts = [], series = [], under_18: under18 = false, guardian_linked: guardianLinked = true, t = {} }) {
    const statusName = (status) => t[`status_${status}`] || status;

    return (
        <AppShell title={t.my_blog_title || 'My blog'}>
            {state === 'not_student' && (
                <p className="max-w-xl rounded-lg border bg-white p-4 text-sm text-gray-700" data-testid="blog-not-student">
                    {t.not_student || 'Blogs here start from an Akuru student record. To write as an adult writer, apply in the writer portal.'}{' '}
                    <a className="underline" href="/write">{t.open_writer_portal || 'Open the writer portal'}</a>
                </p>
            )}

            {(state === 'blog' || state === 'writer') && blog && (
                <section className="max-w-xl rounded-lg border bg-white p-4" data-testid="blog-ready">
                    <p className="mb-1 inline-block rounded bg-[#F3EBE0] px-2 py-0.5 text-xs font-semibold text-[#7C2D37]" data-testid="blog-badge">
                        {state === 'blog' ? t.badge_student || 'Student writer' : t.badge_writer || 'Writer'}
                    </p>
                    <h2 className="text-xl font-semibold" data-testid="blog-ready-title">{blog.blog_title || blog.pen_name}</h2>
                    <p className="text-sm text-gray-600">{fill(t.blog_by || 'by :name', { name: blog.pen_name })}{blog.since ? ` · ${fill(t.blog_since || 'approved :date', { date: blog.since })}` : ''}</p>
                    {blog.about && <p className="mt-2 text-sm text-gray-700">{blog.about}</p>}
                    {state === 'writer' && <p className="mt-3 rounded bg-gray-50 p-3 text-sm text-gray-700">{t.writer_state || 'You write as a full writer. Your blog comes with your writer page.'}</p>}
                    <div className="mt-3 flex flex-wrap gap-3 text-sm">
                        {blog.slug && <a className="underline" href={`/library/authors/${blog.slug}`} data-testid="blog-view-page">{t.view_page || 'See my page'}</a>}
                        {/* BL3a: the page designer. */}
                        <a className="underline" href="/my-blog/design" data-testid="blog-design-link">{t.design_link || 'Design my page'}</a>
                        {state === 'writer' && <a className="underline" href="/write">{t.open_writer_portal || 'Open the writer portal'}</a>}
                    </div>
                </section>
            )}

            {(state === 'blog' || state === 'writer') && <Posts t={t} posts={posts} series={series} />}

            {(state === 'blog' || state === 'writer') && blog && (
                <section className="mt-6 max-w-3xl rounded-lg border bg-white p-4" data-testid="blog-comments">
                    {/* BL5: the writer's switch, forced off under 13; the comments, read only — the office decides. */}
                    <h2 className="text-lg font-semibold">{t.comments_heading || 'Comments'}</h2>
                    {blog.under_13 ? (
                        <p className="text-sm text-gray-700" data-testid="blog-comments-young">{t.comments_closed_young || 'Comments are off on this blog: its writer is under 13.'}</p>
                    ) : (
                        <label className="mt-1 flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={blog.comments_enabled} onChange={(e) => router.post('/my-blog/comments', { enabled: e.target.checked }, { preserveScroll: true })} data-testid="blog-comments-switch" />
                            {t.comments_switch || 'Readers may comment on my posts (the office checks every comment first)'}
                        </label>
                    )}
                    {(blog.comments || []).length > 0 && (
                        <ul className="mt-3 grid gap-2 text-sm">
                            {blog.comments.map((c, i) => (
                                <li key={i} className="rounded border p-2">
                                    <p className="text-xs text-gray-500">{c.post} · {c.at} · {t[`comment_status_${c.status}`] || c.status}</p>
                                    <p className="whitespace-pre-line">{c.body}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            )}

            {state === 'request' && request && (
                <section className="mb-6 max-w-xl rounded-lg border bg-white p-4" data-testid="blog-request">
                    <h2 className="text-lg font-semibold">{t.request_heading || 'Your request'}</h2>
                    <p className="text-sm text-gray-700">{fill(t.request_line || '“:title”, as :name, asked on :date', { title: request.blog_title, name: request.pen_name, date: request.created_at })}</p>
                    <p className="mt-2 inline-block rounded bg-amber-50 px-2 py-1 text-sm font-medium text-amber-900" data-testid="blog-request-status" data-status={request.status}>{statusName(request.status)}</p>
                    {request.status === 'waiting_parent' && !guardianLinked && (
                        <p className="mt-2 text-sm text-amber-900">{t.no_guardian || 'You are under 18, and no parent is linked to your record yet. Ask the school office to link and verify a parent first.'}</p>
                    )}
                    {request.decision_note && <p className="mt-2 text-sm text-gray-700">{fill(t.request_note || 'From the office: :note', { note: request.decision_note })}</p>}
                </section>
            )}

            {(state === 'start' || (state === 'request' && request?.can_ask_again)) && <StartForm t={t} under18={under18} guardianLinked={guardianLinked} />}
        </AppShell>
    );
}
