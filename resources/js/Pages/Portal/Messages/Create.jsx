import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Create({ recipients = [] }) {
    const form = useForm({
        recipient_id: recipients[0]?.user_id || '',
        subject: '',
        body: '',
    });

    return (
        <AppShell title="New message">
            <div className="mb-4">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/portal/messages">← All messages</a>
            </div>

            {recipients.length === 0 ? (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    There is nobody to write to yet. Teachers appear here once your child is on a class
                    roster with a timetable.
                </p>
            ) : (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/portal/messages');
                    }}
                    className="grid gap-3 rounded-lg border bg-white p-4"
                >
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">To</span>
                        <select
                            className="form-input w-full"
                            value={form.data.recipient_id}
                            onChange={(e) => form.setData('recipient_id', e.target.value)}
                        >
                            {recipients.map((person) => (
                                <option key={person.user_id} value={person.user_id}>
                                    {person.name}{person.context ? ` — ${person.context}` : ''}
                                </option>
                            ))}
                        </select>
                        {form.errors.recipient_id && <span className="text-xs text-red-600">{form.errors.recipient_id}</span>}
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Subject</span>
                        <input
                            className="form-input w-full"
                            type="text"
                            value={form.data.subject}
                            onChange={(e) => form.setData('subject', e.target.value)}
                        />
                        {form.errors.subject && <span className="text-xs text-red-600">{form.errors.subject}</span>}
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Message</span>
                        <textarea
                            className="form-input w-full"
                            rows={6}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        {form.errors.body && <span className="text-xs text-red-600">{form.errors.body}</span>}
                    </label>

                    <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                        Send
                    </button>
                </form>
            )}
        </AppShell>
    );
}
