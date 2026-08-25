import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Onboarding({ kind, staff, rows }) {
    const seed = useForm({
        staff_profile_id: staff[0]?.id || '',
        kind,
    });

    return (
        <AppShell title={kind === 'offboarding' ? 'Offboarding' : 'Onboarding'}>
            <div className="mb-4 flex flex-wrap justify-between gap-3">
                <div className="flex gap-3">
                    <a className="btn-secondary" href="/hr/onboarding?kind=onboarding">Onboarding</a>
                    <a className="btn-secondary" href="/hr/onboarding?kind=offboarding">Offboarding</a>
                </div>
                <a className="btn-secondary" href={`/hr/onboarding/export?kind=${kind}`}>Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    seed.post('/hr/onboarding/seed', { preserveScroll: true });
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" value={seed.data.staff_profile_id} onChange={(e) => seed.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={seed.processing}>Open checklist</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Item</th>
                            <th className="px-3 py-2">Done</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.item}</td>
                                <td className="px-3 py-2">
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        onClick={() => router.post(`/hr/onboarding/${row.id}/toggle`, { done: !row.done })}
                                    >
                                        {row.done ? 'Done' : 'Mark done'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
