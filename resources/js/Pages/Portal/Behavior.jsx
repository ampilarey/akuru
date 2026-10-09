import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Behavior({ children, studentId, records, t = {} }) {
    // The column names, also the captions a phone shows beside each value
    // (`data-label`), in the page's language; a record's type is a code, named
    // here; its category and description are what the teacher wrote (BACKLOG
    // C21, slice PT2).
    const col = {
        date: t.col_date || 'Date',
        type: t.col_type || 'Type',
        category: t.col_category || 'Category',
        description: t.col_description || 'Description',
    };

    return (
        <AppShell title={t.behavior_title || 'Behavior'}>
            <div className="mb-4">
                <select className="form-input" aria-label={t.pick_child || 'Child'} value={studentId || ''} onChange={(e) => router.get(`/portal/behavior?student_id=${e.target.value}`)}>
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.date}</th>
                            <th className="px-3 py-2">{col.type}</th>
                            <th className="px-3 py-2">{col.category}</th>
                            <th className="px-3 py-2">{col.description}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.behavior_none || 'No parent-visible records.'}</td></tr>
                        )}
                        {records.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.date}>{row.date}</td>
                                <td className="px-3 py-2" data-label={col.type}>{t[`behavior_type_${row.type}`] || row.type}</td>
                                <td className="px-3 py-2" data-label={col.category}>{row.category}</td>
                                <td className="px-3 py-2" data-label={col.description}>{row.description}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
