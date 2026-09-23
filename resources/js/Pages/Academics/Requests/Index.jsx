import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

const STAFF_TYPES = ['teacher_leave', 'staff_leave'];

export default function Index({ requests, types, canReview, teacherId, leaveTypes = [], children = [] }) {
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
    const chosenLeaveType = leaveTypes.find((type) => String(type.id) === String(form.data.leave_type_id));
    const staffType = STAFF_TYPES.includes(form.data.type);

    return (
        <AppShell title="Requests">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/requests/export">Export CSV</a>
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
                    <span className="mb-1 block text-gray-600">Type</span>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{type.replace(/_/g, ' ')}</option>)}
                    </select>
                </label>
                {!staffType && children.length > 0 && (
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Regarding</span>
                        <select className="form-input w-full" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                            {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                        </select>
                    </label>
                )}
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">From</span>
                    <input className="form-input w-full" type="date" value={form.data.from_date} onChange={(e) => form.setData('from_date', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">To</span>
                    <input className="form-input w-full" type="date" value={form.data.to_date} onChange={(e) => form.setData('to_date', e.target.value)} />
                </label>
                {form.data.type === 'staff_leave' && (
                    <>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">Leave type</span>
                            <select className="form-input w-full" value={form.data.leave_type_id} onChange={(e) => form.setData('leave_type_id', e.target.value)}>
                                {leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                            </select>
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.half_day} onChange={(e) => form.setData('half_day', e.target.checked)} />
                            Half day
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">
                                Supporting document{chosenLeaveType?.requires_document ? ' (required for this leave type)' : ' (optional)'}
                            </span>
                            <input
                                className="form-input w-full"
                                type="file"
                                name="document"
                                accept="application/pdf,image/jpeg,image/png"
                                onChange={(e) => form.setData('document', e.target.files?.[0] ?? null)}
                            />
                            <span className="mt-1 block text-xs text-gray-500">A PDF or a photo of the certificate, up to 5 MB.</span>
                        </label>
                    </>
                )}
                <label className="block text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">Reason</span>
                    <input className="form-input w-full" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                </label>
                <button type="submit" className="btn-primary justify-self-start">Submit request</button>
                <FormErrors errors={form.errors} className="md:col-span-3" />
            </form>

            <div className="grid gap-3">
                {requests.length === 0 && <p className="text-sm text-gray-600">No requests yet.</p>}
                {requests.map((item) => (
                    <RequestCard key={item.id} item={item} canReview={canReview} />
                ))}
            </div>
        </AppShell>
    );
}

function RequestCard({ item, canReview }) {
    const review = useForm({ status: 'approved', review_notes: '' });

    return (
        <section className="rounded-lg border bg-white p-4 text-sm">
            <div className="mb-1 flex justify-between gap-2">
                <p className="font-semibold">
                    {item.type?.replace(/_/g, ' ')}
                    {item.regarding_name && <span className="font-normal text-gray-600"> · about {item.regarding_name}</span>}
                </p>
                <span className="uppercase text-xs">{item.status}</span>
            </div>
            <p className="mb-1 text-xs text-gray-500">
                {canReview && item.requester_name ? `From ${item.requester_name} · ` : ''}
                Submitted {item.submitted_at}
            </p>
            <p>{item.reason}</p>
            {item.payload?.document_id && (
                <p className="mt-1">
                    <a className="text-[#7C2D37] underline" href={`/academics/requests/${item.id}/document`} target="_blank" rel="noreferrer">
                        Supporting document
                    </a>
                </p>
            )}
            {item.status !== 'pending' && (
                <p className="mt-2 rounded bg-[#F3EBE0] px-3 py-2">
                    <span className="capitalize">{item.status}</span>
                    {item.reviewed_at ? ` on ${item.reviewed_at}` : ''}
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
                    <select className="form-input" value={review.data.status} onChange={(e) => review.setData('status', e.target.value)}>
                        <option value="approved">Approve</option>
                        <option value="rejected">Reject</option>
                    </select>
                    <input className="form-input" placeholder="Notes" value={review.data.review_notes} onChange={(e) => review.setData('review_notes', e.target.value)} />
                    <button type="submit" className="btn-primary">Review</button>
                    <FormErrors errors={review.errors} className="w-full" />
                </form>
            )}
        </section>
    );
}
