import { Link, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import WorkspaceTiles from '../../Components/WorkspaceTiles';

function Tile({ tile }) {
    const body = (
        <>
            <div className="flex items-baseline justify-between gap-2">
                <span className="text-sm font-semibold">{tile.label}</span>
                {tile.badge ? (
                    <span className="rounded-full bg-[#7C2D37] px-2 py-0.5 text-xs font-semibold text-white">
                        {tile.badge}
                    </span>
                ) : null}
            </div>
            <p className="mt-1 text-xs text-gray-600">{tile.status}</p>
        </>
    );

    if (!tile.href) {
        return <div className="rounded-lg border bg-white p-3">{body}</div>;
    }

    return (
        <Link href={tile.href} className="block rounded-lg border bg-white p-3 hover:border-[#7C2D37]">
            {body}
        </Link>
    );
}

// A closed day by the name the office gave it for the page's language, as
// the school calendar shows it (slice PT1a).
function closedNote(day, locale, t) {
    const own = { dv: day.note_dhivehi, ar: day.note_arabic }[locale];

    return own || day.note || t.teacher_school_closed || 'No lessons — school closed.';
}

function Periods({ day, emptyLabel, t, locale }) {
    if (day.is_school_day === false) {
        return <p className="text-sm text-gray-600">{closedNote(day, locale, t)}</p>;
    }

    if (!day.periods || day.periods.length === 0) {
        return <p className="text-sm text-gray-600">{emptyLabel}</p>;
    }

    return (
        <ul className="divide-y">
            {day.periods.map((period) => (
                <li key={period.timetable_entry_id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2">
                    <span className="w-16 shrink-0 text-xs text-gray-500">
                        {period.starts_at || period.period_name}
                    </span>
                    <span className="text-sm font-semibold">{period.subject || '—'}</span>
                    <span className="text-sm text-gray-600">{period.class}</span>
                    {period.room && <span className="text-xs text-gray-500">{period.room}</span>}
                    {period.is_covering_for && (
                        <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">
                            {(t.teacher_covering_for || 'Covering for :name').replace(':name', period.is_covering_for)}
                        </span>
                    )}
                    {period.is_substituted && (
                        <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">
                            {period.substitute_teacher
                                ? (t.teacher_covered_by || 'Covered by :name').replace(':name', period.substitute_teacher)
                                : (t[`teacher_cover_${period.cover_status || 'open'}`] || t.teacher_cover_open || 'Cover requested')}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}

export default function TeacherHome({
    title = 'My day',
    teacherId = null,
    today = { periods: [] },
    next = null,
    unfilled = [],
    tiles = [],
    t = {},
}) {
    // The title and the tiles come from the server in the page's language;
    // the rest is the `portal` book's (BACKLOG C21, slice PT4). A weekday is
    // named by the book, since a browser has no Dhivehi weekdays to give.
    const locale = usePage().props.locale || 'en';
    const weekday = next?.day_name ? next.day_name.toLowerCase() : null;

    return (
        <AppShell title={title}>
            {teacherId === null && (
                <p className="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                    {t.teacher_no_profile || 'No teacher profile is linked to this login, so lessons and registers are empty. Messages and notices below are still yours.'}
                </p>
            )}

            <section className="mb-6 rounded-lg border bg-white p-4">
                <h2 className="mb-2 text-sm font-semibold">{(t.teacher_today || 'Today · :date').replace(':date', today.date)}</h2>
                <Periods day={today} t={t} locale={locale} emptyLabel={t.teacher_no_lessons || 'No lessons on your timetable today.'} />
            </section>

            {next && (
                <section className="mb-6 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 text-sm font-semibold">
                        {(t.teacher_next || 'Next · :day :date').replace(':day', (weekday && t[`weekday_${weekday}`]) || next.day_name).replace(':date', next.date)}
                    </h2>
                    <Periods day={next} t={t} locale={locale} emptyLabel={t.teacher_nothing_scheduled || 'Nothing scheduled.'} />
                </section>
            )}

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {tiles.map((tile) => <Tile key={tile.key} tile={tile} />)}
            </div>

            {/* The rest of the School's menu for a teacher, as tiles (SIGN_IN_PLAN ID5). */}
            <WorkspaceTiles skip={tiles.map((tile) => tile.href)} className="mb-6" />

            {unfilled.length > 0 && (
                <section className="rounded-lg border bg-white p-4">
                    <h2 className="mb-2 text-sm font-semibold">{t.teacher_owed || 'Registers you still owe'}</h2>
                    <ul className="divide-y">
                        {unfilled.slice(0, 10).map((row) => (
                            <li key={row.id} className="flex flex-wrap items-baseline justify-between gap-2 py-2">
                                <span className="text-sm">
                                    {row.date} · {row.subject_name} · {row.class_name}
                                </span>
                                <Link href={`/academics/registers/${row.id}`} className="text-sm text-[#7C2D37] underline">
                                    {t.teacher_fill_it || 'Fill it'}
                                </Link>
                            </li>
                        ))}
                    </ul>
                    {unfilled.length > 10 && (
                        <p className="mt-2 text-xs text-gray-500">
                            {(t.teacher_more || 'and :count more.').replace(':count', unfilled.length - 10)}
                        </p>
                    )}
                </section>
            )}
        </AppShell>
    );
}
