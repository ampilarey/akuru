import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * *My enrolments* (docs/SIGN_IN_PLAN.md ID2b): every course enrolment this
 * login made, a child's included, and the course payments beside them — what
 * the old course portal spread over four Blade pages. A receipt is linked
 * only where the money is confirmed; the old pages tested for statuses the
 * payments table cannot hold and never linked one.
 */
const th = 'px-3 py-2 text-start';
const td = 'px-3 py-2';

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
                            <th className={th}>{t.col_date}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrolments.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={5}>
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
