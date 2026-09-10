import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function when(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
}

function Poll({ poll, threadId }) {
    const form = useForm({ choice: poll.my_choice ?? '' });
    const answered = poll.my_choice !== null && poll.my_choice !== undefined;

    return (
        <section className="mb-6 rounded-lg border border-[#E6D9C8] bg-[#F9F4EE] p-4">
            <h2 className="text-sm font-semibold text-gray-900">{poll.question}</h2>
            {!poll.is_open && (
                <p className="mt-1 text-xs text-gray-600">This question is closed.</p>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/portal/messages/${threadId}/poll`, { preserveScroll: true });
                }}
                className="mt-3 space-y-2"
            >
                {poll.options.map((option, index) => (
                    <label key={option} className="flex items-center justify-between gap-3 text-sm">
                        <span className="flex items-center gap-2">
                            <input
                                type="radio"
                                name="poll-choice"
                                checked={String(form.data.choice) === String(index)}
                                onChange={() => form.setData('choice', index)}
                                disabled={!poll.is_open}
                            />
                            {option}
                        </span>
                        {/* Tallies reach the author only — on a small class an
                            aggregate is close to naming people. */}
                        {poll.tallies && (
                            <span className="text-xs font-semibold text-gray-600">{poll.tallies[index]}</span>
                        )}
                    </label>
                ))}

                {form.errors.choice && <p className="text-xs text-red-600">{form.errors.choice}</p>}

                {poll.is_open && (
                    <button type="submit" className="btn-secondary mt-1" disabled={form.processing || form.data.choice === ''}>
                        {answered ? 'Change answer' : 'Answer'}
                    </button>
                )}
            </form>

            {poll.responses !== undefined && (
                <p className="mt-2 text-xs text-gray-600">{poll.responses} answered so far.</p>
            )}
            {answered && poll.tallies === undefined && (
                <p className="mt-2 text-xs text-gray-600">You answered: {poll.options[poll.my_choice]}</p>
            )}
        </section>
    );
}

export default function Show({ thread }) {
    const form = useForm({ body: '' });

    return (
        <AppShell title={thread.subject}>
            <div className="mb-4">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/portal/messages">← All messages</a>
                <h1 className="mt-1 text-lg font-semibold">{thread.subject}</h1>
                <p className="text-xs text-gray-500">
                    {thread.participants.map((person) => person.name).join(', ')}
                </p>
            </div>

            {thread.poll && <Poll poll={thread.poll} threadId={thread.id} />}

            <ol className="mb-6 space-y-3">
                {thread.messages.map((message) => (
                    <li
                        key={message.id}
                        className={`rounded-lg border p-3 ${message.is_mine ? 'border-[#E6D9C8] bg-[#FDFBF8]' : 'bg-white'}`}
                    >
                        <div className="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <span className="text-sm font-semibold">{message.is_mine ? 'You' : message.sender}</span>
                            <span className="text-xs text-gray-500">{when(message.sent_at)}</span>
                        </div>
                        <p className="whitespace-pre-wrap text-sm text-gray-800">{message.body}</p>
                    </li>
                ))}
            </ol>

            {thread.can_reply ? (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/portal/messages/${thread.id}/reply`, {
                            preserveScroll: true,
                            onSuccess: () => form.reset('body'),
                        });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">
                            Reply
                            {/* Saying where a reply lands beats letting the sender
                                discover afterwards that only one person saw it. */}
                            {thread.reply_goes_to_author_only && (
                                <span className="text-gray-500"> — goes to the sender only</span>
                            )}
                        </span>
                        <textarea
                            className="form-input w-full"
                            rows={4}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        {form.errors.body && <span className="text-xs text-red-600">{form.errors.body}</span>}
                    </label>
                    <button type="submit" className="btn-primary mt-3" disabled={form.processing}>
                        Send reply
                    </button>
                </form>
            ) : (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Replies are turned off for this message.
                </p>
            )}
        </AppShell>
    );
}
