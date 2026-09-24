import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import QrCameraScanner from '../../../Components/QrCameraScanner';
import { readPreference, writePreference } from '../../../Platform';
import AppShell from '../../../Layouts/AppShell';

/**
 * E18 gate cards (owner decision 11). One box takes every way a card arrives:
 * a handheld USB or Bluetooth scanner types the code and presses Enter; the
 * camera hands over what it decodes; a person can type the code printed under
 * the QR. The direction is chosen once and stays, because a morning at the
 * gate is all arrivals and an afternoon all departures.
 */
function ScanPanel() {
    const [direction, setDirection] = useState(() => readPreference('gate.direction', new Date().getHours() < 12 ? 'in' : 'out'));
    const [code, setCode] = useState('');
    const [camera, setCamera] = useState(false);
    const box = useRef(null);

    useEffect(() => writePreference('gate.direction', direction), [direction]);

    const submit = useCallback((value) => {
        const scanned = String(value || '').trim();
        if (scanned === '') return;
        router.post('/academics/gate/scan', { code: scanned, direction }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                setCode('');
                box.current?.focus();
            },
        });
    }, [direction]);

    return (
        <div className="mb-6 rounded-lg border-2 border-[#7C2D37] bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-semibold">Scan a gate card</h2>
                <div className="flex overflow-hidden rounded border" role="group" aria-label="Direction">
                    {[['in', 'Arriving'], ['out', 'Leaving']].map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={direction === value}
                            onClick={() => setDirection(value)}
                            className={`px-3 py-1 text-sm ${direction === value ? 'bg-[#7C2D37] text-white' : 'bg-white text-[#7C2D37]'}`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>
            <form
                className="mt-3 flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    submit(code);
                }}
            >
                <input
                    ref={box}
                    autoFocus
                    name="gate-code"
                    autoComplete="off"
                    className="form-input min-w-0 flex-1"
                    placeholder="Scan with a handheld scanner, or type the code under the QR"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                />
                <button type="submit" className="btn-primary">Record</button>
                <button type="button" className="btn-secondary" onClick={() => setCamera((open) => !open)}>
                    {camera ? 'Close camera' : 'Use camera'}
                </button>
            </form>
            {camera && <QrCameraScanner onCode={submit} onClose={() => setCamera(false)} />}
            <p className="mt-2 text-xs text-gray-500">
                No card? Find the pupil by name below. Cards are issued and printed on{' '}
                <Link href="/academics/gate/cards" className="text-[#7C2D37] underline">Gate cards</Link>.
            </p>
        </div>
    );
}

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

            <ScanPanel />

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
                                    {m.recorded_by && m.source && m.source !== 'manual' && ` · ${m.source_label}`}
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
