import AppShell from '../../Layouts/AppShell';

const TYPE_LABELS = {
    general: 'General',
    academic: 'Academic',
    quran: 'Qur’an',
    event: 'Event',
    holiday: 'Holiday',
    emergency: 'Emergency',
};

export default function Announcements({ announcements = [], csvUrl = '/portal/announcements/export' }) {
    // Urgent first, then the order the action already sorted them in (newest
    // published first). A notice marked urgent that sits below three general
    // ones has been marked urgent for nothing.
    const sorted = [...announcements].sort((a, b) => (b.is_urgent ? 1 : 0) - (a.is_urgent ? 1 : 0));

    return (
        <AppShell title="Noticeboard">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    Notices for you and your class. Urgent ones are shown first.
                </p>
                {announcements.length > 0 && <a className="btn-secondary" href={csvUrl}>Export CSV</a>}
            </div>

            {announcements.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    No notices at the moment.
                </p>
            )}

            <ul className="grid gap-3">
                {sorted.map((item) => (
                    <li
                        key={item.id}
                        className={`rounded-lg border bg-white p-4 ${item.is_urgent ? 'border-[#7C2D37]' : ''}`}
                    >
                        <div className="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <h2 className="text-sm font-semibold text-gray-900">
                                {item.title}
                                {item.is_urgent && (
                                    <span className="ms-2 rounded-full bg-[#7C2D37] px-2 py-0.5 text-xs font-bold uppercase text-white">
                                        {item.priority}
                                    </span>
                                )}
                            </h2>
                            <span className="text-xs text-gray-500">{item.published_on}</span>
                        </div>
                        <p className="mb-2 text-xs uppercase tracking-wide text-gray-500">
                            {TYPE_LABELS[item.type] || item.type}
                            {item.expires_on ? ` · until ${item.expires_on}` : ''}
                        </p>
                        <p className="whitespace-pre-wrap text-sm text-gray-800">{item.content}</p>
                        {item.has_attachment && (
                            <p className="mt-2 text-xs text-gray-500">This notice has an attachment.</p>
                        )}
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
