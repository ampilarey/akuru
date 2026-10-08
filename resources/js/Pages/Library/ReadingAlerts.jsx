import { Fragment } from 'react';
import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

// The office's words, from the `admin` book (slice LT3).
const OUTCOMES = [
    ['legitimate', 'library_alerts_outcome_legitimate', 'Legitimate'],
    ['watching', 'library_alerts_outcome_watching', 'Keep watching'],
    ['abuse', 'library_alerts_outcome_abuse', 'Abuse'],
];

// The setting that turns enforcement on: its name, the same in every language.
const ENFORCE_FLAG = 'LIBRARY_ABUSE_ENFORCE';

// A phrase with an element in it, where the phrase puts it.
const withNodes = (text, nodes) => String(text).split(/(:[a-z_]+)/).map((part, index) => {
    const name = part.startsWith(':') ? part.slice(1) : null;

    return name !== null && nodes[name] !== undefined ? <Fragment key={index}>{nodes[name]}</Fragment> : part;
});

export default function ReadingAlerts({ alerts = [], open_only = true, enforcing = false, events_logged = 0, t = {} }) {
    // An alert's outcome is said on its row when it is refused — one
    // already reviewed in another tab, say.
    const refusals = useRowRefusals();
    const review = (id, outcome) => refusals.actOn(`alert:${id}`, () => router.post(`/admin/library/reading-alerts/${id}/review`, { outcome }, { preserveScroll: true }));

    const exportHref = `/admin/library/reading-alerts/export${open_only ? '' : '?all=1'}`;
    const outcomeLabel = (outcome) => {
        const known = OUTCOMES.find(([value]) => value === outcome);

        return known ? t[known[1]] || known[2] : outcome;
    };

    return (
        <AppShell title={t.library_alerts_title || 'Reading alerts'}>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <p className="mb-3 text-sm text-gray-600">
                {withNodes(t.library_alerts_intro || 'Patterns the protected reader noticed. Each one is a :question — a reader skimming a reference book turns pages fast, and a family sharing an account across a phone and a laptop is not a book being resold.', {
                    question: <strong>{t.library_alerts_question || 'question, not a verdict'}</strong>,
                })}
            </p>

            <div
                className={`mb-4 rounded-lg border p-3 text-sm ${
                    enforcing ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-gray-200 bg-gray-50 text-gray-700'
                }`}
            >
                {enforcing ? (
                    <>
                        <strong>{t.library_alerts_enforcing || 'Enforcement is ON.'}</strong> {t.library_alerts_enforcing_body || 'Readers over the session limit are being refused pages.'}
                    </>
                ) : (
                    <>
                        <strong>{t.library_alerts_observing || 'Enforcement is off.'}</strong>{' '}
                        {withNodes(t.library_alerts_observing_body || 'Nobody is being blocked — these are observations only. Turn it on with :flag once the thresholds have been checked against real readers.', {
                            flag: <code>{ENFORCE_FLAG}</code>,
                        })}
                    </>
                )}{' '}
                {fill(t.library_alerts_logged || ':count page views logged.', { count: events_logged.toLocaleString() })}
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    className="btn-secondary"
                    onClick={() => router.get(`/admin/library/reading-alerts${open_only ? '?all=1' : ''}`)}
                >
                    {open_only ? t.library_alerts_show_all || 'Show reviewed too' : t.library_alerts_show_open || 'Show open only'}
                </button>
                <a className="btn-secondary" href={exportHref}>{t.library_office_export_csv || 'Export CSV'}</a>
                {/* A way back to the Library office this list belongs to (the page-by-page sweep, STATUS §5hw). */}
                <Link className="text-sm text-[#7C2D37] hover:underline" href="/admin/library" data-testid="back-link">{t.library_alerts_back || '← Library office'}</Link>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.library_alerts_th_signal || 'Signal'}</th>
                            <th className="px-3 py-2">{t.library_alerts_th_reader || 'Reader'}</th>
                            <th className="px-3 py-2">{t.library_alerts_th_item || 'Item'}</th>
                            <th className="px-3 py-2">{t.library_alerts_th_observed || 'Observed'}</th>
                            <th className="px-3 py-2">{t.library_alerts_th_raised || 'Raised'}</th>
                            <th className="px-3 py-2">{t.library_alerts_th_outcome || 'Outcome'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {alerts.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={7}>
                                    {t.library_alerts_none || 'Nothing flagged. That is the expected state.'}
                                </td>
                            </tr>
                        )}
                        {alerts.map((alert) => (
                            <tr key={alert.id} className="border-t align-top">
                                <td data-label={t.library_alerts_th_signal || 'Signal'} className="px-3 py-2">
                                    {t[`library_alerts_signal_${alert.signal}`] || alert.signal_label}
                                    {/* What the detector saw, said in the page's language when its shape is known (the record keeps the words it was written with). */}
                                    {(alert.detail_said || alert.detail) && <p className="mt-1 text-xs text-gray-500">{alert.detail_said || alert.detail}</p>}
                                </td>
                                <td data-label={t.library_alerts_th_reader || 'Reader'} className="px-3 py-2">{alert.reader}</td>
                                <td data-label={t.library_alerts_th_item || 'Item'} className="px-3 py-2">{alert.item_title ?? '—'}</td>
                                <td data-label={t.library_alerts_th_observed || 'Observed'} className="px-3 py-2 whitespace-nowrap">
                                    {alert.observed} <span className="text-gray-500">/ {alert.threshold}</span>
                                </td>
                                <td data-label={t.library_alerts_th_raised || 'Raised'} className="px-3 py-2 whitespace-nowrap">{alert.raised_at}</td>
                                <td data-label={t.library_alerts_th_outcome || 'Outcome'} className="px-3 py-2">{alert.outcome ? outcomeLabel(alert.outcome) : <span className="text-gray-400">{t.library_alerts_open || 'open'}</span>}</td>
                                <td className="table-actions px-3 py-2 whitespace-nowrap">
                                    {!alert.reviewed_at &&
                                        OUTCOMES.map(([value, key, label]) => (
                                            <button
                                                key={value}
                                                type="button"
                                                className="btn-secondary me-1 text-xs"
                                                onClick={() => review(alert.id, value)}
                                            >
                                                {t[key] || label}
                                            </button>
                                        ))}
                                    <FormErrors errors={refusals.errorsFor(`alert:${alert.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function fill(text, values) {
    return Object.entries(values).reduce((out, [key, value]) => out.split(`:${key}`).join(String(value ?? '')), String(text));
}
