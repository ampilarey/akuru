import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function PaidEnroll({ row, t, offeringId = null, fee = row.fee }) {
    const [code, setCode] = useState('');

    const enroll = (payWithWallet) =>
        router.post(`/learn/courses/${row.id}/enroll`, {
            discount_code: code || undefined,
            pay_with_wallet: payWithWallet ? 1 : undefined,
            offering_id: offeringId || undefined,
        }, { preserveScroll: true });

    return (
        <div className="flex flex-wrap items-center gap-2">
            <input
                className="form-input w-32"
                placeholder={t.discount_code || 'Discount code'}
                value={code}
                onChange={(e) => setCode(e.target.value)}
            />
            <button type="button" className="btn-primary" onClick={() => enroll(false)}>
                {t.enroll_for || 'Enroll'} — MVR {fee}
            </button>
            <button type="button" className="btn-secondary" onClick={() => enroll(true)}>
                {t.pay_with_wallet || 'Pay with wallet'}
            </button>
        </div>
    );
}

/**
 * 1B: the intakes of a course — its open face-to-face, live-online, blended
 * and hybrid offerings — each with the seats it has left and its next
 * session, and its own Enroll. The default button below the list is the
 * self-paced offering, as before. Before this, no screen let a learner
 * choose a batch at all (STATUS §5fh).
 */
function Intakes({ row, t }) {
    if (!row.intakes || row.intakes.length === 0) {
        return null;
    }

    return (
        <ul className="mt-2 space-y-2 text-sm" data-testid="intakes">
            {row.intakes.map((intake) => (
                <li key={intake.id} className="rounded border bg-[#FAF7F2] p-2">
                    <p className="font-medium">
                        {intake.title}
                        <span className="ms-2 text-xs text-gray-600">{intake.delivery_mode_label}</span>
                    </p>
                    <p className="text-xs text-gray-600">
                        {intake.seats_left === null
                            ? (t.seats_unlimited || 'Open seats')
                            : intake.full
                                ? (t.full || 'Full')
                                : `${intake.seats_left} ${intake.seats_left === 1 ? (t.seat_left || 'seat left') : (t.seats_left || 'seats left')}`}
                        {intake.next_session && ` · ${t.next_session || 'Next'}: ${intake.next_session.title} · ${intake.next_session.starts_at}`}
                    </p>
                    {!row.enrolled && !intake.full && (
                        intake.fee > 0 ? (
                            <PaidEnroll row={row} t={t} offeringId={intake.id} fee={intake.fee} />
                        ) : (
                            <button
                                type="button"
                                className="btn-primary mt-1"
                                onClick={() => router.post(`/learn/courses/${row.id}/enroll`, { offering_id: intake.id }, { preserveScroll: true })}
                            >
                                {t.enroll_intake || 'Enroll in this intake'}
                            </button>
                        )
                    )}
                    {row.enrolled && row.enrolled_offering_id === intake.id && (
                        <span className="text-xs font-medium text-green-800">{t.your_intake || 'Your intake'}</span>
                    )}
                </li>
            ))}
        </ul>
    );
}

export default function Catalog({ rows }) {
    const t = usePage().props.i18n?.learn || {};
    const flash = usePage().props.flash || {};

    return (
        <AppShell title={t.catalog_title || 'Learn catalog'}>
            <p className="mb-4 text-sm text-gray-600">{t.catalog_intro || 'Published courses. Enroll self-paced, or choose an intake where one is open.'}</p>
            {flash.error && <p className="mb-4 rounded bg-red-50 p-3 text-red-700">{flash.error}</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.course || 'Course'}</th>
                            <th className="px-3 py-2">{t.progress || 'Progress'}</th>
                            <th className="px-3 py-2">{t.action || 'Action'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.no_published || 'No published courses yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <p className="font-medium">{row.title}</p>
                                    {row.short_desc && <p className="text-xs text-gray-500">{row.short_desc}</p>}
                                    <Intakes row={row} t={t} />
                                </td>
                                <td className="px-3 py-2">{row.enrolled ? `${row.progress_percentage}%` : '—'}</td>
                                <td className="px-3 py-2">
                                    {row.enrolled ? (
                                        <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${row.id}`}>{t.open || 'Open'}</a>
                                    ) : row.fee > 0 ? (
                                        <PaidEnroll row={row} t={t} />
                                    ) : (
                                        <button type="button" className="btn-primary" onClick={() => router.post(`/learn/courses/${row.id}/enroll`)}>{t.enroll || 'Enroll'}</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
