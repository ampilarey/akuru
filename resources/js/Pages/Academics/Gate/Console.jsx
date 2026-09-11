import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function SearchResult({ child }) {
    const record = (direction) =>
        router.post('/academics/gate/record', { student_id: child.id, direction }, { preserveScroll: true });

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 border-t px-3 py-2">
            <div>
                <p className="font-medium">
                    {child.name}
                    {child.indistinguishable && (
                        <span className="ms-2 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                            same name as another pupil — check the number
                        </span>
                    )}
                </p>
                <p className="text-xs text-gray-500">
                    {[child.student_number, child.current_class].filter(Boolean).join(' · ') || '—'}
                </p>
            </div>
            <div className="flex items-center gap-2">
                {child.current && (
                    <span className="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                        currently {child.current === 'in' ? 'in' : 'out'}
                    </span>
                )}
                <button className="btn-primary text-xs" onClick={() => record('in')}>Arrived</button>
                <button className="btn-secondary text-xs" onClick={() => record('out')}>Left</button>
            </div>
        </li>
    );
}

export default function Console({ date, q = '', matches = [], movements = [], in_count = 0, out_count = 0 }) {
    const [query, setQuery] = useState(q);

    const search = (value) => {
        setQuery(value);
        router.get('/academics/gate', { date, q: value }, { preserveState: true, replace: true });
    };

    return (
        <AppShell title="At the gate">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <input
                    type="date"
                    className="form-input text-sm"
                    value={date}
                    onChange={(e) => router.get('/academics/gate', { date: e.target.value, q: query }, { preserveState: true, replace: true })}
                />
                <span className="rounded bg-emerald-50 px-2 py-0.5 text-sm text-emerald-800">In: {in_count}</span>
                <span className="rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-700">Out: {out_count}</span>
            </div>

            <div className="mb-6 rounded-lg border bg-white p-4">
                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">Find a pupil</span>
                    <input
                        className="form-input w-full"
                        placeholder="Name or student number"
                        value={query}
                        onChange={(e) => search(e.target.value)}
                    />
                </label>
                {query.length > 0 && query.length < 2 && (
                    <p className="mt-2 text-xs text-gray-500">Keep typing — at least two letters.</p>
                )}
                {query.length >= 2 && matches.length === 0 && (
                    <p className="mt-2 text-sm text-gray-600">Nobody matches “{query}”.</p>
                )}
                {matches.length > 0 && (
                    <ul className="mt-3">{matches.map((child) => <SearchResult key={child.id} child={child} />)}</ul>
                )}
            </div>

            <h2 className="mb-2 text-sm font-semibold">Recorded today ({movements.filter((m) => !m.voided).length})</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">Pupil</th>
                            <th className="px-3 py-2 text-start">Direction</th>
                            <th className="px-3 py-2 text-start">Time</th>
                            <th className="px-3 py-2 text-start">Recorded</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {movements.map((m) => (
                            <tr key={m.id} className={`border-t ${m.voided ? 'text-gray-400 line-through' : ''}`}>
                                <td className="px-3 py-2">
                                    {m.student}
                                    {m.student_number && <span className="ms-2 text-xs text-gray-500">{m.student_number}</span>}
                                </td>
                                <td className="px-3 py-2">{m.direction_label}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">{m.at?.slice(11, 16)}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">
                                    {m.recorded_by ?? m.source_label}
                                    {m.note && <span className="block">“{m.note}”</span>}
                                </td>
                                <td className="px-3 py-2">
                                    {!m.voided && (
                                        <button
                                            className="text-xs text-[#7C2D37] underline"
                                            onClick={() => router.post(`/academics/gate/${m.id}/void`, {}, { preserveScroll: true })}
                                        >
                                            Take back
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {movements.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">Nothing recorded yet today.</p>
                )}
            </div>

            <p className="mt-4 text-xs text-gray-500">
                A mistake is taken back, never deleted — a family told their child left at a time
                they did not is owed an explanation, and a removed row cannot give one.
            </p>
        </AppShell>
    );
}
