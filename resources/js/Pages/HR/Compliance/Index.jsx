import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ within, rows }) {
    const notify = useForm({});

    return (
        <AppShell title="Expiring documents">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <a className="btn-secondary" href={`/hr/compliance/export?within=${within}`}>Export CSV</a>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        notify.post('/hr/compliance/notify');
                    }}
                >
                    <button type="submit" className="btn-primary" disabled={notify.processing}>Send due notices</button>
                </form>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Document</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Expires</th>
                            <th className="px-3 py-2">Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No documents expiring in this window.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.title || '—'}</td>
                                <td className="px-3 py-2">{row.document_type}</td>
                                <td className="px-3 py-2">{row.expires_at}</td>
                                <td className="px-3 py-2">{row.days_until}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
