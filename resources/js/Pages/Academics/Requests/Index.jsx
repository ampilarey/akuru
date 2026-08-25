import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ requests, types, canReview, teacherId }) {
    const form = useForm({
        type: 'teacher_leave',
        reason: '',
        teacher_id: teacherId || '',
        from_date: '',
        to_date: '',
    });

    return (
        <AppShell title="Requests">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/requests/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/requests', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Type</span>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">From</span>
                    <input className="form-input w-full" type="date" value={form.data.from_date} onChange={(e) => form.setData('from_date', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">To</span>
                    <input className="form-input w-full" type="date" value={form.data.to_date} onChange={(e) => form.setData('to_date', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">Reason</span>
                    <input className="form-input w-full" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                </label>
                <button type="submit" className="btn-primary justify-self-start">Submit request</button>
            </form>

            <div className="grid gap-3">
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
                <p className="font-semibold">{item.type}</p>
                <span className="uppercase text-xs">{item.status}</span>
            </div>
            <p>{item.reason}</p>
            {canReview && item.status === 'pending' && (
                <form
                    className="mt-3 flex flex-wrap gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        review.post(`/academics/requests/${item.id}/review`);
                    }}
                >
                    <select className="form-input" value={review.data.status} onChange={(e) => review.setData('status', e.target.value)}>
                        <option value="approved">Approve</option>
                        <option value="rejected">Reject</option>
                    </select>
                    <input className="form-input" placeholder="Notes" value={review.data.review_notes} onChange={(e) => review.setData('review_notes', e.target.value)} />
                    <button type="submit" className="btn-primary">Review</button>
                </form>
            )}
        </section>
    );
}
