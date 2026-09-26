import { router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * BOOKSHOP_PLAN slice B9e — the shop's funnel: how many visited the shop,
 * looked at a product, put it in the cart, started a checkout and paid,
 * with the rate from each step to the next; the days; the products most
 * looked at and what they sold; the shop's pages. Counts only — nobody's
 * visit is recorded. Every list has its CSV.
 */

const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.replaceAll(`:${k}`, v), s || '');

function Funnel({ report, t }) {
    const top = Math.max(1, ...report.funnel.map((s) => s.count));

    return (
        <section className="mb-8" data-testid="insights-funnel">
            <h2 className="mb-2 text-lg font-semibold">{t.insights_funnel}</h2>
            <ol className="space-y-2">
                {report.funnel.map((step) => (
                    <li key={step.metric} className="rounded border bg-white p-2" data-testid={`funnel-${step.metric}`} data-count={step.count}>
                        <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{t[`insights_step_${step.metric}`] || step.metric}</span>
                            <span>
                                <span className="text-lg font-semibold">{step.count}</span>
                                {step.rate !== null && <span className="ms-2 text-gray-500">{fill(t.insights_rate, { rate: step.rate })}</span>}
                            </span>
                        </div>
                        <div className="mt-1 h-2 rounded bg-gray-100" aria-hidden="true">
                            <div className="h-2 rounded bg-brandMaroon-600" style={{ width: `${Math.round((step.count * 100) / top)}%` }} />
                        </div>
                    </li>
                ))}
            </ol>
            <p className="mt-2 text-sm text-gray-600" data-testid="insights-summary">
                {fill(t.insights_revenue, { amount: report.revenue })}
                {report.conversion !== null && ` · ${fill(t.insights_conversion, { rate: report.conversion })}`}
            </p>
        </section>
    );
}

function Table({ title, testid, head, rows, exportHref, t, empty }) {
    return (
        <section className="mb-8" data-testid={testid}>
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{title}</h2>
                {exportHref && <a href={exportHref} className="btn-secondary" data-testid={`${testid}-export`}>{t.export_csv}</a>}
            </div>
            {rows.length === 0 ? (
                <p className="rounded border bg-white p-3 text-sm text-gray-600">{empty}</p>
            ) : (
                <div className="overflow-x-auto rounded border bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50"><tr>{head.map((h, i) => <th key={h} className={`p-2 ${i === 0 ? 'text-start' : 'text-end'}`}>{h}</th>)}</tr></thead>
                        <tbody>
                            {rows.map((cells, r) => (
                                <tr key={r} className="border-t">{cells.map((c, i) => <td key={i} className={`p-2 ${i === 0 ? 'text-start' : 'text-end'}`} dir={i === 0 ? 'auto' : undefined}>{c}</td>)}</tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

const pageName = (subject, t) => {
    if (subject === 'home') return t.insights_page_home;
    const [kind, slug] = subject.split(':');
    return `${t[`insights_page_${kind}`] || kind}: ${slug}`;
};

export default function VendorInsights({ t, vendor, report, ranges }) {
    const { flash = {} } = usePage().props;
    const days = report.days;

    return (
        <AppShell title={t.insights_title}>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}
            <header className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="text-2xl font-bold" data-testid="insights-heading">{t.insights_title} · {vendor.name}</h1>
                    <p className="text-sm text-gray-600"><a href="/vendor" className="text-blue-700 underline">{t.portal_title}</a> · {t.insights_intro}</p>
                </div>
                <nav className="flex gap-1" data-testid="insights-ranges">
                    {ranges.map((d) => (
                        <button key={d} type="button" className={`rounded px-2 py-1 text-sm ${d === days ? 'bg-gray-800 text-white' : 'bg-gray-100'}`} onClick={() => router.get('/vendor/insights', { days: d })} data-testid={`range-${d}`}>
                            {fill(t.insights_last_days, { days: d })}
                        </button>
                    ))}
                </nav>
            </header>
            <p className="mb-4 text-xs text-gray-500">{fill(t.insights_period, { from: report.from, to: report.to })} · {t.insights_privacy}</p>
            <Funnel report={report} t={t} />
            <Table
                title={t.insights_top_products} testid="insights-products" t={t} empty={t.insights_none}
                exportHref={`/vendor/insights/export?list=products&days=${days}`}
                head={[t.product, t.insights_views, t.insights_cart_adds, t.insights_sold, t.insights_sales]}
                rows={report.top_products.map((p) => [p.title, p.views, p.cart_adds, p.sold, p.sales])}
            />
            <Table
                title={t.insights_top_pages} testid="insights-pages" t={t} empty={t.insights_none}
                head={[t.insights_page, t.insights_views]}
                rows={report.top_pages.map((p) => [pageName(p.page, t), p.views])}
            />
            <Table
                title={t.insights_by_day} testid="insights-days" t={t} empty={t.insights_none}
                exportHref={`/vendor/insights/export?days=${days}`}
                head={[t.insights_day, ...report.funnel.map((s) => t[`insights_step_${s.metric}`] || s.metric), t.insights_sales]}
                rows={[...report.daily].reverse().map((d) => [d.day, d.shop_view, d.product_view, d.cart_add, d.checkout, d.order_paid, d.revenue])}
            />
        </AppShell>
    );
}
