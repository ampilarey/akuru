import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const OUTCOMES = [
    ['legitimate', 'Legitimate'],
    ['watching', 'Keep watching'],
    ['abuse', 'Abuse'],
];

export default function ReadingAlerts({ alerts = [], open_only = true, enforcing = false, events_logged = 0 }) {
    const review = (id, outcome) => {
        router.post(`/admin/library/reading-alerts/${id}/review`, { outcome }, { preserveScroll: true });
    };

    const exportHref = `/admin/library/reading-alerts/export${open_only ? '' : '?all=1'}`;

    return (
        <AppShell title="Reading alerts">
            <p className="mb-3 text-sm text-gray-600">
                Patterns the protected reader noticed. Each one is a <strong>question, not a verdict</strong> — a
                reader skimming a reference book turns pages fast, and a family sharing an account across a phone
                and a laptop is not a book being resold.
            </p>

            <div
                className={`mb-4 rounded-lg border p-3 text-sm ${
                    enforcing ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-gray-200 bg-gray-50 text-gray-700'
                }`}
            >
                {enforcing ? (
                    <>
                        <strong>Enforcement is ON.</strong> Readers over the session limit are being refused pages.
                    </>
                ) : (
                    <>
                        <strong>Enforcement is off.</strong> Nobody is being blocked — these are observations only.
                        Turn it on with <code>LIBRARY_ABUSE_ENFORCE</code> once the thresholds have been checked
                        against real readers.
                    </>
                )}{' '}
                {events_logged.toLocaleString()} page views logged.
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    className="btn-secondary"
                    onClick={() => router.get(`/admin/library/reading-alerts${open_only ? '?all=1' : ''}`)}
                >
                    {open_only ? 'Show reviewed too' : 'Show open only'}
                </button>
                <a className="btn-secondary" href={exportHref}>Export CSV</a>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Signal</th>
                            <th className="px-3 py-2">Reader</th>
                            <th className="px-3 py-2">Item</th>
                            <th className="px-3 py-2">Observed</th>
                            <th className="px-3 py-2">Raised</th>
                            <th className="px-3 py-2">Outcome</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {alerts.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={7}>
                                    Nothing flagged. That is the expected state.
                                </td>
                            </tr>
                        )}
                        {alerts.map((alert) => (
                            <tr key={alert.id} className="border-t align-top">
                                <td className="px-3 py-2">
                                    {alert.signal_label}
                                    {alert.detail && <p className="mt-1 text-xs text-gray-500">{alert.detail}</p>}
                                </td>
                                <td className="px-3 py-2">{alert.reader}</td>
                                <td className="px-3 py-2">{alert.item_title ?? '—'}</td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    {alert.observed} <span className="text-gray-500">/ {alert.threshold}</span>
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">{alert.raised_at}</td>
                                <td className="px-3 py-2">{alert.outcome ?? <span className="text-gray-400">open</span>}</td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    {!alert.reviewed_at &&
                                        OUTCOMES.map(([value, label]) => (
                                            <button
                                                key={value}
                                                type="button"
                                                className="btn-secondary me-1 text-xs"
                                                onClick={() => review(alert.id, value)}
                                            >
                                                {label}
                                            </button>
                                        ))}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
