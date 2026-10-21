import { router } from '@inertiajs/react';

/**
 * PRINT_PRODUCTS_PLAN PP7c (PD10d): who prints the job — Akuru first, then
 * each shop the office approved that has a priced paper of the job's sheet
 * size. Choosing saves what is typed, then moves the job to that printer's
 * first paper (its finishings are its own, so they are cleared) and the page
 * reloads with that printer's papers.
 */
export default function PrinterChoice({ t, printers = [], printer = null, sheetSize = 'a4', base, open = true, flush = () => Promise.resolve() }) {
    const offered = printers.filter((p) => p.id === printer || (p.sizes || []).includes(sheetSize));
    if (offered.length < 2) return null;
    const choose = (id) => flush().then(() => router.post(`${base}/printer`, { printer: id }, { preserveScroll: true }));

    return (
        <fieldset disabled={!open} data-testid="print-printers">
            <legend className="block">{t.printer_heading}</legend>
            <p className="text-xs text-gray-600">{t.printer_intro}</p>
            {offered.map((p) => (
                <label key={p.id ?? 'akuru'} className="mt-1 flex items-center gap-2 rounded border px-2 py-1.5">
                    <input type="radio" name="printer" checked={(printer ?? null) === (p.id ?? null)} onChange={() => choose(p.id)} data-testid={`print-printer-${p.slug}`} />
                    <span className="flex-1" dir="auto">{p.name}</span>
                </label>
            ))}
        </fieldset>
    );
}
