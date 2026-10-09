import { Link, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Children({ children, pending = [], t = {} }) {
    // A relationship is named by the `learn` book the shell shares.
    const learn = usePage().props.i18n?.learn || {};
    // The column names, also the captions a phone shows beside each value
    // (`data-label`), in the page's language (BACKLOG C21, slice PT1a).
    const col = {
        name: t.col_name || 'Name',
        number: t.col_number || 'Number',
        relationship: t.col_relationship || 'Relationship',
        status: t.col_status || 'Status',
        library: t.col_library || 'Library',
    };
    const relationship = (code) => learn[`relationship_${code}`] || code;

    return (
        <AppShell title={t.children_title || 'My children'}>
            {pending.length > 0 && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4" role="status" data-testid="pending-links">
                    <p className="font-medium text-amber-900">{t.children_pending_title || 'Awaiting the office'}</p>
                    <p className="mt-1 text-sm text-amber-800">
                        {t.children_pending_body || 'The office checks each parent link before a child’s records are shown. These will appear below once confirmed.'}
                    </p>
                    <ul className="mt-2 list-disc ps-5 text-sm text-amber-900">
                        {pending.map((child) => (
                            <li key={child.id}>
                                {[child.first_name, child.middle_name, child.last_name].filter(Boolean).join(' ')}
                                {child.relationship ? ` · ${relationship(child.relationship)}` : ''}
                                {child.verification_status === 'rejected' ? ` · ${t.children_not_accepted || 'not accepted'}` : ''}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.name}</th>
                            <th className="px-3 py-2">{col.number}</th>
                            <th className="px-3 py-2">{col.relationship}</th>
                            <th className="px-3 py-2">{col.status}</th>
                            <th className="px-3 py-2">{col.library}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {children.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={5}>
                                    {pending.length > 0 ? (t.children_none_confirmed || 'No confirmed children yet.') : (t.children_none || 'No linked children.')}
                                </td>
                            </tr>
                        )}
                        {children.map((child) => (
                            <tr key={child.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.name}>{[child.first_name, child.middle_name, child.last_name].filter(Boolean).join(' ')}</td>
                                <td className="px-3 py-2" data-label={col.number}>{child.student_id}</td>
                                <td className="px-3 py-2" data-label={col.relationship}>{relationship(child.relationship)}</td>
                                <td className="px-3 py-2" data-label={col.status}>{t[`student_status_${child.status}`] || child.status}</td>
                                {/* B8: what they are reading and have bought. */}
                                <td className="table-actions px-3 py-2"><Link href={`/portal/children/${child.id}/library`} className="chip-link" data-testid="child-library">{col.library}</Link></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
