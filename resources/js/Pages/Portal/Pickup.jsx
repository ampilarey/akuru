import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function PinForm({ hasPin }) {
    const form = useForm({ pin: '' });

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => { e.preventDefault(); form.post('/portal/pickup/pin', { onSuccess: () => form.reset() }); }}
        >
            <p className="text-sm font-semibold">{hasPin ? 'Change your pick-up PIN' : 'Set your pick-up PIN'}</p>
            <p className="text-xs text-gray-600">
                You will be asked for this every time you collect your child. It is what stops
                somebody else asking for them. Four to eight digits, and not something obvious.
            </p>
            <label className="block text-sm">
                <input
                    className="form-input w-40"
                    type="password"
                    inputMode="numeric"
                    autoComplete="off"
                    value={form.data.pin}
                    onChange={(e) => form.setData('pin', e.target.value)}
                />
                {form.errors.pin && <span className="mt-1 block text-xs text-red-600">{form.errors.pin}</span>}
            </label>
            <button type="submit" className="btn-secondary justify-self-start text-sm" disabled={form.processing}>
                Save PIN
            </button>
        </form>
    );
}

function RequestForm({ children }) {
    const form = useForm({ student_id: '', pin: '', note: '' });

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => { e.preventDefault(); form.post('/portal/pickup/request', { onSuccess: () => form.reset() }); }}
        >
            <p className="text-sm font-semibold">I am on my way</p>
            <p className="text-xs text-gray-600">
                Send this when you are about ten minutes away. The school will bring your child
                to reception.
            </p>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Child</span>
                <select className="form-input w-full" value={form.data.student_id}
                    onChange={(e) => form.setData('student_id', e.target.value)}>
                    <option value="">Choose…</option>
                    {children.map((child) => (
                        <option key={child.id} value={child.id}>{child.name}</option>
                    ))}
                </select>
            </label>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Your PIN</span>
                <input className="form-input w-40" type="password" inputMode="numeric" autoComplete="off"
                    value={form.data.pin} onChange={(e) => form.setData('pin', e.target.value)} />
                {form.errors.pin && <span className="mt-1 block text-xs text-red-600">{form.errors.pin}</span>}
            </label>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Anything the office should know (optional)</span>
                <input className="form-input w-full" value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)} />
            </label>
            {form.errors.pickup && <p className="text-sm text-red-600">{form.errors.pickup}</p>}
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                Tell the school
            </button>
        </form>
    );
}

export default function Pickup({ is_open, has_pin, children = [], notices = [] }) {
    return (
        <AppShell title="Collecting your child">
            {!is_open && (
                <p className="mb-4 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Pick-up is not open at the moment. The school opens it each day.
                </p>
            )}

            <PinForm hasPin={has_pin} />

            {is_open && has_pin && children.length > 0 && <RequestForm children={children} />}
            {is_open && has_pin && children.length === 0 && (
                <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    You are not listed as someone who may collect any child. The office can
                    change that — an empty list here is a record to correct, not a mistake on
                    your part.
                </p>
            )}
            {is_open && !has_pin && (
                <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Set a PIN above before you can ask for your child.
                </p>
            )}

            <h2 className="mb-2 text-sm font-semibold">Today</h2>
            <ul className="grid gap-2">
                {notices.map((notice) => (
                    <li key={notice.id} className="rounded-lg border bg-white p-3 text-sm">
                        <p className="font-medium">{notice.student}</p>
                        {notice.status === 'requested' && <p className="text-xs text-gray-600">The school has been told.</p>}
                        {notice.status === 'sent' && (
                            <>
                                <p className="text-xs text-emerald-700">Your child is at reception.</p>
                                <button
                                    className="btn-primary mt-2 text-xs"
                                    onClick={() => router.post(`/portal/pickup/${notice.id}/confirm`, {}, { preserveScroll: true })}
                                >
                                    I have my child
                                </button>
                            </>
                        )}
                        {notice.status === 'collected' && (
                            <p className="text-xs text-gray-600">Collected at {notice.collected_at?.slice(11, 16)}.</p>
                        )}
                        {notice.status === 'cancelled' && <p className="text-xs text-gray-500">Cancelled.</p>}
                    </li>
                ))}
            </ul>
            {notices.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">Nothing today.</p>
            )}
        </AppShell>
    );
}
