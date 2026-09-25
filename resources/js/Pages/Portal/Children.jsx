import AppShell from '../../Layouts/AppShell';

export default function Children({ children, pending = [] }) {
    return (
        <AppShell title="My children">
            {pending.length > 0 && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4" role="status" data-testid="pending-links">
                    <p className="font-medium text-amber-900">Awaiting the office</p>
                    <p className="mt-1 text-sm text-amber-800">
                        The office checks each parent link before a child&rsquo;s records are shown. These will appear below once confirmed.
                    </p>
                    <ul className="mt-2 list-disc ps-5 text-sm text-amber-900">
                        {pending.map((child) => (
                            <li key={child.id}>
                                {child.first_name} {child.last_name}
                                {child.relationship ? ` · ${child.relationship}` : ''}
                                {child.verification_status === 'rejected' ? ' · not accepted' : ''}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
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
                                <td className="px-3 py-4 text-gray-500" colSpan={4}>
                                    {pending.length > 0 ? 'No confirmed children yet.' : 'No linked children.'}
                                </td>
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
