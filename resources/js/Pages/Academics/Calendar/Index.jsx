import { router, useForm, usePage } from '@inertiajs/react';
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

const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

export default function Index({ yearId, yearStart, yearEnd, years, types, days, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const form = useForm({
        academic_year_id: yearId || '',
        date: '',
        type: types[0] || 'holiday',
        title: '',
        title_arabic: '',
        title_dhivehi: '',
        affects_timetable: true,
        // E11b: closed days reach families by default; anything else is the
        // office's until somebody says otherwise.
        is_public: true,
        notes: '',
    });

    const start = yearStart ? new Date(yearStart) : new Date();
    const months = [];
    for (let cursor = new Date(start.getFullYear(), start.getMonth(), 1); months.length < 12; cursor.setMonth(cursor.getMonth() + 1)) {
        months.push({ year: cursor.getFullYear(), month: cursor.getMonth() });
    }

    const byDate = Object.fromEntries(days.map((day) => [day.date, day]));
    // In the page's language (BACKLOG C21, slice OA2). A day's type is a
    // code, named here; its title is the office's, in the page's language
    // where the office gave one. The months and weekdays are the book's: a
    // browser names no month in Dhivehi.
    const typeName = (type) => t[`calendar_type_${type}`] || type;
    const title = (day) => ({ dv: day.title_dhivehi, ar: day.title_arabic }[locale]) || day.title;
    const yesNo = (value) => (value ? (t.yes || 'Yes') : (t.no || 'No'));

    return (
        <AppShell title={t.calendar_title || 'School calendar'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    aria-label={t.year || 'Year'}
                    value={yearId || ''}
                    onChange={(e) => router.get(`/academics/calendar?academic_year_id=${e.target.value}`)}
                >
                    <option value="">{t.year || 'Year'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/calendar/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/calendar', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label={t.date || 'Date'} error={form.errors.date}>
                    <input className="form-input w-full" type="date" aria-label={t.date || 'Date'} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
                <Field label={t.type || 'Type'} error={form.errors.type}>
                    <select className="form-input w-full" aria-label={t.type || 'Type'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{typeName(type)}</option>)}
                    </select>
                </Field>
                <Field label={t.title_en || 'Title (EN)'} error={form.errors.title}>
                    <input className="form-input w-full" aria-label={t.title_en || 'Title (EN)'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label={t.title_ar || 'Title (AR)'}>
                    <input className="form-input w-full" dir="rtl" aria-label={t.title_ar || 'Title (AR)'} value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                </Field>
                <Field label={t.title_dv || 'Title (DV)'}>
                    <input className="form-input w-full" dir="rtl" aria-label={t.title_dv || 'Title (DV)'} value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                </Field>
                <Field label={t.notes || 'Notes'}>
                    <input className="form-input w-full" aria-label={t.notes || 'Notes'} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.affects_timetable} onChange={(e) => form.setData('affects_timetable', e.target.checked)} />
                    {t.calendar_affects || 'Affects timetable'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_public} onChange={(e) => form.setData('is_public', e.target.checked)} />
                    {t.calendar_public || 'Show to families and teachers'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.calendar_add || 'Add day'}</button>
            </form>

            <div className="mb-6 grid gap-3 md:grid-cols-3">
                {months.map(({ year, month }) => (
                    <div key={`${year}-${month}`} className="rounded-lg border bg-white p-3">
                        <p className="mb-2 text-sm font-medium">{t[`month_${month + 1}`] || new Date(year, month, 1).toLocaleString('en', { month: 'long' })} {year}</p>
                        <div className="grid grid-cols-7 gap-1 text-center text-[11px]">
                            {WEEKDAYS.map((day) => <div key={day} className="text-gray-400">{t[`weekday_initial_${day}`] || day.slice(0, 1).toUpperCase()}</div>)}
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
                                        title={entry ? `${typeName(entry.type)}: ${title(entry)}` : ''}
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
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.calendar_affects || 'Affects timetable'}</th>
                            <th className="px-3 py-2">{t.calendar_col_public || 'Shown to families'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {days.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.calendar_none || 'No calendar days yet.'}</td></tr>
                        )}
                        {days.map((day) => (
                            <tr key={day.id} className="border-t">
                                <td className="px-3 py-2">{day.date}</td>
                                <td className="px-3 py-2">{typeName(day.type)}</td>
                                <td className="px-3 py-2">{title(day)}</td>
                                <td className="px-3 py-2">{yesNo(day.affects_timetable)}</td>
                                <td className="px-3 py-2">{yesNo(day.is_public)}</td>
                                <td className="px-3 py-2">
                                    <button type="button" className="text-sm text-red-700 underline" onClick={() => router.delete(`/academics/calendar/${day.id}`)}>{t.remove || 'Remove'}</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
