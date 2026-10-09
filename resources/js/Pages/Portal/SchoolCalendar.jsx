import { usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function Rows({ days, empty, t, locale }) {
    if (days.length === 0) {
        return <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{empty}</p>;
    }

    return (
        <ul className="divide-y rounded-lg border bg-white">
            {days.map((day) => {
                // The office may name a day in Dhivehi and Arabic too; a page
                // in one of those reads that name first (BACKLOG C21, slice
                // PT1a), and an English page keeps it as the line beneath.
                const own = { dv: day.title_dhivehi, ar: day.title_arabic }[locale];
                const beneath = locale === 'en' ? (day.title_dhivehi || day.title_arabic) : null;

                return (
                    <li key={day.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-3">
                        <span className="w-24 shrink-0 text-sm text-gray-500">{day.date}</span>
                        <span className="text-sm font-semibold">{own || day.title}</span>
                        <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">
                            {t[`calendar_type_${day.type}`] || day.type}
                        </span>
                        {/* The one fact a family acts on, said rather than implied. */}
                        {day.no_school && (
                            <span className="rounded bg-[#7C2D37] px-2 py-0.5 text-xs font-semibold text-white">
                                {t.calendar_no_school || 'No school'}
                            </span>
                        )}
                        {beneath && (
                            <span className="w-full text-xs text-gray-500" dir="rtl">
                                {beneath}
                            </span>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export default function SchoolCalendar({ upcoming = [], past = [], t = {} }) {
    const locale = usePage().props.locale || 'en';

    return (
        <AppShell title={t.calendar_title || 'School calendar'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.calendar_intro || 'Holidays, closures, events and exam days for the current school year.'}
            </p>

            <h2 className="mb-2 text-sm font-semibold">{t.calendar_upcoming || 'Coming up'}</h2>
            <Rows days={upcoming} t={t} locale={locale} empty={t.calendar_none || 'Nothing published for the rest of this year yet.'} />

            {past.length > 0 && (
                <>
                    <h2 className="mb-2 mt-6 text-sm font-semibold">{t.calendar_past || 'Earlier this year'}</h2>
                    <Rows days={past} t={t} locale={locale} empty="" />
                </>
            )}
        </AppShell>
    );
}
