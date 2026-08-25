import { router, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Catalog({ rows }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.catalog_title || 'Learn catalog'}>
            <p className="mb-4 text-sm text-gray-600">{t.catalog_intro || 'Published self-learning courses.'}</p>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.course || 'Course'}</th>
                            <th className="px-3 py-2">{t.progress || 'Progress'}</th>
                            <th className="px-3 py-2">{t.action || 'Action'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.no_published || 'No published courses yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <p className="font-medium">{row.title}</p>
                                    {row.short_desc && <p className="text-xs text-gray-500">{row.short_desc}</p>}
                                </td>
                                <td className="px-3 py-2">{row.enrolled ? `${row.progress_percentage}%` : '—'}</td>
                                <td className="px-3 py-2">
                                    {row.enrolled ? (
                                        <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${row.id}`}>{t.open || 'Open'}</a>
                                    ) : (
                                        <button type="button" className="btn-primary" onClick={() => router.post(`/learn/courses/${row.id}/enroll`)}>{t.enroll || 'Enroll'}</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
