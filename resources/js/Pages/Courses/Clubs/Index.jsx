import { Link } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ clubs = [], t = {} }) {
    return (
        <AppShell title={t.clubs_title || 'Clubs'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.clubs_intro || 'A club is a course of the club type, so it has a plan, sessions and a roster like any other course. Create one from Courses; this screen is the roster side.'}
            </p>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">{t.clubs_col_club || 'Club'}</th>
                            <th className="px-3 py-2 text-start">{t.clubs_col_members || 'Members'}</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {clubs.map((club) => (
                            <tr key={club.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/academics/clubs/${club.id}`} className="text-[#7C2D37] underline">
                                        {club.title}
                                    </Link>
                                </td>
                                <td className="px-3 py-2 tabular-nums">{club.members}</td>
                                <td className="px-3 py-2">
                                    <Link href={`/academics/clubs/${club.id}/attendance-sheet`}
                                        className="text-xs text-[#7C2D37] underline">{t.clubs_sheet || 'Attendance sheet'}</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {clubs.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">
                        {t.clubs_none || 'No clubs yet. Create a course and set its type to club.'}
                    </p>
                )}
            </div>
        </AppShell>
    );
}
