import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import { IdentityCardFields, IdentityStatus } from '../../Components/IdentityCard';

/**
 * *My enrolments* (docs/SIGN_IN_PLAN.md ID2b): every course enrolment this
 * login made, a child's included, and the course payments beside them — what
 * the old course portal spread over four Blade pages. A receipt is linked
 * only where the money is confirmed; the old pages tested for statuses the
 * payments table cannot hold and never linked one.
 */
const th = 'px-3 py-2 text-start';
const td = 'px-3 py-2';

/** COMMERCE_PARITY_PLAN P3: both sides of the learner's ID card, sent (again) to the office. */
function ResendCard({ studentId, t }) {
    const form = useForm({ id_front: null, id_back: null });

    return (
        <details className="mt-1 text-xs" data-testid={`resend-id-${studentId}`}>
            <summary className="cursor-pointer text-[#7C2D37] underline">{t.id_submit}</summary>
            <form className="mt-2 grid gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/my-account/id-card/${studentId}`, { forceFormData: true, preserveScroll: true }); }}>
                <IdentityCardFields form={form} l={t} />
                {/* Each side shows its own message inside the fields; this names the refusal for a screen reader. */}
                {Object.keys(form.errors).length > 0 && <span className="sr-only" role="alert">{t.id_learner_needed}</span>}
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.id_submit}</button>
            </form>
        </details>
    );
}

export default function MyEnrolments({ t = {}, enrolments = [], payments = [], export_href, browse_href = '/learn/catalog' }) {
    return (
        <AppShell title={t.enrolments_title || 'My enrolments'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600">{t.enrolments_intro}</p>
                <a href={export_href} className="chip-link" data-testid="export-csv">{t.export_csv}</a>
            </div>

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="my-enrolments">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className={th}>{t.col_course}</th>
                            <th className={th}>{t.col_for}</th>
                            <th className={th}>{t.col_status}</th>
                            <th className={th}>{t.col_payment}</th>
                            <th className={th}>{t.id_col}</th>
                            <th className={th}>{t.col_date}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrolments.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={6}>
                                    {t.enrolments_empty}{' '}
                                    <Link href={browse_href} className="text-[#7C2D37] underline">{t.browse_courses}</Link>
                                </td>
                            </tr>
                        )}
                        {enrolments.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className={`${td} font-medium`} data-label={t.col_course}>{row.course}</td>
                                <td className={td} data-label={t.col_for}>{row.own ? t.you : row.student}</td>
                                <td className={td} data-label={t.col_status}>{t[`state_${row.state}`] || row.state}</td>
                                <td className={td} data-label={t.col_payment}>
                                    {t[`pay_${row.payment}`] || row.payment}
                                    {row.receipt_href && (
                                        <>
                                            {' · '}
                                            <a href={row.receipt_href} className="text-[#7C2D37] underline">{t.receipt}</a>
                                        </>
                                    )}
                                </td>
                                <td className={td} data-label={t.id_col}>
                                    {row.id_card && <IdentityStatus status={row.id_card} l={t} />}
                                    {row.id_card === 'rejected' && row.id_card_note && <div className="text-xs text-red-800">{t.id_rejected_note}: {row.id_card_note}</div>}
                                    {(row.id_card === 'rejected' || row.id_card === 'none') && row.student_id && <ResendCard studentId={row.student_id} t={t} />}
                                </td>
                                <td className={td} data-label={t.col_date}>{row.date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 font-medium">{t.payments_title}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="my-payments">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className={th}>{t.col_reference}</th>
                            <th className={th}>{t.col_course}</th>
                            <th className={th}>{t.col_amount}</th>
                            <th className={th}>{t.col_status}</th>
                            <th className={th}>{t.col_date}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {payments.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={5}>{t.payments_empty}</td>
                            </tr>
                        )}
                        {payments.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className={`${td} font-mono text-xs`} data-label={t.col_reference}>{row.reference}</td>
                                <td className={td} data-label={t.col_course}>{row.for}</td>
                                <td className={td} data-label={t.col_amount}>{row.amount} {row.currency}</td>
                                <td className={td} data-label={t.col_status}>
                                    {t[`payment_${row.status}`] || row.status}
                                    {row.receipt_href && (
                                        <>
                                            {' · '}
                                            <a href={row.receipt_href} className="text-[#7C2D37] underline" data-testid="payment-receipt">{t.receipt}</a>
                                        </>
                                    )}
                                </td>
                                <td className={td} data-label={t.col_date}>{row.date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
