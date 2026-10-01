import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * COMMERCE_PARITY_PLAN P7a: the problems customers reported on their
 * orders, open first. The office answers each and moves it to in progress
 * or resolved; the answer reaches the customer in the app, by email and SMS.
 */
function Reply({ complaint, t }) {
    const form = useForm({ status: complaint.status === 'open' ? 'in_progress' : complaint.status, reply: complaint.reply || '' });

    return (
        <form className="mt-2 grid gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/bookshop/complaints/${complaint.id}/reply`, { preserveScroll: true }); }}>
            <label className="text-sm">{t.complaint_reply_label}
                <textarea className="form-input w-full" rows={2} maxLength={2000} value={form.data.reply} onChange={(e) => form.setData('reply', e.target.value)} required data-testid={`complaint-reply-${complaint.id}`} />
            </label>
            <div className="flex flex-wrap items-center gap-2">
                <label className="text-sm">{t.complaint_mark}{' '}
                    <select className="form-input text-sm" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} data-testid={`complaint-status-${complaint.id}`}>
                        <option value="in_progress">{t.complaint_status_in_progress}</option>
                        <option value="resolved">{t.complaint_status_resolved}</option>
                    </select>
                </label>
                <button type="submit" className="btn-primary text-sm" disabled={form.processing} data-testid={`complaint-send-${complaint.id}`}>{t.complaint_send_reply}</button>
            </div>
            {(form.errors.reply || form.errors.status) && <p className="text-xs text-red-700">{form.errors.reply || form.errors.status}</p>}
        </form>
    );
}

export default function Complaints({ t = {}, complaints = [] }) {
    const { flash = {}, errors } = usePage().props;
    const tone = { open: 'bg-red-50 text-red-800', in_progress: 'bg-amber-50 text-amber-800', resolved: 'bg-green-50 text-green-800' };

    return (
        <AppShell title={t.complaints_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600">{t.complaints_intro}</p>
                <a href="/admin/bookshop/complaints/export" className="btn-secondary" data-testid="export-complaints">{t.export_csv}</a>
            </div>
            {complaints.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600" data-testid="complaints-empty">{t.complaints_empty}</p> : (
                <ul className="divide-y rounded border bg-white" data-testid="complaints">
                    {complaints.map((c) => (
                        <li key={c.id} className="p-3 text-sm" data-testid={`complaint-${c.id}`} data-status={c.status}>
                            <p className="font-semibold">
                                {c.number} · {c.shop} · {t[`complaint_kind_${c.kind}`] || c.kind}{' '}
                                <span className={`rounded px-1 text-xs ${tone[c.status] || ''}`}>{t[`complaint_status_${c.status}`] || c.status}</span>
                            </p>
                            <p className="text-gray-600">{c.created_at}{c.recipient ? ` · ${t.complaint_customer_said}: ${c.recipient}` : ''}{c.phone ? ` · ${c.phone}` : ''}</p>
                            <p className="mt-1 whitespace-pre-line break-words">{c.body}</p>
                            {c.photo_url && <a href={c.photo_url} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid={`complaint-photo-${c.id}`}>{t.complaint_open_photo}</a>}
                            {c.reply && <p className="mt-2 rounded bg-gray-50 p-2"><span className="font-medium">{t.complaint_answer}:</span> {c.reply} <span className="text-xs text-gray-500">{c.replied_at}</span></p>}
                            {c.status !== 'resolved' && <Reply complaint={c} t={t} />}
                        </li>
                    ))}
                </ul>
            )}
        </AppShell>
    );
}
