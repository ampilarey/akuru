import AppShell from '../../Layouts/AppShell';

export default function Children({ children }) {
    return (
        <AppShell title="My children">
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Relationship</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {children.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={4}>No linked children.</td>
                            </tr>
                        )}
                        {children.map((child) => (
                            <tr key={child.id} className="border-t">
                                <td className="px-3 py-2">{child.first_name} {child.last_name}</td>
                                <td className="px-3 py-2">{child.student_id}</td>
                                <td className="px-3 py-2">{child.relationship}</td>
                                <td className="px-3 py-2">{child.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
