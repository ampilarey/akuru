import { Link, router, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Today({ date, teacherId, registers }) {
    const { errors } = usePage().props;

    return (
        <AppShell title="Today's registers">
            {errors?.status && <p className="mb-3 text-sm text-red-600">{errors.status}</p>}
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <input
                    className="form-input"
                    type="date"
                    value={date}
                    onChange={(e) => router.get(`/academics/registers/today?date=${e.target.value}`)}
                />
                {!teacherId && <p className="text-sm text-gray-600">No teacher profile is linked to this login.</p>}
            </div>
            {registers.length === 0 ? (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">No registers for this date.</p>
            ) : (
                <ul className="grid gap-3">
                    {registers.map((item) => (
                        <li key={item.id} className="rounded-lg border bg-white p-4">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{item.subject_name}</p>
                                    <p className="text-sm text-gray-600">{item.class_name}</p>
                                    <p className="text-xs text-gray-500">
                                        {item.period_name || 'Time-based'}
                                        {item.period_start ? ` · ${String(item.period_start).slice(0, 5)}` : ''}
                                    </p>
                                </div>
                                <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs uppercase">{item.status}</span>
                            </div>
                            <Link
                                href={`/academics/registers/${item.id}`}
                                className="mt-3 inline-block text-sm text-[#7C2D37] underline"
                            >
                                {item.status === 'submitted' || item.status === 'locked' ? 'View register' : 'Fill register'}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </AppShell>
    );
}
