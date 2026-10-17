import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL1: *My blog*. A student asks to start a blog, follows the
 * request through their parent and the office, and sees the blog once it is
 * approved. Every word is in the `blog` phrase book (EN/DV/AR).
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

export default function MyBlog({ state, blog = null, request = null, under_18: under18 = false, guardian_linked: guardianLinked = true, t = {} }) {
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
                    <p className="mt-3 rounded bg-gray-50 p-3 text-sm text-gray-700">
                        {state === 'blog' ? t.posts_soon || 'Writing posts opens soon. Every post is checked before it is published.' : t.writer_state || 'You write as a full writer. Your blog comes with your writer page.'}
                    </p>
                    <div className="mt-3 flex flex-wrap gap-3 text-sm">
                        {blog.slug && <a className="underline" href={`/library/authors/${blog.slug}`} data-testid="blog-view-page">{t.view_page || 'See my page'}</a>}
                        {state === 'writer' && <a className="underline" href="/write">{t.open_writer_portal || 'Open the writer portal'}</a>}
                    </div>
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
