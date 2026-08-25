import AppShell from '../../Layouts/AppShell';

export default function Holidays({ holidays }) {
    return (
        <AppShell title="Holidays">
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Title</th>
                        </tr>
                    </thead>
                    <tbody>
                        {holidays.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>No holidays published for the active year.</td></tr>
                        )}
                        {holidays.map((day) => (
                            <tr key={day.id} className="border-t">
                                <td className="px-3 py-2">{day.date}</td>
                                <td className="px-3 py-2">{day.type}</td>
                                <td className="px-3 py-2">{day.title}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
