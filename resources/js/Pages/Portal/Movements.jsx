import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Movements({ date, has_children = false, movements = [] }) {
    return (
        <AppShell title="Arrivals and departures">
            <div className="mb-4">
                <input
                    type="date"
                    className="form-input text-sm"
                    value={date}
                    onChange={(e) => router.get('/portal/movements', { date: e.target.value }, { preserveState: true, replace: true })}
                />
            </div>

            {!has_children && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    No children are linked to your account. The office can put that right.
                </p>
            )}

            {has_children && movements.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Nothing recorded for this day. The school records arrivals and departures at
                    the gate; a quiet day here means nothing was logged, not that nobody came.
                </p>
            )}

            <ul className="grid gap-2">
                {movements.map((m) => (
                    <li key={m.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-white p-3 text-sm">
                        <div>
                            <p className="font-medium">{m.student}</p>
                            <p className="text-xs text-gray-500">Recorded: {m.source_label}</p>
                        </div>
                        <div className="text-end">
                            <p className={m.direction === 'in' ? 'text-emerald-700' : 'text-[#7C2D37]'}>
                                {m.direction_label}
                            </p>
                            <p className="text-xs text-gray-600">{m.at?.slice(11, 16)}</p>
                        </div>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
