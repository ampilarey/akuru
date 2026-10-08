import { Link } from '@inertiajs/react';
import AppShell from '../../../../Layouts/AppShell';

export default function MushafIndex({ mushafs = [], can_manage = false, t = {} }) {
    const yesNo = (value) => (value ? (t.mushaf_yes || 'Yes') : (t.mushaf_no || 'No'));

    return (
        <AppShell title={t.mushaf_title || 'Qur’an mushafs'}>
            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-600">
                    {t.mushaf_intro || 'The source editions every Qur’an screen reads from. One is active at a time.'}
                </p>
                {can_manage && (
                    <Link className="btn-primary" href="/quran/mushafs/create">{t.mushaf_upload || 'Upload mushaf'}</Link>
                )}
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.mushaf_col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.mushaf_col_pages || 'Pages'}</th>
                            <th className="px-3 py-2">{t.mushaf_col_active || 'Active'}</th>
                            <th className="px-3 py-2">{t.mushaf_col_locked || 'Locked'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {mushafs.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={5}>
                                    {t.mushaf_none || 'No mushaf uploaded yet.'}
                                </td>
                            </tr>
                        )}
                        {mushafs.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.page_count ?? '—'}</td>
                                <td className="px-3 py-2">{yesNo(row.is_active)}</td>
                                <td className="px-3 py-2">{yesNo(row.locked)}</td>
                                <td className="px-3 py-2">
                                    <Link className="text-[#7C2D37] hover:underline" href={`/quran/mushafs/${row.id}`}>
                                        {t.mushaf_manage || 'Manage'}
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
