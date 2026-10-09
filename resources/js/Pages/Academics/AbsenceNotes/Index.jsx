import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function Index({ status, statuses, notes, types = [], t = {} }) {
    const locale = usePage().props.locale || 'en';
    // In the page's language (BACKLOG C21, slice OA1). A note's state is a
    // code, named here; its reason is a code the school named itself, in
    // Dhivehi and Arabic where it has — a retired reason too, since last
    // term's notes still point at it.
    const typeName = (code) => {
        const type = types.find((item) => item.code === code);

        return type ? ({ dv: type.name_dhivehi, ar: type.name_arabic }[locale] || type.name) : code;
    };

    return (
        <AppShell title={t.notes_title || 'Absence notes'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select className="form-input" aria-label={t.status || 'Status'} value={status || ''} onChange={(e) => router.get(`/academics/absence-notes?status=${e.target.value}`)}>
                    <option value="">{t.all || 'All'}</option>
                    {statuses.map((item) => <option key={item} value={item}>{t[`note_status_${item}`] || item}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/absence-notes/export?status=${status || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <div className="grid gap-3">
                {notes.map((note) => <NoteCard key={note.id} note={note} typeName={typeName} t={t} />)}
                {notes.length === 0 && <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.notes_none || 'No notes.'}</p>}
            </div>
        </AppShell>
    );
}

function NoteCard({ note, typeName, t }) {
    const form = useForm({ review_notes: '' });

    return (
        <section className="rounded-lg border bg-white p-4 text-sm">
            <div className="mb-2 flex flex-wrap justify-between gap-2">
                <p className="font-semibold">{note.student_name} · {note.date}{note.period_name ? ` · ${note.period_name}` : ''}</p>
                <span className="uppercase text-xs">{t[`note_status_${note.status}`] || note.status}</span>
            </div>
            <p className="mb-1">{typeName(note.type)}: {note.reason}</p>
            {note.attachment_url && (
                <p className="mb-1">
                    <a className="text-[#7C2D37] underline" href={note.attachment_url}>{t.notes_open_document || 'Open attached document'}</a>
                </p>
            )}
            {note.status === 'submitted' && (
                <form className="mt-3 flex flex-wrap gap-2" onSubmit={(e) => e.preventDefault()}>
                    <input
                        className="form-input"
                        placeholder={t.notes_review_notes || 'Review notes'}
                        aria-label={t.notes_review_notes || 'Review notes'}
                        value={form.data.review_notes}
                        onChange={(e) => form.setData('review_notes', e.target.value)}
                    />
                    <button type="button" className="btn-primary" onClick={() => form.post(`/academics/absence-notes/${note.id}/approve`)}>{t.notes_approve || 'Approve'}</button>
                    <button type="button" className="btn-secondary" onClick={() => form.post(`/academics/absence-notes/${note.id}/reject`)}>{t.notes_reject || 'Reject'}</button>
                    <FormErrors errors={form.errors} />
                </form>
            )}
        </section>
    );
}
