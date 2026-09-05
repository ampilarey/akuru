import AppShell from '../../../Layouts/AppShell';

function when(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
}

export default function Index({ threads = [], canCompose = false }) {
    return (
        <AppShell title="Messages">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">Conversations with your child&apos;s teachers.</p>
                {canCompose && <a className="btn-primary" href="/portal/messages/new">New message</a>}
            </div>

            {threads.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {canCompose
                        ? 'No messages yet. Start one with “New message”.'
                        : 'No messages yet. Once your child is on a class roster with a timetable, their teachers appear here.'}
                </p>
            )}

            <ul className="grid gap-2">
                {threads.map((thread) => (
                    <li key={thread.id}>
                        <a
                            href={thread.href}
                            className={`block rounded-lg border bg-white p-3 hover:border-[#7C2D37] ${thread.unread > 0 ? 'border-[#7C2D37]' : ''}`}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className={`text-sm ${thread.unread > 0 ? 'font-bold' : 'font-medium'}`}>
                                    {thread.subject}
                                    {thread.unread > 0 && (
                                        <span className="ms-2 rounded-full bg-[#7C2D37] px-2 py-0.5 text-xs font-bold text-white">
                                            {thread.unread}
                                        </span>
                                    )}
                                </p>
                                <span className="text-xs text-gray-500">{when(thread.last_message_at)}</span>
                            </div>
                            {thread.with?.length > 0 && (
                                <p className="mt-0.5 text-xs text-gray-500">With {thread.with.join(', ')}</p>
                            )}
                            {thread.preview && <p className="mt-1 text-sm text-gray-600">{thread.preview}</p>}
                        </a>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
