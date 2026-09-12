import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const KIND_LABELS = {
    send_rate: 'Send ceiling',
    resend_cooldown: 'Resend cooldown',
    verify_rate: 'Verify attempts',
    code_attempts: 'Attempts on one code',
};

const WINDOWS = [1, 7, 30];

export default function OtpAbuse({ groups = [], days = 7, total_trips = 0, limits = {} }) {
    return (
        <AppShell title="OTP abuse events">
            <p className="mb-4 text-sm text-gray-600">
                Every time an OTP limit refused somebody. Grouped by contact, because the question worth
                answering is <strong>one person or many</strong> — a parent pressing Resend four times in a
                minute is a support call, forty contacts hitting the send ceiling in an hour is the SMS cost
                abuse these limits exist to stop.
            </p>

            <div className="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                <strong>Limits in force:</strong> {limits.max_sends} sends per {limits.send_window_minutes}{' '}
                minutes · {limits.resend_cooldown_seconds}s between resends · {limits.max_verify_attempts}{' '}
                verify attempts. Events are kept {limits.retention_days} days.
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                {WINDOWS.map((d) => (
                    <button
                        key={d}
                        type="button"
                        className={d === days ? 'btn-primary' : 'btn-secondary'}
                        onClick={() => router.get('/admin/users/otp-abuse', { days: d })}
                    >
                        Last {d} {d === 1 ? 'day' : 'days'}
                    </button>
                ))}
                <a className="btn-secondary" href={`/admin/users/otp-abuse/export?days=${days}`}>
                    Export CSV
                </a>
                <span className="text-sm text-gray-500">
                    {total_trips.toLocaleString()} trips across {groups.length.toLocaleString()} contacts
                </span>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Contact</th>
                            <th className="px-3 py-2">Account</th>
                            <th className="px-3 py-2">Trips</th>
                            <th className="px-3 py-2">What tripped</th>
                            <th className="px-3 py-2">First seen</th>
                            <th className="px-3 py-2">Last seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={6}>
                                    Nothing tripped a limit in this window. That is the expected state.
                                </td>
                            </tr>
                        )}
                        {groups.map((group) => (
                            <tr key={group.key} className="border-t align-top">
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <span className="font-mono">…{group.contact_tail}</span>
                                    <span className="ms-2 text-xs text-gray-500">{group.channel}</span>
                                    {/* For a mobile number the last four digits identify it. For an
                                        email they are the end of the domain, which every address at
                                        one provider shares — so the group's short code is what
                                        actually tells two rows apart. */}
                                    <span className="ms-2 font-mono text-xs text-gray-400">{group.key}</span>
                                </td>
                                <td className="px-3 py-2">
                                    {group.user ?? <span className="text-gray-400">no account yet</span>}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">{group.trips}</td>
                                <td className="px-3 py-2">
                                    {Object.entries(group.kinds).map(([kind, count]) => (
                                        <span
                                            key={kind}
                                            className="me-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs"
                                        >
                                            {KIND_LABELS[kind] ?? kind} ×{count}
                                        </span>
                                    ))}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">{group.first_seen}</td>
                                <td className="px-3 py-2 whitespace-nowrap">{group.last_seen}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-xs text-gray-500">
                Contacts are stored hashed — only the last four characters are kept, so this list stays
                reviewable without being a phone book.
            </p>
        </AppShell>
    );
}
