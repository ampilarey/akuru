import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const WINDOWS = [1, 7, 30];

/**
 * Every time a sign-in code's limit refused somebody, grouped by contact. The
 * page's words, what tripped and the channel are the `admin` book's (slice
 * SY1, STATUS §5qu).
 */
export default function OtpAbuse({ groups = [], days = 7, total_trips = 0, limits = {}, t = {} }) {
    return (
        <AppShell title={t.otp_abuse_title || 'OTP abuse events'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.otp_abuse_intro || 'Every time an OTP limit refused somebody, grouped by contact.'}{' '}
                <strong>{t.otp_abuse_question || 'One person or many?'}</strong>{' '}
                {t.otp_abuse_why || 'A parent pressing Resend four times in a minute is a support call; forty contacts hitting the send ceiling in an hour is the SMS cost abuse these limits exist to stop.'}
            </p>

            <div className="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                <strong>{t.otp_abuse_limits || 'Limits in force:'}</strong>{' '}
                {(t.otp_abuse_limits_line || ':sends sends per :minutes minutes · :seconds seconds between resends · :attempts verify attempts. Events are kept :days days.')
                    .replace(':sends', limits.max_sends)
                    .replace(':minutes', limits.send_window_minutes)
                    .replace(':seconds', limits.resend_cooldown_seconds)
                    .replace(':attempts', limits.max_verify_attempts)
                    .replace(':days', limits.retention_days)}
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                {WINDOWS.map((d) => (
                    <button
                        key={d}
                        type="button"
                        className={d === days ? 'btn-primary' : 'btn-secondary'}
                        onClick={() => router.get('/admin/users/otp-abuse', { days: d })}
                    >
                        {d === 1 ? (t.otp_abuse_last_one || 'Last day') : (t.otp_abuse_last_many || 'Last :days days').replace(':days', d)}
                    </button>
                ))}
                <a className="btn-secondary" href={`/admin/users/otp-abuse/export?days=${days}`}>
                    {t.ft_export || 'Export CSV'}
                </a>
                {/* A way back to the roster this log belongs to (the page-by-page sweep, STATUS §5hw). */}
                <a className="text-sm text-[#7C2D37] hover:underline" href="/admin/users" data-testid="back-link">{t.otp_abuse_back || '← Users'}</a>
                <span className="text-sm text-gray-500">
                    {(t.otp_abuse_trips || ':trips trips across :contacts contacts')
                        .replace(':trips', total_trips.toLocaleString())
                        .replace(':contacts', groups.length.toLocaleString())}
                </span>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.otp_abuse_col_contact || 'Contact'}</th>
                            <th className="px-3 py-2">{t.otp_abuse_col_account || 'Account'}</th>
                            <th className="px-3 py-2">{t.otp_abuse_col_trips || 'Trips'}</th>
                            <th className="px-3 py-2">{t.otp_abuse_col_kinds || 'What tripped'}</th>
                            <th className="px-3 py-2">{t.otp_abuse_col_first || 'First seen'}</th>
                            <th className="px-3 py-2">{t.otp_abuse_col_last || 'Last seen'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={6}>
                                    {t.otp_abuse_none || 'Nothing tripped a limit in this window. That is the expected state.'}
                                </td>
                            </tr>
                        )}
                        {groups.map((group) => (
                            <tr key={group.key} className="border-t align-top">
                                <td data-label={t.otp_abuse_col_contact || 'Contact'} className="px-3 py-2 whitespace-nowrap">
                                    <span className="font-mono">…{group.contact_tail}</span>
                                    <span className="ms-2 text-xs text-gray-500">{t[`otp_channel_${group.channel}`] || group.channel}</span>
                                    {/* For a mobile number the last four digits identify it. For an
                                        email they are the end of the domain, which every address at
                                        one provider shares — so the group's short code is what
                                        actually tells two rows apart. */}
                                    <span className="ms-2 font-mono text-xs text-gray-400">{group.key}</span>
                                </td>
                                <td data-label={t.otp_abuse_col_account || 'Account'} className="px-3 py-2">
                                    {group.user ?? <span className="text-gray-400">{t.otp_abuse_no_account || 'no account yet'}</span>}
                                </td>
                                <td data-label={t.otp_abuse_col_trips || 'Trips'} className="px-3 py-2 whitespace-nowrap">{group.trips}</td>
                                <td data-label={t.otp_abuse_col_kinds || 'What tripped'} className="px-3 py-2">
                                    {Object.entries(group.kinds).map(([kind, count]) => (
                                        <span
                                            key={kind}
                                            className="me-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs"
                                        >
                                            {t[`otp_kind_${kind}`] || kind} ×{count}
                                        </span>
                                    ))}
                                </td>
                                <td data-label={t.otp_abuse_col_first || 'First seen'} className="px-3 py-2 whitespace-nowrap">{group.first_seen}</td>
                                <td data-label={t.otp_abuse_col_last || 'Last seen'} className="px-3 py-2 whitespace-nowrap">{group.last_seen}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-xs text-gray-500">
                {t.otp_abuse_footnote || 'Contacts are stored hashed — only the last four characters are kept, so this list stays reviewable without being a phone book.'}
            </p>
        </AppShell>
    );
}
