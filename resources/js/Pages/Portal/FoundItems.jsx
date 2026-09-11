import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

export default function FoundItems({ items = [], filters = {} }) {
    const [q, setQ] = useState(filters.q || '');

    return (
        <AppShell title="Lost and found">
            <p className="mb-4 text-sm text-gray-600">
                Things handed in to the school this year and not yet collected. If you
                recognise something, ask where it is being held.
            </p>

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <input
                    type="search"
                    className="form-input w-64 text-sm"
                    placeholder="Search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && router.get('/portal/found-items', { q }, { preserveState: true, replace: true })}
                />
            </div>

            <ul className="grid gap-2 sm:grid-cols-2">
                {items.map((item) => (
                    <li key={item.id} className="rounded-lg border bg-white p-3">
                        <p className="font-medium">{item.title}</p>
                        {item.description && <p className="mt-1 text-sm text-gray-600">{item.description}</p>}
                        <p className="mt-2 text-xs text-gray-500">
                            Found {item.found_at}
                            {item.location ? ` · ${item.location}` : ''}
                        </p>
                        {item.held_at && <p className="text-xs text-gray-500">Held at {item.held_at}</p>}
                        {item.has_photo && (
                            <a
                                href={`/portal/found-items/${item.id}/photo`}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-1 inline-block text-xs text-[#7C2D37] underline"
                            >
                                See photo
                            </a>
                        )}
                    </li>
                ))}
            </ul>

            {items.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Nothing is waiting to be collected.
                </p>
            )}
        </AppShell>
    );
}
