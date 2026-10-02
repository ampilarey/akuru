import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import { IdentityChecks, IdentityStatus } from '../../Components/IdentityCard';

/**
 * One enrolment, as the office decides on it (docs/ADMIN_PANEL.md; C9 slice
 * 5, STATUS §5jg): the details, the student and guardians, the payment, the
 * decision stamp, and the six writes — activate, reject, suspend, reinstate,
 * the access window, a manual payment. Every write is an Inertia request
 * with the same confirm the Blade page asked; each form keeps a real
 * `action`, so the walks find them where they always were. Every string is
 * a key in the admin tranche.
 */
const STATUS_TONES = {
    active: 'bg-green-100 text-green-800',
    approved: 'bg-green-100 text-green-800',
    pending: 'bg-amber-100 text-amber-800',
    suspended: 'bg-amber-100 text-amber-800',
    rejected: 'bg-red-100 text-red-800',
};
const PAYMENT_TONES = { confirmed: 'bg-green-100 text-green-800', required: 'bg-amber-100 text-amber-800', pending: 'bg-amber-50 text-amber-700' };
const PAY_TONES = { confirmed: 'bg-green-100 text-green-800', failed: 'bg-red-100 text-red-800', cancelled: 'bg-red-100 text-red-800', expired: 'bg-red-100 text-red-800' };
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

function Field({ label, children, wide = false, testId }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-xs font-medium text-gray-500">{label}</dt>
            <dd className="text-sm text-gray-900" data-testid={testId}>{children}</dd>
        </div>
    );
}

function Decision({ enrollment, action, label, confirm, tone, testId }) {
    const submit = (e) => {
        e.preventDefault();
        if (!window.confirm(confirm)) return;
        router.patch(`/admin/enrollments/${enrollment.id}/${action}`, {}, { preserveScroll: true });
    };

    return (
        <form action={`/admin/enrollments/${enrollment.id}/${action}`} method="post" onSubmit={submit}>
            <button type="submit" className={`rounded px-4 py-2 text-sm font-semibold ${tone}`} data-testid={testId}>{label}</button>
        </form>
    );
}

