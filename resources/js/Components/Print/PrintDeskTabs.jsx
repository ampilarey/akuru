import { Link } from '@inertiajs/react';

/**
 * ST3 (admin audit 2026-11-27, STATUS §5uv): the print desk's pages in one
 * row, the same on every one of them — the daily work first (queue,
 * quotes), then what is set up once (templates, papers, layouts, products,
 * settings). Two to a row on a phone; the page you are on is filled in.
 */
const TABS = [
    ['queue', '/admin/bookshop/print/queue'],
    ['quotes', '/admin/bookshop/print/quotes'],
    ['templates', '/admin/bookshop/print/templates'],
    ['papers', '/admin/bookshop/print/desk/papers'],
    ['layouts', '/admin/bookshop/print/desk/layouts'],
    ['products', '/admin/bookshop/print/desk/products'],
    ['settings', '/admin/bookshop/print'],
];

export default function PrintDeskTabs({ t = {}, current }) {
    return (
        <nav className="mb-4" aria-label={t.title} data-testid="print-desk-tabs">
            <ul className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                {TABS.map(([key, href]) => (
                    <li key={key} className="min-w-0">
                        <Link
                            href={href}
                            aria-current={key === current ? 'page' : undefined}
                            className={`flex min-h-[2.5rem] items-center justify-center rounded-full border px-4 py-1 text-center text-sm ${key === current ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-800'}`}
                            data-testid={`print-tab-${key}`}
                        >
                            {t[`desk_tab_${key}`]}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
