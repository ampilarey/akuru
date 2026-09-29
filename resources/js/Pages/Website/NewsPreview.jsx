import { Link, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * RESEARCH_ARTICLES_PLAN R4: a news item as the office previews it — a
 * draft too. The body is sanitised on write (SaveNewsPostAction,
 * PROFILE_CMS), so what is rendered raw here is what the public page
 * renders raw.
 */
export default function NewsPreview({ post, t = {} }) {
    const { flash = {} } = usePage().props;
    const state = { live: t.news_state_live || 'Published', scheduled: t.news_state_scheduled || 'Scheduled', draft: t.news_state_draft || 'Draft' }[post.state];

    return (
        <AppShell title={post.title}>
            <p className="mb-2 text-sm"><Link href="/admin/public-site/news" className="text-gray-500 underline">{t.news_back || '← Back to News'}</Link></p>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="news-flash">✓ {flash.success}</p>}
            <article className="max-w-3xl space-y-4 rounded-lg border bg-white p-6" data-testid="news-preview-body">
                {post.cover_url && <img src={post.cover_url} alt="" className="max-h-72 w-full rounded object-cover" data-testid="news-preview-cover" />}
                <h1 className="text-2xl font-bold text-gray-900">{post.title}</h1>
                {post.summary && <p className="text-gray-600">{post.summary}</p>}
                <div className="prose max-w-none text-gray-700" dangerouslySetInnerHTML={{ __html: post.body }} />
                <footer className="flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-sm text-gray-500">
                    <span data-testid="news-preview-state">{state}{post.published_at ? ` · ${post.published_at}` : ''}{post.category ? ` · ${post.category}` : ''}</span>
                    <span className="flex gap-2">
                        {post.state === 'live' && <a href={post.public_url} target="_blank" rel="noopener noreferrer" className="btn-secondary">{t.pages_view_public || 'View Public'}</a>}
                        <Link href={`/admin/public-site/news/${post.id}/edit`} className="btn-primary">{t.research_edit || 'Edit'}</Link>
                    </span>
                </footer>
            </article>
        </AppShell>
    );
}
