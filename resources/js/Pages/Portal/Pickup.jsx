import { router, useForm } from '@inertiajs/react';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';
import AppShell from '../../Layouts/AppShell';

function PinForm({ hasPin, t }) {
    const form = useForm({ pin: '' });

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => { e.preventDefault(); form.post('/portal/pickup/pin', { onSuccess: () => form.reset() }); }}
        >
            <p className="text-sm font-semibold">{hasPin ? (t.pickup_pin_change || 'Change your pick-up PIN') : (t.pickup_pin_set || 'Set your pick-up PIN')}</p>
            <p className="text-xs text-gray-600">
                {t.pickup_pin_intro || 'You will be asked for this every time you collect your child. It is what stops somebody else asking for them. Four to eight digits, and not something obvious.'}
            </p>
            <label className="block text-sm">
                <input
                    className="form-input w-40"
                    type="password"
                    inputMode="numeric"
                    autoComplete="off"
                    aria-label={t.pickup_pin || 'Your PIN'}
                    value={form.data.pin}
                    onChange={(e) => form.setData('pin', e.target.value)}
                />
                {form.errors.pin && <span className="mt-1 block text-xs text-red-600">{form.errors.pin}</span>}
            </label>
            <button type="submit" className="btn-secondary justify-self-start text-sm" disabled={form.processing}>
                {t.pickup_pin_save || 'Save PIN'}
            </button>
        </form>
    );
}

function RequestForm({ children, t }) {
    const form = useForm({ student_id: '', pin: '', note: '' });

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => { e.preventDefault(); form.post('/portal/pickup/request', { onSuccess: () => form.reset() }); }}
        >
            <p className="text-sm font-semibold">{t.pickup_on_my_way || 'I am on my way'}</p>
            <p className="text-xs text-gray-600">
                {t.pickup_on_my_way_intro || 'Send this when you are about ten minutes away. The school will bring your child to reception.'}
            </p>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">{t.pick_child || 'Child'}</span>
                <select className="form-input w-full" value={form.data.student_id}
                    onChange={(e) => form.setData('student_id', e.target.value)}>
                    <option value="">{t.choose || 'Choose…'}</option>
                    {children.map((child) => (
                        <option key={child.id} value={child.id}>{child.name}</option>
                    ))}
                </select>
                {form.errors.student_id && <span className="mt-1 block text-xs text-red-600">{form.errors.student_id}</span>}
            </label>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">{t.pickup_pin || 'Your PIN'}</span>
                <input className="form-input w-40" type="password" inputMode="numeric" autoComplete="off"
                    value={form.data.pin} onChange={(e) => form.setData('pin', e.target.value)} />
                {form.errors.pin && <span className="mt-1 block text-xs text-red-600">{form.errors.pin}</span>}
            </label>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">{t.pickup_note || 'Anything the office should know (optional)'}</span>
                <input className="form-input w-full" value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)} />
            </label>
            <FormErrors errors={form.errors} except={['student_id', 'pin']} />
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                {t.pickup_tell_school || 'Tell the school'}
            </button>
        </form>
    );
}

export default function Pickup({ is_open, has_pin, children = [], notices = [], t = {} }) {
    // "I have my child" posts with `router`, so a refusal had no form to show
    // it: it is said under the notice now, in the page's language (BACKLOG
    // C21, slice PT3).
    const refusals = useRowRefusals();
    const confirm = (notice) => refusals.actOn(`notice:${notice.id}`, () => router.post(`/portal/pickup/${notice.id}/confirm`, {}, { preserveScroll: true, preserveState: 'errors' }));

    return (
        <AppShell title={t.pickup_title || 'Collecting your child'}>
            {!is_open && (
                <p className="mb-4 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.pickup_closed || 'Pick-up is not open at the moment. The school opens it each day.'}
                </p>
            )}

            <PinForm hasPin={has_pin} t={t} />

            {is_open && has_pin && children.length > 0 && <RequestForm children={children} t={t} />}
            {is_open && has_pin && children.length === 0 && (
                <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.pickup_not_listed || 'You are not listed as someone who may collect any child. The office can change that — an empty list here is a record to correct, not a mistake on your part.'}
                </p>
            )}
            {is_open && !has_pin && (
                <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.pickup_set_pin_first || 'Set a PIN above before you can ask for your child.'}
                </p>
            )}

            <FormErrors errors={refusals.unplaced} className="mb-3" />
            <h2 className="mb-2 text-sm font-semibold">{t.pickup_today || 'Today'}</h2>
            <ul className="grid gap-2">
                {notices.map((notice) => (
                    <li key={notice.id} className="rounded-lg border bg-white p-3 text-sm">
                        <p className="font-medium">{notice.student}</p>
                        {notice.status === 'requested' && <p className="text-xs text-gray-600">{t.pickup_status_requested || 'The school has been told.'}</p>}
                        {notice.status === 'sent' && (
                            <>
                                <p className="text-xs text-emerald-700">{t.pickup_status_sent || 'Your child is at reception.'}</p>
                                <button type="button" className="btn-primary mt-2 text-xs" onClick={() => confirm(notice)}>
                                    {t.pickup_have_child || 'I have my child'}
                                </button>
                            </>
                        )}
                        {notice.status === 'collected' && (
                            <p className="text-xs text-gray-600">{(t.pickup_status_collected || 'Collected at :time.').replace(':time', notice.collected_at?.slice(11, 16) || '')}</p>
                        )}
                        {notice.status === 'cancelled' && <p className="text-xs text-gray-500">{t.pickup_status_cancelled || 'Cancelled.'}</p>}
                        <FormErrors errors={refusals.errorsFor(`notice:${notice.id}`)} className="mt-1" />
                    </li>
                ))}
            </ul>
            {notices.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.pickup_nothing_today || 'Nothing today.'}</p>
            )}
        </AppShell>
    );
}
