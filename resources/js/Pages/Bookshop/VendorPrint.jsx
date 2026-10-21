import { Link, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import VendorNav from '../../Components/Bookshop/VendorNav';
import { Finishing, Papers } from './Print';
import { Job } from './PrintQueue';

/**
 * PRINT_PRODUCTS_PLAN PP7b (STATUS §5ui): a printing shop's own print desk.
 * Its queue of paid print orders comes first — that is what the page is for
 * — then its papers and finishings at its own prices, the office's own forms
 * pointed at `/vendor/print`. A shop the office has not approved is told so,
 * and can set its papers up meanwhile.
 */
export default function VendorPrint({ t = {}, shop = {}, vendor, prints = false, papers = [], finishing = [], rows = [], all = false, kinds = [], sheet_sizes: sizes = ['a4'] }) {
    const { errors } = usePage().props;
    const base = '/vendor/print';

    return (
        <AppShell title={t.title}>
            <FormErrors errors={errors} className="mb-4" />
            <header className="mb-4">
                <h1 className="text-2xl font-bold" data-testid="vendor-print-heading">{t.title} · {vendor.name}</h1>
                <p className="mb-3 text-sm text-gray-600">{t.vendor_print_intro}</p>
                <VendorNav t={shop} current="print" />
            </header>
            {!prints && <p className="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" data-testid="vendor-print-not-approved">{t.vendor_print_not_approved}</p>}
            <section className="mb-6" data-phone-main data-testid="vendor-print-queue">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">{t.vendor_print_queue} <span className="text-sm font-normal text-gray-500">({rows.length})</span></h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <Link href={all ? base : `${base}?all=1`} className="text-sm text-blue-700 underline" data-testid="queue-toggle-all">{all ? t.queue_show_open : t.queue_show_all}</Link>
                        <a href={`${base}/export${all ? '?all=1' : ''}`} className="btn-secondary text-sm" data-testid="queue-export">{t.export_csv}</a>
                    </div>
                </div>
                {rows.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600" data-testid="queue-empty">{t.queue_empty}</p> : (
                    <ul className="grid gap-3">{rows.map((row) => <Job key={row.id} t={t} row={row} base={base} />)}</ul>
                )}
            </section>
            <Papers t={t} papers={papers} kinds={kinds} sizes={sizes} base={base} />
            <Finishing t={t} finishing={finishing} base={base} />
        </AppShell>
    );
}