export default function Enrollment({ enrollment, payment_methods: paymentMethods = [], can_record_payment: canRecordPayment = false, t = {}, identity = null, id_l = {} }) {
    const { errors = {} } = usePage().props;
    const [starts, setStarts] = useState(enrollment.access_starts_at || '');
    const [ends, setEnds] = useState(enrollment.access_ends_at || '');
    const [amount, setAmount] = useState(enrollment.suggested_amount || '');
    const [method, setMethod] = useState(paymentMethods[0]?.value || '');
    const [note, setNote] = useState('');
    const firstError = Object.values(errors)[0];
    const statusLabel = (s) => t[`enrolments_status_${s}`] || humanize(s);
    const paymentLabel = (s) => (s ? t[`enrolments_pay_${s}`] || humanize(s) : t.enrolments_pay_none || 'N/A');
    const saveWindow = (e) => {
        e.preventDefault();
        router.patch(`/admin/enrollments/${enrollment.id}/access-window`, { access_starts_at: starts, access_ends_at: ends }, { preserveScroll: true });
    };
    const recordPayment = (e) => {
        e.preventDefault();
        if (!window.confirm(t.enrolment_record_confirm || 'Record this payment as received? The enrollment activates through the payment pipeline.')) return;
        router.post(`/admin/enrollments/${enrollment.id}/record-payment`, { amount, payment_method: method, note }, { preserveScroll: true });
    };
    const title = (t.enrolment_title || 'Enrollment #:id').replace(':id', enrollment.id);

    return (
        <AppShell title={title}>
            <p className="mb-4 text-sm"><Link href="/admin/enrollments" className="text-gray-500 underline" data-testid="enrolment-back">{t.enrolment_back || '← Enrollments'}</Link> <span className="text-gray-400">/</span> <span className="text-gray-700">{title}</span></p>
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-red-700" data-testid="enrolment-error">✗ {firstError}</p>}

            <section className="mb-6 rounded-lg border bg-white p-5" data-testid="enrolment-details">
                <h2 className="mb-3 text-base font-semibold text-gray-800">{t.enrolment_details || 'Enrollment Details'}</h2>
                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <Field label={t.enrolments_col_student || 'Student'}><span className="font-semibold">{enrollment.student?.name || '—'}</span></Field>
                    <Field label={t.enrolments_col_course || 'Course'}>{enrollment.course || '—'}</Field>
                    <Field label={t.enrolment_status || 'Enrollment Status'}><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_TONES[enrollment.status] || 'bg-gray-100 text-gray-700'}`} data-testid="enrolment-status">{statusLabel(enrollment.status)}</span></Field>
                    <Field label={t.enrolment_payment_status || 'Payment Status'}><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${PAYMENT_TONES[enrollment.payment_status] || 'bg-gray-100 text-gray-600'}`} data-testid="enrolment-payment-status">{paymentLabel(enrollment.payment_status)}</span></Field>
                    <Field label={t.enrolment_enrolled_at || 'Enrolled at'}>{enrollment.enrolled_at || '—'}</Field>
                    <Field label={t.enrolment_registered_by || 'Registered by'}>{enrollment.registered_by || '—'}</Field>
                    <Field label={t.enrolment_last_decision || 'Last decision'} wide testId="last-decision">{enrollment.last_decision}</Field>
                </dl>
            </section>

            {enrollment.student && (
                <section className="mb-6 rounded-lg border bg-white p-5" data-testid="enrolment-student">
                    <h2 className="mb-3 text-base font-semibold text-gray-800">{t.enrolment_student_profile || 'Student Profile'}</h2>
                    <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        <Field label={t.enrolment_full_name || 'Full name'}>{enrollment.student.name}</Field>
                        <Field label={t.enrolment_dob || 'Date of birth'}>{enrollment.student.date_of_birth || '—'}</Field>
                        <Field label={t.enrolment_gender || 'Gender'}>{enrollment.student.gender ? (t[`enrolment_gender_${enrollment.student.gender}`] || humanize(enrollment.student.gender)) : '—'}</Field>
                        <Field label={t.enrolment_national_id || 'National ID'}>{enrollment.student.national_id || '—'}</Field>
                    </dl>
                    {enrollment.student.guardians.length > 0 && (
                        <div className="mt-4">
                            <p className="mb-2 text-xs font-medium uppercase text-gray-500">{t.enrolment_guardians || 'Guardians'}</p>
                            {enrollment.student.guardians.map((g, i) => <p key={i} className="text-sm text-gray-800">{g.name} <span className="text-gray-400">({g.relationship || (t.enrolment_guardian || 'guardian')})</span></p>)}
                        </div>
                    )}
                </section>
            )}

            {/* COMMERCE_PARITY_PLAN P3: the learner's ID card, verified here; the certificate waits for it. */}
            <div className="mb-6" data-testid="enrolment-identity">
                {identity ? <IdentityChecks rows={[identity]} l={id_l} title={id_l.id_col} /> : (
                    <section className="rounded-lg border bg-white p-5"><h2 className="mb-1 text-base font-semibold text-gray-800">{id_l.id_col}</h2><p className="text-sm text-gray-600"><IdentityStatus status="none" l={id_l} /></p></section>
                )}
            </div>

            {enrollment.payment && (
                <section className="mb-6 rounded-lg border bg-white p-5" data-testid="enrolment-payment">
                    <h2 className="mb-3 text-base font-semibold text-gray-800">{t.enrolment_payment || 'Payment'}</h2>
                    <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        <Field label={t.payments_col_reference || 'Reference'}><span className="break-all font-mono text-xs">{enrollment.payment.reference}</span></Field>
                        <Field label={t.payments_col_amount || 'Amount'}><span className="font-semibold">{enrollment.payment.amount} {enrollment.payment.currency}</span></Field>
                        <Field label={t.payments_col_status || 'Status'}><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${PAY_TONES[enrollment.payment.status] || 'bg-amber-100 text-amber-800'}`}>{t[`payments_status_${enrollment.payment.status}`] || humanize(enrollment.payment.status)}</span></Field>
                        <Field label={t.enrolment_paid_at || 'Paid at'}>{enrollment.payment.paid_at || '—'}</Field>
                    </dl>
                </section>
            )}

            <section className="rounded-lg border bg-white p-5" data-testid="enrolment-actions">
                <h2 className="mb-4 text-base font-semibold text-gray-800">{t.enrolment_actions || 'Actions'}</h2>
                <div className="flex flex-wrap gap-3">
                    {enrollment.can_activate && <Decision enrollment={enrollment} action="activate" label={t.enrolment_activate || 'Activate enrollment'} confirm={t.enrolment_activate_confirm || 'Activate this enrollment?'} tone="btn-primary" testId="enrolment-activate" />}
                    {enrollment.can_reject && <Decision enrollment={enrollment} action="reject" label={t.enrolment_reject || 'Reject enrollment'} confirm={t.enrolment_reject_confirm || 'Reject this enrollment?'} tone="bg-red-600 text-white hover:bg-red-700" testId="enrolment-reject" />}
                    {/* SPEC §23's sixth status: suspension is the reversible one — the seat is released, the record is kept. */}
                    {enrollment.can_reinstate && <Decision enrollment={enrollment} action="reinstate" label={t.enrolment_reinstate || 'Reinstate enrollment'} confirm={t.enrolment_reinstate_confirm || 'Reinstate this enrollment? It needs a free seat on the offering.'} tone="btn-secondary" testId="enrolment-reinstate" />}
                    {enrollment.can_suspend && <Decision enrollment={enrollment} action="suspend" label={t.enrolment_suspend || 'Suspend enrollment'} confirm={t.enrolment_suspend_confirm || 'Suspend this enrollment? The seat is released and their record is kept.'} tone="bg-amber-600 text-white hover:bg-amber-700" testId="enrolment-suspend" />}
                </div>

                {/* SPEC §11.7 "Access starts at" / "Access ends at": blank means unbounded at that end, so clearing a field is a real operation. */}
                <div className="mt-6 border-t pt-4">
                    <h3 className="mb-1 text-sm font-semibold text-gray-800">{t.enrolment_access_window || 'Access window'}</h3>
                    <p className="mb-2 text-xs text-gray-600">{t.enrolment_access_hint || 'Leave blank for no limit at that end.'}</p>
                    <form action={`/admin/enrollments/${enrollment.id}/access-window`} method="post" onSubmit={saveWindow} className="flex flex-wrap items-end gap-2" data-testid="enrolment-access-window">
                        <label className="text-xs text-gray-700">
                            <span className="block">{t.enrolment_access_starts || 'Starts'}</span>
                            <input type="datetime-local" name="access_starts_at" className="form-input text-sm" value={starts} onChange={(e) => setStarts(e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-700">
                            <span className="block">{t.enrolment_access_ends || 'Ends'}</span>
                            <input type="datetime-local" name="access_ends_at" className="form-input text-sm" value={ends} onChange={(e) => setEnds(e.target.value)} />
                        </label>
                        <button type="submit" className="btn-secondary text-sm">{t.enrolment_access_save || 'Save access window'}</button>
                    </form>
                </div>

                {/* P4.4: record money received outside the gateway (cash / transfer), only while it is still owed. */}
                {canRecordPayment && enrollment.awaits_payment && (
                    <div className="mt-6 border-t pt-4">
                        <h3 className="mb-2 text-sm font-semibold text-gray-800">{t.enrolment_record_title || 'Record manual payment'}</h3>
                        <form action={`/admin/enrollments/${enrollment.id}/record-payment`} method="post" onSubmit={recordPayment} className="flex flex-wrap items-center gap-2" data-testid="enrolment-record-payment">
                            <input type="number" name="amount" step="0.01" min="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} className="form-input w-32 text-sm" placeholder={t.enrolment_record_amount || 'Amount (MVR)'} aria-label={t.enrolment_record_amount || 'Amount (MVR)'} />
                            {/* SPEC §38 "Payment method", separate from the gateway. */}
                            <select name="payment_method" value={method} onChange={(e) => setMethod(e.target.value)} className="form-input text-sm" aria-label={t.enrolment_record_method || 'Payment method'}>
                                {paymentMethods.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                            </select>
                            <input type="text" name="note" maxLength="500" value={note} onChange={(e) => setNote(e.target.value)} placeholder={t.enrolment_record_note || 'Note (e.g. receipt number)'} aria-label={t.enrolment_record_note || 'Note (e.g. receipt number)'} className="form-input w-64 text-sm" />
                            <button type="submit" className="btn-primary text-sm">{t.enrolment_record || 'Record payment'}</button>
                        </form>
                    </div>
                )}
            </section>
        </AppShell>
    );
}
