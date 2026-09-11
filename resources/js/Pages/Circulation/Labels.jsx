import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A printable label sheet. Each label carries the accession number as a Code 39
 * barcode and as readable text — a scanner reads the bars, a human reads the
 * number when the label is scuffed.
 */
export default function Labels({ title, labels = [] }) {
    return (
        <AppShell title={`Labels — ${title.title}`}>
            <div className="mb-4 flex flex-wrap gap-3 print:hidden">
                <button className="btn-primary text-sm" onClick={() => window.print()}>Print</button>
                <Link href={`/circulation/titles/${title.id}`} className="self-center text-sm text-[#7C2D37] underline">
                    Back to the title
                </Link>
            </div>

            <p className="mb-4 text-sm text-gray-600 print:hidden">
                {labels.length} label{labels.length === 1 ? '' : 's'}. Stick one inside each copy before
                it goes on the shelf — a book without one cannot be issued or taken back.
            </p>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {labels.map((label) => (
                    <div key={label.accession_number} className="rounded border bg-white p-3 text-center">
                        <p className="text-xs text-gray-600">{title.title}</p>
                        <div
                            className="my-2 flex justify-center"
                            dangerouslySetInnerHTML={{ __html: label.barcode }}
                        />
                        <p className="font-mono text-sm">{label.accession_number}</p>
                        {label.shelf && <p className="text-xs text-gray-500">{label.shelf}</p>}
                    </div>
                ))}
            </div>
            {labels.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    No copies yet, so nothing to label.
                </p>
            )}
        </AppShell>
    );
}
