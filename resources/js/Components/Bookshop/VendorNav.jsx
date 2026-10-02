/**
 * The shop's other pages, one thumb-sized row. On a phone it scrolls
 * sideways under the heading so Orders, Money, Stock and the rest stay
 * one tap away without a trip back to the portal. The page you are on
 * is filled in.
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
        <nav className="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label={t.on_this_page} data-testid="vendor-subnav">
            <ul className="flex w-max gap-2 pb-1 sm:w-auto sm:flex-wrap">
                {LINKS.map(([href, key, testid, id]) => (
                    <li key={id} className="shrink-0">
                        <a
                            href={href}
                            aria-current={current === id ? 'page' : undefined}
                            className={`inline-flex min-h-[2rem] items-center whitespace-nowrap rounded-full border px-3 py-1 text-sm ${current === id ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-800'}`}
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
