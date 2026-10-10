import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A printable label sheet. Each label carries the accession number as a Code 39
 * barcode and as readable text — a scanner reads the bars, a human reads the
 * number when the label is scuffed. Every word is the `circulation` book's
 * (slice LD1, STATUS §5qt).
 */
export default function Labels({ title, labels = [], t = {} }) {
    return (
        <AppShell title={(t.labels_title || 'Labels — :title').replace(':title', title.title)}>
            <div className="mb-4 flex flex-wrap gap-3 print:hidden">
                <button type="button" className="btn-primary text-sm" onClick={() => window.print()}>{t.print || 'Print'}</button>
                <Link href={`/circulation/titles/${title.id}`} className="self-center text-sm text-[#7C2D37] underline">
                    {t.back_to_title || 'Back to the title'}
                </Link>
            </div>

            <p className="mb-4 text-sm text-gray-600 print:hidden">
                {labels.length === 1 ? (t.labels_one || '1 label.') : (t.labels_many || ':count labels.').replace(':count', labels.length)}
                {' '}
                {t.labels_hint || 'Stick one inside each copy before it goes on the shelf — a book without one cannot be issued or taken back.'}
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
                    {t.labels_none || 'No copies yet, so nothing to label.'}
                </p>
            )}
        </AppShell>
    );
}
