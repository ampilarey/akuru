import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Appraisals({ staff, appraisals, observations, cpd }) {
    return (
        <AppShell title="My performance">
            {!staff && <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">No staff profile is linked to this account.</p>}
            {staff && (
                <>
                    <h2 className="mb-2 font-medium">Appraisals</h2>
                    <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-left">
                                <tr>
                                    <th className="px-3 py-2">Cycle</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Acknowledge</th>
                                </tr>
                            </thead>
                            <tbody>
                                {appraisals.map((row) => (
                                    <AppraisalRow key={row.id} row={row} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <h2 className="mb-2 font-medium">Shared observations</h2>
                    <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-left">
                                <tr>
                                    <th className="px-3 py-2">Date</th>
                                    <th className="px-3 py-2">Class</th>
                                    <th className="px-3 py-2">Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                {observations.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-3 py-2">{row.date}</td>
                                        <td className="px-3 py-2">{row.class_name || '—'}</td>
                                        <td className="px-3 py-2">{row.summary}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <h2 className="mb-2 font-medium">CPD</h2>
                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-left">
                                <tr>
                                    <th className="px-3 py-2">Title</th>
                                    <th className="px-3 py-2">Hours</th>
                                    <th className="px-3 py-2">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {cpd.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-3 py-2">{row.title}</td>
                                        <td className="px-3 py-2">{row.hours}</td>
                                        <td className="px-3 py-2">{row.date}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </AppShell>
    );
}

function AppraisalRow({ row }) {
    const form = useForm({ staff_comment: '' });

    return (
        <tr className="border-t">
            <td className="px-3 py-2">{row.cycle_name}</td>
            <td className="px-3 py-2">{row.status}</td>
            <td className="px-3 py-2">
                {row.status !== 'acknowledged' && (
                    <form
                        className="flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/portal/appraisals/${row.id}/acknowledge`);
                        }}
                    >
                        <input className="form-input" placeholder="Comment" value={form.data.staff_comment} onChange={(e) => form.setData('staff_comment', e.target.value)} />
                        <button type="submit" className="btn-secondary" disabled={form.processing}>Acknowledge</button>
                    </form>
                )}
            </td>
        </tr>
    );
}
