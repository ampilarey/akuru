import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const AUDIENCES = [
    { value: 'guardians', label: 'Parents' },
    { value: 'students', label: 'Students' },
    { value: 'both', label: 'Parents and students' },
];

export default function Create({ recipients = [], classes = [] }) {
    const canPerson = recipients.length > 0;
    const canClass = classes.length > 0;

    const form = useForm({
        target_type: canClass && !canPerson ? 'class' : 'user',
        recipient_id: recipients[0]?.user_id || '',
        class_id: classes[0]?.id || '',
        audience: 'guardians',
        subject: '',
        body: '',
    });

    const selectedClass = classes.find((c) => String(c.id) === String(form.data.class_id));
    const reach = selectedClass ? selectedClass.reach[form.data.audience] : 0;

    return (
        <AppShell title="New message">
            <div className="mb-4">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/portal/messages">← All messages</a>
            </div>

            {!canPerson && !canClass ? (
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
                    {/* Only offered when the person actually has both routes;
                        a single-option chooser is noise. */}
                    {canPerson && canClass && (
                        <div className="flex gap-4 text-sm">
                            {[['user', 'A person'], ['class', 'A whole class']].map(([value, label]) => (
                                <label key={value} className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        checked={form.data.target_type === value}
                                        onChange={() => form.setData('target_type', value)}
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    )}

                    {form.data.target_type === 'user' ? (
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
                    ) : (
                        <>
                            <label className="block text-sm">
                                <span className="mb-1 block text-gray-600">Class</span>
                                <select
                                    className="form-input w-full"
                                    value={form.data.class_id}
                                    onChange={(e) => form.setData('class_id', e.target.value)}
                                >
                                    {classes.map((klass) => (
                                        <option key={klass.id} value={klass.id}>
                                            {klass.name} · {klass.students_on_roster} on roster
                                        </option>
                                    ))}
                                </select>
                                {form.errors.class_id && <span className="text-xs text-red-600">{form.errors.class_id}</span>}
                            </label>

                            <label className="block text-sm">
                                <span className="mb-1 block text-gray-600">Send to</span>
                                <select
                                    className="form-input w-full"
                                    value={form.data.audience}
                                    onChange={(e) => form.setData('audience', e.target.value)}
                                >
                                    {AUDIENCES.map((a) => (
                                        <option key={a.value} value={a.value}>{a.label}</option>
                                    ))}
                                </select>
                            </label>

                            {/* Say the size before sending, and say what the size
                                does: above five recipients replies stop going to
                                everyone, which is a surprise if discovered after. */}
                            <p className="rounded border border-[#E6D9C8] bg-[#FDFBF8] p-2 text-xs text-gray-700">
                                {reach === 0
                                    ? 'Nobody in this class has an account for that audience.'
                                    : `Goes to ${reach} account${reach === 1 ? '' : 's'}.`}
                                {reach > 5 && ' Replies come back to you only, not to the whole class.'}
                            </p>
                        </>
                    )}

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

                    <button
                        type="submit"
                        className="btn-primary justify-self-start"
                        disabled={form.processing || (form.data.target_type === 'class' && reach === 0)}
                    >
                        Send
                    </button>
                </form>
            )}
        </AppShell>
    );
}
