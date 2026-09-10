import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

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

function Periods({ day, emptyLabel }) {
    if (day.is_school_day === false) {
        return <p className="text-sm text-gray-600">{day.note || 'No lessons — school closed.'}</p>;
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
                            Covering for {period.is_covering_for}
                        </span>
                    )}
                    {period.is_substituted && (
                        <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">
                            {period.substitute_teacher
                                ? `Covered by ${period.substitute_teacher}`
                                : `Cover ${period.cover_status || 'requested'}`}
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
}) {
    return (
        <AppShell title={title}>
            {teacherId === null && (
                <p className="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                    No teacher profile is linked to this login, so lessons and registers are empty.
                    Messages and notices below are still yours.
                </p>
            )}

            <section className="mb-6 rounded-lg border bg-white p-4">
                <h2 className="mb-2 text-sm font-semibold">Today · {today.date}</h2>
                <Periods day={today} emptyLabel="No lessons on your timetable today." />
            </section>

            {next && (
                <section className="mb-6 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 text-sm font-semibold">
                        Next · {next.day_name} {next.date}
                    </h2>
                    <Periods day={next} emptyLabel="Nothing scheduled." />
                </section>
            )}

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {tiles.map((tile) => <Tile key={tile.key} tile={tile} />)}
            </div>

            {unfilled.length > 0 && (
                <section className="rounded-lg border bg-white p-4">
                    <h2 className="mb-2 text-sm font-semibold">Registers you still owe</h2>
                    <ul className="divide-y">
                        {unfilled.slice(0, 10).map((row) => (
                            <li key={row.id} className="flex flex-wrap items-baseline justify-between gap-2 py-2">
                                <span className="text-sm">
                                    {row.date} · {row.subject_name} · {row.class_name}
                                </span>
                                <Link href={`/academics/registers/${row.id}`} className="text-sm text-[#7C2D37] underline">
                                    Fill it
                                </Link>
                            </li>
                        ))}
                    </ul>
                    {unfilled.length > 10 && (
                        <p className="mt-2 text-xs text-gray-500">
                            and {unfilled.length - 10} more.
                        </p>
                    )}
                </section>
            )}
        </AppShell>
    );
}
