import AppShell from '../../../Layouts/AppShell';

/**
 * Paper. A club leader takes a register on a clipboard in a hall with no
 * screen, and types it up afterwards — so the columns are deliberately blank.
 */
export default function AttendanceSheet({ club, members = [], t = {} }) {
    const weeks = Array.from({ length: 8 }, (_, i) => i + 1);

    return (
        <AppShell title={(t.clubs_sheet_title || ':club — attendance sheet').replace(':club', club.title)}>
            <div className="mb-3 flex flex-wrap items-center gap-3 print:hidden">
                <a href={`/academics/clubs/${club.id}`} className="text-sm text-[#7C2D37] underline">{t.clubs_back || 'Back to roster'}</a>
                <button onClick={() => window.print()} className="btn-secondary text-sm">{t.clubs_print || 'Print'}</button>
                <span className="text-xs text-gray-500">{t.clubs_blank_weeks || 'Eight blank weeks — fill in by hand.'}</span>
            </div>

            <div className="rounded-lg border bg-white p-4">
                <h2 className="mb-1 text-lg font-bold">{club.title}</h2>
                <p className="mb-4 text-xs text-gray-500">
                    {t.clubs_leader || 'Leader'} ______________________  {t.clubs_term || 'Term'} ____________  {t.clubs_room || 'Room'} ____________
                </p>

                <table className="min-w-full border text-sm">
                    <thead>
                        <tr>
                            <th className="border px-2 py-1 text-start">{t.clubs_col_member || 'Member'}</th>
                            {weeks.map((w) => (
                                <th key={w} className="border px-2 py-1 text-center text-xs font-normal">{w}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => (
                            <tr key={member.enrollment_id}>
                                <td className="border px-2 py-2">
                                    {member.name}
                                    {!member.on_roll && <span className="ms-1 text-xs text-gray-500">{t.clubs_visitor_mark || '(visitor)'}</span>}
                                </td>
                                {weeks.map((w) => <td key={w} className="border px-2 py-2" />)}
                            </tr>
                        ))}
                    </tbody>
                </table>

                {members.length === 0 && (
                    <p className="mt-4 text-sm text-gray-500">{t.clubs_nobody || 'Nobody has joined yet.'}</p>
                )}
            </div>
        </AppShell>
    );
}
