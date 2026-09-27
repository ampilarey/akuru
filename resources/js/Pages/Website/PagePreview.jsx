import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A website page as the office previews it (C9 slice 10, STATUS §5jl).
 * The body is the stored HTML: PageController sanitises it with
 * HtmlSanitizer::PROFILE_CMS on every write, so what is rendered raw here
 * is what the public site renders raw too (tests/Architecture/Baselines/
 * raw_html_renders.php names both sinks).
 */
export default function PagePreview({ page, t = {} }) {
    return (
        <AppShell title={page.title}>
            <p className="mb-2 text-sm"><Link href="/admin/public-site/pages" className="text-gray-500 underline" data-testid="pages-back">{t.pages_back || '← Back to Pages'}</Link></p>
            <p className="mb-4 text-sm text-gray-500"><code className="rounded bg-gray-100 px-2 py-0.5 text-xs" dir="ltr">{page.slug}</code></p>

            <article className="max-w-3xl space-y-6 rounded-lg border bg-white p-6" data-testid="page-preview">
                {page.excerpt && (
                    <section>
                        <h2 className="mb-1 text-xs font-medium uppercase text-gray-500">{t.pages_excerpt || 'Excerpt'}</h2>
                        <p className="text-gray-700">{page.excerpt}</p>
                    </section>
                )}
                <section>
                    <h2 className="mb-1 text-xs font-medium uppercase text-gray-500">{t.pages_content || 'Content'}</h2>
                    {/* Sanitised on write (PROFILE_CMS); see the note above. */}
                    <div className="prose max-w-none text-gray-700" dangerouslySetInnerHTML={{ __html: page.body }} data-testid="page-body" />
                </section>
                <footer className="flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-sm text-gray-500">
                    <span>
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${page.is_published ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>{page.is_published ? (t.pages_published || 'Published') : (t.pages_draft || 'Draft')}</span>
                        {' · '}{(t.pages_updated_on || 'Updated :date').replace(':date', page.updated_at)}
                    </span>
                    <span className="flex gap-2">
                        <a href={page.public_url} target="_blank" rel="noopener noreferrer" className="btn-secondary">{t.pages_view_public || 'View Public'}</a>
                        <Link href={`/admin/public-site/pages/${page.id}/edit`} className="btn-primary" data-testid="page-edit">{t.research_edit || 'Edit'}</Link>
                    </span>
                </footer>
            </article>
        </AppShell>
    );
}
