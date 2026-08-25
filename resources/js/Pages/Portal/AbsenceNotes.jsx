import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function AbsenceNotes({ children, notes, types }) {
    const form = useForm({
        student_id: children[0]?.id || '',
        date: '',
        reason: '',
        type: types[0] || 'illness',
        affects_attendance: true,
    });

    return (
        <AppShell title="Absence notes">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/portal/absence-notes', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Child</span>
                    <select className="form-input w-full" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                        {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Date</span>
                    <input className="form-input w-full" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Type</span>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </label>
                <label className="block text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">Reason</span>
                    <textarea className="form-input w-full" rows={3} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                    {form.errors.reason && <span className="text-xs text-red-600">{form.errors.reason}</span>}
                </label>
                <button type="submit" className="btn-primary justify-self-start">Submit note</button>
            </form>

            <ul className="grid gap-2">
                {notes.map((note) => (
                    <li key={note.id} className="rounded-lg border bg-white p-3 text-sm">
                        <span className="uppercase text-xs">{note.status}</span>
                        {' · '}
                        {note.date} · {note.student_name}: {note.reason}
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
