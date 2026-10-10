import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Staff documents about to expire. Every word is the `hr` book's (slice HR1,
 * STATUS §5ql); a document's type is named rather than printed as its code.
 * The document's own title is the office's.
 */
export default function Index({ within, rows, t = {} }) {
    const notify = useForm({});
    const typeName = (type) => t[`document_type_${type}`] || type;

    return (
        <AppShell title={t.compliance_title || 'Expiring documents'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <a className="btn-secondary" href={`/hr/compliance/export?within=${within}`}>{t.export_csv || 'Export CSV'}</a>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        notify.post('/hr/compliance/notify');
                    }}
                >
                    <button type="submit" className="btn-primary" disabled={notify.processing}>{t.compliance_notify || 'Send due notices'}</button>
                </form>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.compliance_document || 'Document'}</th>
                            <th className="px-3 py-2">{t.type || 'Type'}</th>
                            <th className="px-3 py-2">{t.compliance_expires || 'Expires'}</th>
                            <th className="px-3 py-2">{t.compliance_days || 'Days'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.compliance_none || 'No documents expiring in this window.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.title || '—'}</td>
                                <td className="px-3 py-2">{typeName(row.document_type)}</td>
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
