import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({ classRoom, roster }) {
    const form = useForm({ student_id: '' });

    return (
        <AppShell title={`${classRoom.name} ${classRoom.section || ''}`}>
            <p className="mb-3 text-sm text-gray-700">Class teacher: {classRoom.class_teacher_name || 'None'}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/academics/classes/${classRoom.id}/assign`);
                }}
                className="mb-4 flex gap-3 rounded-lg border bg-white p-4"
            >
                <input className="form-input" placeholder="Student ID" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)} />
                <button type="submit" className="btn-primary">Add to roster</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {roster.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.student_number}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
