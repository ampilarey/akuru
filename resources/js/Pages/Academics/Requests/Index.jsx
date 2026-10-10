import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

const STAFF_TYPES = ['teacher_leave', 'staff_leave'];

/**
 * A family's and a member of staff's requests, and the office's review of
 * them. Every word is the `academics` book's, and a request's type and state
 * are named rather than printed as codes (slice OA4, STATUS §5qi).
 */
export default function Index({ requests, types, canReview, teacherId, leaveTypes = [], children = [], t = {} }) {
    const form = useForm({
        type: types[0] || 'other',
        reason: '',
        teacher_id: teacherId || '',
        student_id: children[0]?.id || '',
        leave_type_id: leaveTypes[0]?.id || '',
        from_date: '',
        to_date: '',
        half_day: false,
        document: null,
    });
    const chosenLeaveType = leaveTypes.find((type) => `${type.id}` === `${form.data.leave_type_id}`);
    const staffType = STAFF_TYPES.includes(form.data.type);
    const documentLabel = chosenLeaveType?.requires_document
        ? (t.requests_document_required || 'Supporting document (required for this leave type)')
        : (t.requests_document_optional || 'Supporting document (optional)');

    return (
        <AppShell title={t.requests_title || 'Requests'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/requests/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    // A family's request is about a child; a staff member's is about themselves.
                    form.transform((data) => ({ ...data, student_id: staffType ? '' : data.student_id }));
                    form.post('/academics/requests', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.type || 'Type'}</span>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{t[`request_type_${type}`] || type}</option>)}
                    </select>
                </label>
                {!staffType && children.length > 0 && (
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.requests_regarding || 'Regarding'}</span>
                        <select className="form-input w-full" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                            {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                        </select>
                    </label>
                )}
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.requests_from_date || 'From'}</span>
                    <input className="form-input w-full" type="date" value={form.data.from_date} onChange={(e) => form.setData('from_date', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.requests_to_date || 'To'}</span>
                    <input className="form-input w-full" type="date" value={form.data.to_date} onChange={(e) => form.setData('to_date', e.target.value)} />
                </label>
                {form.data.type === 'staff_leave' && (
                    <>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">{t.requests_leave_type || 'Leave type'}</span>
                            <select className="form-input w-full" value={form.data.leave_type_id} onChange={(e) => form.setData('leave_type_id', e.target.value)}>
                                {leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                            </select>
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.half_day} onChange={(e) => form.setData('half_day', e.target.checked)} />
                            {t.requests_half_day || 'Half day'}
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">{documentLabel}</span>
                            <input
                                className="form-input w-full"
                                type="file"
                                name="document"
                                aria-label={documentLabel}
                                accept="application/pdf,image/jpeg,image/png"
                                onChange={(e) => form.setData('document', e.target.files?.[0] ?? null)}
                            />
                            <span className="mt-1 block text-xs text-gray-500">{t.requests_document_hint || 'A PDF or a photo of the certificate, up to 5 MB.'}</span>
                        </label>
                    </>
                )}
                <label className="block text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">{t.requests_reason || 'Reason'}</span>
                    <input className="form-input w-full" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                </label>
                <button type="submit" className="btn-primary justify-self-start">{t.requests_submit || 'Submit request'}</button>
                <FormErrors errors={form.errors} className="md:col-span-3" />
            </form>

            <div className="grid gap-3">
                {requests.length === 0 && <p className="text-sm text-gray-600">{t.requests_none || 'No requests yet.'}</p>}
                {requests.map((item) => (
                    <RequestCard key={item.id} item={item} canReview={canReview} t={t} />
                ))}
            </div>
        </AppShell>
    );
}

function RequestCard({ item, canReview, t }) {
    const review = useForm({ status: 'approved', review_notes: '' });
    const status = t[`request_status_${item.status}`] || item.status;

    return (
        <section className="rounded-lg border bg-white p-4 text-sm">
            <div className="mb-1 flex justify-between gap-2">
                <p className="font-semibold">
                    {t[`request_type_${item.type}`] || item.type}
                    {item.regarding_name && <span className="font-normal text-gray-600"> · {(t.requests_about || 'about :name').replace(':name', item.regarding_name)}</span>}
                </p>
                <span className="uppercase text-xs">{status}</span>
            </div>
            <p className="mb-1 text-xs text-gray-500">
                {canReview && item.requester_name ? `${(t.requests_from_person || 'From :name').replace(':name', item.requester_name)} · ` : ''}
                {(t.requests_submitted || 'Submitted :date').replace(':date', item.submitted_at)}
            </p>
            <p>{item.reason}</p>
            {item.payload?.document_id && (
                <p className="mt-1">
                    <a className="text-[#7C2D37] underline" href={`/academics/requests/${item.id}/document`} target="_blank" rel="noreferrer">
                        {t.requests_document || 'Supporting document'}
                    </a>
                </p>
            )}
            {item.status !== 'pending' && (
                <p className="mt-2 rounded bg-[#F3EBE0] px-3 py-2">
                    {item.reviewed_at
                        ? (t.requests_decided_on || ':status on :date').replace(':status', status).replace(':date', item.reviewed_at)
                        : status}
                    {item.review_notes ? `: ${item.review_notes}` : ''}
                </p>
            )}
            {canReview && item.status === 'pending' && (
                <form
                    className="mt-3 flex flex-wrap gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        review.post(`/academics/requests/${item.id}/review`, { preserveScroll: true });
                    }}
                >
                    <select className="form-input" aria-label={t.requests_decision || 'Decision'} value={review.data.status} onChange={(e) => review.setData('status', e.target.value)}>
                        <option value="approved">{t.requests_approve || 'Approve'}</option>
                        <option value="rejected">{t.requests_reject || 'Reject'}</option>
                    </select>
                    <input className="form-input" aria-label={t.notes || 'Notes'} placeholder={t.notes || 'Notes'} value={review.data.review_notes} onChange={(e) => review.setData('review_notes', e.target.value)} />
                    <button type="submit" className="btn-primary">{t.requests_review || 'Review'}</button>
                    <FormErrors errors={review.errors} className="w-full" />
                </form>
            )}
        </section>
    );
}
