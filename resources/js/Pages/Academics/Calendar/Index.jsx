import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block text-gray-600">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

function monthCells(year, month) {
    const first = new Date(year, month, 1);
    const pad = first.getDay();
    const count = new Date(year, month + 1, 0).getDate();
    return [...Array(pad).fill(null), ...Array.from({ length: count }, (_, index) => index + 1)];
}

export default function Index({ yearId, yearStart, yearEnd, years, types, days }) {
    const form = useForm({
        academic_year_id: yearId || '',
        date: '',
        type: types[0] || 'holiday',
        title: '',
        title_arabic: '',
        title_dhivehi: '',
        affects_timetable: true,
        notes: '',
    });

    const start = yearStart ? new Date(yearStart) : new Date();
    const months = [];
    for (let cursor = new Date(start.getFullYear(), start.getMonth(), 1); months.length < 12; cursor.setMonth(cursor.getMonth() + 1)) {
        months.push({ year: cursor.getFullYear(), month: cursor.getMonth() });
    }

    const byDate = Object.fromEntries(days.map((day) => [day.date, day]));

    return (
        <AppShell title="School calendar">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    value={yearId || ''}
                    onChange={(e) => router.get(`/academics/calendar?academic_year_id=${e.target.value}`)}
                >
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/calendar/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/calendar', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label="Date" error={form.errors.date}>
                    <input className="form-input w-full" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
                <Field label="Type" error={form.errors.type}>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </Field>
                <Field label="Title (EN)" error={form.errors.title}>
                    <input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="Title (AR)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                </Field>
                <Field label="Title (DV)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                </Field>
                <Field label="Notes">
                    <input className="form-input w-full" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.affects_timetable} onChange={(e) => form.setData('affects_timetable', e.target.checked)} />
                    Affects timetable
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Add day</button>
            </form>

            <div className="mb-6 grid gap-3 md:grid-cols-3">
                {months.map(({ year, month }) => (
                    <div key={`${year}-${month}`} className="rounded-lg border bg-white p-3">
                        <p className="mb-2 text-sm font-medium">{new Date(year, month, 1).toLocaleString('en', { month: 'long', year: 'numeric' })}</p>
                        <div className="grid grid-cols-7 gap-1 text-center text-[11px]">
                            {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map((label, index) => <div key={`${label}-${index}`} className="text-gray-400">{label}</div>)}
                            {monthCells(year, month).map((day, index) => {
                                if (!day) {
                                    return <div key={`pad-${index}`} />;
                                }
                                const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                                const entry = byDate[iso];
                                return (
                                    <div
                                        key={iso}
                                        className={`rounded px-1 py-1 ${entry ? 'bg-[#F3EBE0] text-[#7C2D37] font-medium' : 'text-gray-600'}`}
                                        title={entry ? `${entry.type}: ${entry.title}` : ''}
                                    >
                                        {day}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Affects timetable</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {days.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No calendar days yet.</td></tr>
                        )}
                        {days.map((day) => (
                            <tr key={day.id} className="border-t">
                                <td className="px-3 py-2">{day.date}</td>
                                <td className="px-3 py-2">{day.type}</td>
                                <td className="px-3 py-2">{day.title}</td>
                                <td className="px-3 py-2">{day.affects_timetable ? 'yes' : 'no'}</td>
                                <td className="px-3 py-2">
                                    <button type="button" className="text-sm text-red-700 underline" onClick={() => router.delete(`/academics/calendar/${day.id}`)}>Remove</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
