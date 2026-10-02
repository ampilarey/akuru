/**
 * The shop's other pages, thumb-sized. On a phone they sit in two columns
 * under the heading — a sideways row ran off the screen (the owner's
 * screenshot, 2026-10-02) and the rest of the links could not be reached.
 * The page you are on is filled in.
 */
const LINKS = [
    ['/vendor', 'portal_title', 'nav-home', 'home'],
    ['/vendor/orders', 'orders_title', 'open-orders', 'orders'],
    ['/vendor/storefront', 'designer_title', 'open-designer', 'designer'],
    ['/vendor/storefront/sections', 'sections_title', 'open-sections', 'sections'],
    ['/vendor/money', 'money_title', 'open-money', 'money'],
    ['/vendor/reviews', 'reviews_heading', 'open-reviews', 'reviews'],
    ['/vendor/stock', 'stock_title', 'open-stock', 'stock'],
    ['/vendor/quotes', 'quotes_title', 'open-quotes', 'quotes'],
    ['/vendor/insights', 'insights_title', 'open-insights', 'insights'],
];

export default function VendorNav({ t, current }) {
    return (
        <nav className="mb-4 min-w-0" aria-label={t.on_this_page} data-testid="vendor-subnav">
            <ul className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                {LINKS.map(([href, key, testid, id]) => (
                    <li key={id} className="min-w-0">
                        <a
                            href={href}
                            aria-current={current === id ? 'page' : undefined}
                            className={`flex min-h-[2rem] items-center justify-center rounded-full border px-3 py-1 text-center text-sm leading-snug sm:inline-flex sm:justify-start sm:text-start ${current === id ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-800'}`}
                            data-testid={testid}
                        >
                            {t[key]}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
