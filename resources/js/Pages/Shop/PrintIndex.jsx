import { Link, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * PRINT_PRODUCTS_PLAN PP2: `/shop/print` — what Akuru prints, one card per
 * print product on sale, each opening its own door. The office also sees
 * the products not yet on sale, marked, to try them first.
 */
export default function PrintIndex({ t = {}, products = [], enabled = true }) {
    const { locale = 'en' } = usePage().props;

    return (
        <AppShell title={t.print_index_title}>
            {!enabled && <p className="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-900" data-testid="print-index-office">{t.door_office_preview}</p>}
            <p className="mb-4 text-gray-700">{t.print_index_intro}</p>
            {products.length === 0 ? <p className="text-sm text-gray-600" data-testid="print-index-none">{t.print_index_none}</p> : (
                <ul className="grid gap-3 sm:grid-cols-2" data-testid="print-index" data-phone-main>
                    {products.map((p) => (
                        <li key={p.kind} className="flex flex-col rounded-lg border bg-white p-4" data-testid={`print-index-${p.kind}`}>
                            <h2 className="font-semibold" dir="auto">{p.title}</h2>
                            <p className="mt-1 flex-1 text-sm text-gray-600" dir="auto">{p.summary}</p>
                            {!p.on_sale && <p className="mt-2 text-xs font-medium text-amber-800" data-testid={`print-index-${p.kind}-off`}>{t.product_not_on_sale}</p>}
                            <Link href={`/${locale}/shop/print/${p.path}`} className="btn-primary mt-3 self-start" data-testid={`print-index-${p.kind}-open`}>{t.print_index_open}</Link>
                        </li>
                    ))}
                </ul>
            )}
        </AppShell>
    );
}
