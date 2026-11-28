import { Link } from '@inertiajs/react';

/**
 * ST5 (admin audit 2026-11-27, STATUS §5uy): the Blogs office's pages in
 * one row, the same on every one of them — the glance first, then what
 * waits for a decision, then its settings. Two to a row on a phone; the
 * page you are on is filled in.
 */
const TABS = [
    ['insights', '/admin/library/blog-insights'],
    ['requests', '/admin/library/blog-requests'],
    ['pages', '/admin/library/blog-pages'],
    ['comments', '/admin/library/comments'],
    ['tips', '/admin/library/tips'],
    ['settings', '/admin/settings/office/blogs'],
];

export default function BlogOfficeTabs({ t = {}, current }) {
    return (
        <nav className="mb-4" aria-label={t.office_tabs_label} data-testid="blog-office-tabs">
            <ul className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                {TABS.map(([key, href]) => (
                    <li key={key} className="min-w-0">
                        <Link
                            href={href}
                            aria-current={key === current ? 'page' : undefined}
                            className={`flex min-h-[2.5rem] items-center justify-center rounded-full border px-4 py-1 text-center text-sm ${key === current ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-800'}`}
                            data-testid={`blog-tab-${key}`}
                        >
                            {t[`office_tab_${key}`]}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
