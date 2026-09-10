import AppShell from '../../Layouts/AppShell';

const TYPE_LABELS = {
    holiday: 'Holiday',
    closure: 'Closed',
    event: 'Event',
    exam_day: 'Exam day',
    special_schedule: 'Special schedule',
};

function Rows({ days, empty }) {
    if (days.length === 0) {
        return <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{empty}</p>;
    }

    return (
        <ul className="divide-y rounded-lg border bg-white">
            {days.map((day) => (
                <li key={day.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-3">
                    <span className="w-24 shrink-0 text-sm text-gray-500">{day.date}</span>
                    <span className="text-sm font-semibold">{day.title}</span>
                    <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">
                        {TYPE_LABELS[day.type] || day.type}
                    </span>
                    {/* The one fact a family acts on, said rather than implied. */}
                    {day.no_school && (
                        <span className="rounded bg-[#7C2D37] px-2 py-0.5 text-xs font-semibold text-white">
                            No school
                        </span>
                    )}
                    {(day.title_dhivehi || day.title_arabic) && (
                        <span className="w-full text-xs text-gray-500" dir="rtl">
                            {day.title_dhivehi || day.title_arabic}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}

export default function SchoolCalendar({ upcoming = [], past = [] }) {
    return (
        <AppShell title="School calendar">
            <p className="mb-4 text-sm text-gray-600">
                Holidays, closures, events and exam days for the current school year.
            </p>

            <h2 className="mb-2 text-sm font-semibold">Coming up</h2>
            <Rows days={upcoming} empty="Nothing published for the rest of this year yet." />

            {past.length > 0 && (
                <>
                    <h2 className="mb-2 mt-6 text-sm font-semibold">Earlier this year</h2>
                    <Rows days={past} empty="" />
                </>
            )}
        </AppShell>
    );
}
