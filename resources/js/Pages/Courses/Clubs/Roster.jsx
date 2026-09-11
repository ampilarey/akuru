import { Link, router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Roster({ club, members = [] }) {
    const form = useForm({ student_id: '' });
    const offRoll = members.filter((m) => !m.on_roll).length;

    return (
        <AppShell title={`${club.title} — roster`}>
            <p className="mb-4 text-sm text-gray-600">
                <Link href="/academics/clubs" className="text-[#7C2D37] underline">Clubs</Link>
                {' · '}
                {members.length} member{members.length === 1 ? '' : 's'}
                {offRoll > 0 && ` · ${offRoll} from outside the roll`}
                {' · '}
                <Link href={`/academics/clubs/${club.id}/attendance-sheet`} className="text-[#7C2D37] underline">
                    Attendance sheet
                </Link>
                {' · '}
                <a href={`/academics/clubs/${club.id}/export`} className="text-[#7C2D37] underline">CSV</a>
            </p>

            <form
                className="mb-6 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/academics/clubs/${club.id}/members`, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Add a member by student id</span>
                    <input className="form-input w-48" value={form.data.student_id}
                        onChange={(e) => form.setData('student_id', e.target.value)} />
                    {form.errors.student_id && (
                        <span className="mt-1 block text-xs text-red-600">{form.errors.student_id}</span>
                    )}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Add</button>
                <p className="w-full text-xs text-gray-500">
                    A club member does not have to be on a class roster — siblings and children of
                    staff are welcome, and the register marks them as visitors.
                </p>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">Member</th>
                            <th className="px-3 py-2 text-start">Number</th>
                            <th className="px-3 py-2 text-start">On the roll</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => (
                            <tr key={member.enrollment_id} className="border-t">
                                <td className="px-3 py-2">{member.name}</td>
                                <td className="px-3 py-2">{member.student_number || '—'}</td>
                                <td className="px-3 py-2">
                                    {member.on_roll
                                        ? <span className="text-xs text-gray-600">Pupil</span>
                                        : <span className="rounded bg-amber-50 px-1 text-xs text-amber-800">Visitor</span>}
                                </td>
                                <td className="px-3 py-2">
                                    <button
                                        className="text-xs text-[#7C2D37] underline"
                                        onClick={() => router.delete(`/academics/clubs/${club.id}/members/${member.enrollment_id}`, { preserveScroll: true })}
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {members.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">Nobody has joined yet.</p>
                )}
            </div>
        </AppShell>
    );
}
