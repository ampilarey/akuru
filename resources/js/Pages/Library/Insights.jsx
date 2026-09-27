import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * B14 (LIBRARY_PLAN §29): the Library over a period, for the office —
 * aggregates only, nothing here names a reader.
 */
function Table({ title, columns, rows, empty, testId }) {
    return (
        <div className="mb-6 overflow-x-auto rounded-lg border bg-white" data-testid={testId}>
            <table className="min-w-full text-sm">
                <thead className="bg-[#F3EBE0] text-start">
                    <tr>{columns.map(([key, label]) => <th key={key} className="px-3 py-2">{label}</th>)}</tr>
                </thead>
                <tbody>
                    {rows.length === 0 && <tr><td className="px-3 py-4 text-gray-500" colSpan={columns.length}>{empty}</td></tr>}
                    {rows.map((row, i) => (
                        <tr key={i} className="border-t">
                            {columns.map(([key]) => <td key={key} className="px-3 py-2">{row[key] ?? '—'}</td>)}
                        </tr>
                    ))}
                </tbody>
            </table>
            <span className="sr-only">{title}</span>
        </div>
    );
}

export default function Insights({ insights, periods = [], t = {} }) {
    const h = insights.headline;
    const periodLabel = (p) => t[`library_insights_period_${p}`] || { week: 'Last 7 days', month: 'Last 30 days', quarter: 'Last 90 days', all: 'All time' }[p] || p;
    const tiles = [
        ['active_readers', t.library_insights_active_readers || 'Active readers'],
        ['pages_opened', t.library_insights_pages_opened || 'Pages opened'],
        ['completions', t.library_insights_completions || 'Items completed'],
        ['purchases', t.library_insights_purchases || 'Purchases'],
        ['revenue', t.library_insights_revenue || 'Revenue (MVR)'],
        ['searches', t.library_insights_searches || 'Searches'],
    ];

    return (
        <AppShell title={t.library_insights_title || 'Digital Library insights'}>
            <div className="mb-4 flex flex-wrap items-center gap-2 text-sm">
                {periods.map((p) => (
                    <Link key={p} href={`/admin/library/insights?period=${p}`} className={`rounded px-3 py-1 ${insights.period === p ? 'bg-[#7C2D37] text-white' : 'border'}`} data-testid={`period-${p}`}>{periodLabel(p)}</Link>
                ))}
                <a href={`/admin/library/insights/export?period=${insights.period}`} className="ms-auto underline" data-testid="export-csv">{t.library_insights_export || 'Export CSV'}</a>
                <a href="/admin/library" className="underline">{t.library_settings_back || 'Back to the Library office'}</a>
            </div>

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-6" data-testid="insights-headline">
                {tiles.map(([key, label]) => (
                    <div key={key} className="rounded-lg border bg-white p-3">
                        <div className="text-xs uppercase text-gray-500">{label}</div>
                        <div className="text-xl font-semibold" data-testid={`headline-${key}`}>{h[key]}</div>
                    </div>
                ))}
            </div>

            <h2 className="mb-2 text-base font-semibold">{t.library_insights_most_read || 'Most read'}</h2>
            <Table testId="most-read" title="Most read" rows={insights.most_read} empty={t.library_insights_nothing || 'Nothing in this period.'} columns={[
                ['title', t.library_insights_col_title || 'Title'], ['category', t.library_insights_col_category || 'Category'], ['writer', t.library_insights_col_writer || 'Writer'],
                ['pages', t.library_insights_pages_opened || 'Pages opened'], ['readers', t.library_insights_col_readers || 'Readers'], ['completions', t.library_insights_completions || 'Items completed'], ['purchases', t.library_insights_purchases || 'Purchases'],
            ]} />

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <h2 className="mb-2 text-base font-semibold">{t.library_insights_categories || 'Categories'}</h2>
                    <Table testId="categories" title="Categories" rows={insights.categories} empty={t.library_insights_nothing || 'Nothing in this period.'} columns={[
                        ['category', t.library_insights_col_category || 'Category'], ['pages', t.library_insights_pages_opened || 'Pages opened'], ['readers', t.library_insights_col_readers || 'Readers'],
                    ]} />
                </div>
                <div>
                    <h2 className="mb-2 text-base font-semibold">{t.library_insights_writers || 'Writers, by sales'}</h2>
                    <Table testId="writers" title="Writers" rows={insights.writers} empty={t.library_insights_nothing || 'Nothing in this period.'} columns={[
                        ['writer', t.library_insights_col_writer || 'Writer'], ['sales', t.library_insights_col_sales || 'Sales'], ['revenue', t.library_insights_revenue || 'Revenue (MVR)'],
                    ]} />
                </div>
                <div>
                    <h2 className="mb-2 text-base font-semibold">{t.library_insights_searches_top || 'What people search for'}</h2>
                    <Table testId="searches-top" title="Searches" rows={insights.searches.top} empty={t.library_insights_nothing || 'Nothing in this period.'} columns={[
                        ['term', t.library_insights_col_term || 'Term'], ['count', t.library_insights_col_count || 'Times'], ['misses', t.library_insights_col_misses || 'Found nothing'],
                    ]} />
                </div>
                <div>
                    <h2 className="mb-2 text-base font-semibold">{t.library_insights_searches_empty || 'Searched for and not found'}</h2>
                    <Table testId="searches-empty" title="Empty searches" rows={insights.searches.empty} empty={t.library_insights_nothing || 'Nothing in this period.'} columns={[
                        ['term', t.library_insights_col_term || 'Term'], ['count', t.library_insights_col_count || 'Times'],
                    ]} />
                </div>
            </div>
        </AppShell>
    );
}
