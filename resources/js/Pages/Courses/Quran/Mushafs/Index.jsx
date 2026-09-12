import { Link } from '@inertiajs/react';
import AppShell from '../../../../Layouts/AppShell';

export default function MushafIndex({ mushafs = [], can_manage = false }) {
    return (
        <AppShell title="Qur’an mushafs">
            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-600">
                    The source editions every Qur’an screen reads from. One is active at a time.
                </p>
                {can_manage && (
                    <Link className="btn-primary" href="/quran/mushafs/create">Upload mushaf</Link>
                )}
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Pages</th>
                            <th className="px-3 py-2">Active</th>
                            <th className="px-3 py-2">Locked</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {mushafs.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={5}>
                                    No mushaf uploaded yet.
                                </td>
                            </tr>
                        )}
                        {mushafs.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.page_count ?? '—'}</td>
                                <td className="px-3 py-2">{row.is_active ? 'Yes' : 'No'}</td>
                                <td className="px-3 py-2">{row.locked ? 'Yes' : 'No'}</td>
                                <td className="px-3 py-2">
                                    <Link className="text-[#7C2D37] hover:underline" href={`/quran/mushafs/${row.id}`}>
                                        Manage
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
