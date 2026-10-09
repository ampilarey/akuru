import { useForm, usePage } from '@inertiajs/react';
import FormErrors from '../../Components/FormErrors';
import AppShell from '../../Layouts/AppShell';

export default function AbsenceNotes({ children, notes, types, periods = [], t = {} }) {
    const form = useForm({
        student_id: children[0]?.id || '',
        date: '',
        period_id: '',
        reason: '',
        absence_type_id: types[0]?.id ?? '',
        attachment: null,
    });

    // E10c: the school defines its own reasons, and a reason can require a
    // document. Saying so before the parent submits is cheaper than a refusal
    // after they have typed everything.
    const chosenId = String(form.data.absence_type_id);
    const chosen = types.find((type) => `${type.id}` === chosenId);
    // The school names its own reasons, in Dhivehi and Arabic where it has
    // (BACKLOG C21, slice OA1).
    const locale = usePage().props.locale || 'en';
    const typeName = (type) => ({ dv: type.name_dhivehi, ar: type.name_arabic }[locale]) || type.name;

    return (
        <AppShell title={t.absence_title || 'Absence notes'}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/portal/absence-notes', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.pick_child || 'Child'}</span>
                    <select className="form-input w-full" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                        {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.col_date || 'Date'}</span>
                    <input className="form-input w-full" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                    {form.errors.date && <span className="text-xs text-red-600">{form.errors.date}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.col_type || 'Type'}</span>
                    <select className="form-input w-full" value={form.data.absence_type_id}
                        onChange={(e) => form.setData('absence_type_id', e.target.value)}>
                        {types.map((type) => <option key={type.id} value={type.id}>{typeName(type)}</option>)}
                    </select>
                    {chosen?.requires_evidence && (
                        <span className="mt-1 block text-xs text-[#7C2D37]">
                            {t.absence_needs_document || 'This reason needs a document attached.'}
                        </span>
                    )}
                    {chosen && !chosen.excuses_absence && (
                        <span className="mt-1 block text-xs text-gray-600">
                            {t.absence_still_absent || 'The day will still be recorded as absent.'}
                        </span>
                    )}
                    {form.errors.absence_type_id && <span className="text-xs text-red-600">{form.errors.absence_type_id}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.absence_lesson || 'Lesson'}</span>
                    <select className="form-input w-full" value={form.data.period_id} onChange={(e) => form.setData('period_id', e.target.value)}>
                        <option value="">{t.absence_whole_day || 'Whole day'}</option>
                        {periods.map((period) => (
                            <option key={period.id} value={period.id}>{period.name} ({period.start_time}–{period.end_time})</option>
                        ))}
                    </select>
                </label>
                {/* The controller has accepted a file since S2.4; the form never
                    offered one, so a reason marked "needs a document" could not
                    be sent from here at all (STATUS §5ev). */}
                <label className="block text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">
                        {chosen?.requires_evidence
                            ? (t.absence_document_required || 'Document (required for this reason)')
                            : (t.absence_document_optional || 'Document (optional)')}
                    </span>
                    <input
                        className="form-input w-full"
                        type="file"
                        accept="image/*,.pdf"
                        aria-label={t.absence_document || 'Document'}
                        onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)}
                    />
                    {(form.errors.attachment_path || form.errors.attachment) && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.attachment_path || form.errors.attachment}</span>
                    )}
                </label>
                <label className="block text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">{t.absence_reason || 'Reason'}</span>
                    <textarea className="form-input w-full" rows={3} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                    {form.errors.reason && <span className="text-xs text-red-600">{form.errors.reason}</span>}
                </label>
                <FormErrors errors={form.errors} except={['date', 'absence_type_id', 'attachment_path', 'attachment', 'reason']} className="md:col-span-2" />
                <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>{t.absence_submit || 'Submit note'}</button>
            </form>

            <ul className="grid gap-2">
                {notes.length === 0 && (
                    <li className="rounded-lg border bg-white p-3 text-sm text-gray-600">{t.absence_none || 'No absence notes yet.'}</li>
                )}
                {notes.map((note) => (
                    <li key={note.id} className="rounded-lg border bg-white p-3 text-sm">
                        <span className="text-xs font-semibold">{t[`absence_status_${note.status}`] || note.status}</span>
                        {' · '}
                        {note.date}{note.period_name ? ` · ${note.period_name}` : ''} · {note.student_name}: {note.reason}
                        {note.attachment_url && (
                            <>
                                {' · '}
                                <a className="text-[#7C2D37] underline" href={note.attachment_url}>{t.absence_document || 'Document'}</a>
                            </>
                        )}
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
