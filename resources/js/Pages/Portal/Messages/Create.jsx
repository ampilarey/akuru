import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

const AUDIENCES = ['guardians', 'students', 'both'];

export default function Create({ recipients = [], classes = [], t = {} }) {
    const canPerson = recipients.length > 0;
    const canClass = classes.length > 0;

    const form = useForm({
        target_type: canClass && !canPerson ? 'class' : 'user',
        recipient_id: recipients[0]?.user_id || '',
        class_id: classes[0]?.id || '',
        audience: 'guardians',
        subject: '',
        body: '',
        poll_question: '',
        poll_options: ['', ''],
    });

    const selectedClass = classes.find((c) => String(c.id) === String(form.data.class_id));
    const reach = selectedClass ? selectedClass.reach[form.data.audience] : 0;
    const audienceLabel = {
        guardians: t.messages_audience_guardians || 'Parents',
        students: t.messages_audience_students || 'Students',
        both: t.messages_audience_both || 'Parents and students',
    };

    return (
        <AppShell title={t.messages_new || 'New message'}>
            <div className="mb-4">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/portal/messages">{t.messages_all || '← All messages'}</a>
            </div>

            {!canPerson && !canClass ? (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.messages_nobody || 'There is nobody to write to yet. Teachers appear here once your child is on a class roster with a timetable.'}
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
                            {[['user', t.messages_target_user || 'A person'], ['class', t.messages_target_class || 'A whole class']].map(([value, label]) => (
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
                            <span className="mb-1 block text-gray-600">{t.messages_to || 'To'}</span>
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
                                <span className="mb-1 block text-gray-600">{t.messages_class || 'Class'}</span>
                                <select
                                    className="form-input w-full"
                                    value={form.data.class_id}
                                    onChange={(e) => form.setData('class_id', e.target.value)}
                                >
                                    {classes.map((klass) => (
                                        <option key={klass.id} value={klass.id}>
                                            {klass.name} · {(t.messages_on_roster || ':count on roster').replace(':count', klass.students_on_roster)}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.class_id && <span className="text-xs text-red-600">{form.errors.class_id}</span>}
                            </label>

                            <label className="block text-sm">
                                <span className="mb-1 block text-gray-600">{t.messages_send_to || 'Send to'}</span>
                                <select
                                    className="form-input w-full"
                                    value={form.data.audience}
                                    onChange={(e) => form.setData('audience', e.target.value)}
                                >
                                    {AUDIENCES.map((value) => (
                                        <option key={value} value={value}>{audienceLabel[value]}</option>
                                    ))}
                                </select>
                            </label>

                            {/* Say the size before sending, and say where replies
                                go: to the sender only, for a class send of any
                                size — a surprise if discovered after. */}
                            <p className="rounded border border-[#E6D9C8] bg-[#FDFBF8] p-2 text-xs text-gray-700">
                                {reach === 0
                                    ? (t.messages_reach_none || 'Nobody in this class has an account for that audience.')
                                    : reach === 1
                                        ? (t.messages_reach_one || 'Goes to 1 account. Replies come back to you only, not to the whole class.')
                                        : (t.messages_reach_many || 'Goes to :count accounts. Replies come back to you only, not to the whole class.').replace(':count', reach)}
                            </p>
                        </>
                    )}

                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.messages_subject || 'Subject'}</span>
                        <input
                            className="form-input w-full"
                            type="text"
                            value={form.data.subject}
                            onChange={(e) => form.setData('subject', e.target.value)}
                        />
                        {form.errors.subject && <span className="text-xs text-red-600">{form.errors.subject}</span>}
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.messages_body || 'Message'}</span>
                        <textarea
                            className="form-input w-full"
                            rows={6}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        {form.errors.body && <span className="text-xs text-red-600">{form.errors.body}</span>}
                    </label>

                    {/* Only classes get a question: "will your child attend?"
                        is a class-sized ask, and a poll of one is a message. */}
                    {form.data.target_type === 'class' && (
                        <fieldset className="rounded border border-[#E6D9C8] p-3">
                            <legend className="px-1 text-xs uppercase tracking-wide text-gray-500">
                                {t.poll_legend || 'Ask a question (optional)'}
                            </legend>
                            <input
                                className="form-input w-full"
                                type="text"
                                placeholder={t.poll_placeholder || 'e.g. Will your child attend the trip?'}
                                aria-label={t.poll_placeholder || 'e.g. Will your child attend the trip?'}
                                value={form.data.poll_question}
                                onChange={(e) => form.setData('poll_question', e.target.value)}
                            />
                            {form.errors['poll.question'] && (
                                <span className="text-xs text-red-600">{form.errors['poll.question']}</span>
                            )}
                            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                {form.data.poll_options.map((option, index) => (
                                    <input
                                        key={index}
                                        className="form-input w-full"
                                        type="text"
                                        placeholder={(t.poll_option || 'Option :number').replace(':number', index + 1)}
                                        aria-label={(t.poll_option || 'Option :number').replace(':number', index + 1)}
                                        value={option}
                                        onChange={(e) => {
                                            const next = [...form.data.poll_options];
                                            next[index] = e.target.value;
                                            form.setData('poll_options', next);
                                        }}
                                    />
                                ))}
                            </div>
                            {form.data.poll_options.length < 10 && (
                                <button
                                    type="button"
                                    className="mt-2 text-xs text-[#7C2D37] hover:underline"
                                    onClick={() => form.setData('poll_options', [...form.data.poll_options, ''])}
                                >
                                    {t.poll_add_option || 'Add option'}
                                </button>
                            )}
                            {form.errors['poll.options'] && (
                                <span className="block text-xs text-red-600">{form.errors['poll.options']}</span>
                            )}
                        </fieldset>
                    )}

                    {/* What no field above says (BACKLOG C21, slice PT1b):
                        `recipients`, a thread's refusal, and the audience had
                        no field to sit under and were dropped. */}
                    <FormErrors errors={form.errors} except={['recipient_id', 'class_id', 'subject', 'body', 'poll.question', 'poll.options']} />

                    <button
                        type="submit"
                        className="btn-primary justify-self-start"
                        disabled={form.processing || (form.data.target_type === 'class' && reach === 0)}
                    >
                        {t.messages_send || 'Send'}
                    </button>
                </form>
            )}
        </AppShell>
    );
}
