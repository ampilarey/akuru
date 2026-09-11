import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/** Borrower cards: the pupil's number as a barcode a desk scanner can read. */
export default function BorrowerCards({ q = '', cards = [] }) {
    const [query, setQuery] = useState(q);

    return (
        <AppShell title="Borrower cards">
            <div className="mb-4 print:hidden">
                <input
                    className="form-input w-full"
                    placeholder="Search pupils by name, class or number"
                    value={query}
                    onChange={(e) => { setQuery(e.target.value); router.get('/circulation/cards', { q: e.target.value }, { preserveState: true, replace: true }); }}
                />
                {cards.length > 0 && (
                    <button className="btn-primary mt-3 text-sm" onClick={() => window.print()}>Print these cards</button>
                )}
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {cards.map((card) => (
                    <div key={card.id} className="rounded border bg-white p-3 text-center">
                        <p className="text-sm font-medium">{card.name}</p>
                        {card.current_class && <p className="text-xs text-gray-500">{card.current_class}</p>}
                        {card.barcode
                            ? <div className="my-2 flex justify-center" dangerouslySetInnerHTML={{ __html: card.barcode }} />
                            : <p className="my-2 text-xs text-[#7C2D37]">No student number — a card cannot be printed.</p>}
                        <p className="font-mono text-sm">{card.student_number ?? '—'}</p>
                    </div>
                ))}
            </div>
            {query.length >= 2 && cards.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">Nobody matches “{query}”.</p>
            )}
        </AppShell>
    );
}
