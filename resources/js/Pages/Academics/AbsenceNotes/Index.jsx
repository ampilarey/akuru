import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ status, statuses, notes }) {
    return (
        <AppShell title="Absence notes">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select className="form-input" value={status || ''} onChange={(e) => router.get(`/academics/absence-notes?status=${e.target.value}`)}>
                    <option value="">All</option>
                    {statuses.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/absence-notes/export?status=${status || ''}`}>Export CSV</a>
            </div>
            <div className="grid gap-3">
                {notes.map((note) => <NoteCard key={note.id} note={note} />)}
                {notes.length === 0 && <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">No notes.</p>}
            </div>
        </AppShell>
    );
}

function NoteCard({ note }) {
    const form = useForm({ review_notes: '' });

    return (
        <section className="rounded-lg border bg-white p-4 text-sm">
            <div className="mb-2 flex flex-wrap justify-between gap-2">
                <p className="font-semibold">{note.student_name} · {note.date}</p>
                <span className="uppercase text-xs">{note.status}</span>
            </div>
            <p className="mb-1">{note.type}: {note.reason}</p>
            {note.status === 'submitted' && (
                <form className="mt-3 flex flex-wrap gap-2" onSubmit={(e) => e.preventDefault()}>
                    <input className="form-input" placeholder="Review notes" value={form.data.review_notes} onChange={(e) => form.setData('review_notes', e.target.value)} />
                    <button type="button" className="btn-primary" onClick={() => form.post(`/academics/absence-notes/${note.id}/approve`)}>Approve</button>
                    <button type="button" className="btn-secondary" onClick={() => form.post(`/academics/absence-notes/${note.id}/reject`)}>Reject</button>
                </form>
            )}
        </section>
    );
}
