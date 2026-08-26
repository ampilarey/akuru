import { useForm, Link } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({
    students,
    filters,
    statuses,
    schools = [],
    classes = [],
    guardians = [],
    relationships = [],
}) {
    const form = useForm({
        search: filters.search || '',
        status: filters.status || '',
        class_id: filters.class_id || '',
    });

    const createForm = useForm({
        first_name: '',
        last_name: '',
        date_of_birth: '',
        gender: '',
        student_id: '',
        school_id: '',
        class_id: '',
        admission_date: '',
        status: 'active',
        guardian_id: '',
        guardian_relationship: relationships[0] || 'guardian',
        is_primary: true,
        can_pickup: true,
        financial_responsible: false,
    });

    const apply = (e) => {
        e.preventDefault();
        form.get('/people/students', { preserveState: true });
    };

    return (
        <AppShell title="Students">
            <form onSubmit={apply} className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4">
                <input
                    className="form-input min-w-56"
                    placeholder="Search name, ID, student number"
                    value={form.data.search}
                    onChange={(e) => form.setData('search', e.target.value)}
                />
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    <option value="">All statuses</option>
                    {statuses.map((status) => (
                        <option key={status} value={status}>{status}</option>
                    ))}
                </select>
                <button type="submit" className="btn-primary">Filter</button>
                <a
                    className="btn-secondary"
                    href={`/people/students/export?search=${encodeURIComponent(form.data.search || '')}&status=${encodeURIComponent(form.data.status || '')}`}
                >
                    Export CSV
                </a>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    createForm.post('/people/students');
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <h2 className="md:col-span-3 font-semibold">Add student</h2>
                {Object.keys(createForm.errors).length > 0 && (
                    <p className="md:col-span-3 text-sm text-red-600">{Object.values(createForm.errors).join(' ')}</p>
                )}
                <label className="text-xs text-gray-500">
                    First name
                    <input
                        className="form-input mt-1 w-full"
                        value={createForm.data.first_name}
                        onChange={(e) => createForm.setData('first_name', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    Last name
                    <input
                        className="form-input mt-1 w-full"
                        value={createForm.data.last_name}
                        onChange={(e) => createForm.setData('last_name', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    Date of birth
                    <input
                        type="date"
                        className="form-input mt-1 w-full"
                        value={createForm.data.date_of_birth}
                        onChange={(e) => createForm.setData('date_of_birth', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    Gender
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.gender}
                        onChange={(e) => createForm.setData('gender', e.target.value)}
                    >
                        <option value="">Select</option>
                        <option value="female">female</option>
                        <option value="male">male</option>
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    Student number (optional)
                    <input
                        className="form-input mt-1 w-full"
                        placeholder="Leave blank for course-only"
                        value={createForm.data.student_id}
                        onChange={(e) => createForm.setData('student_id', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    School (optional)
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.school_id}
                        onChange={(e) => createForm.setData('school_id', e.target.value)}
                    >
                        <option value="">None</option>
                        {schools.map((school) => (
                            <option key={school.id} value={school.id}>{school.name}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    Class (optional)
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.class_id}
                        onChange={(e) => createForm.setData('class_id', e.target.value)}
                    >
                        <option value="">None</option>
                        {classes.map((room) => (
                            <option key={room.id} value={room.id}>{room.label}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    Admission date (optional)
                    <input
                        type="date"
                        className="form-input mt-1 w-full"
                        value={createForm.data.admission_date}
                        onChange={(e) => createForm.setData('admission_date', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    Status
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.status}
                        onChange={(e) => createForm.setData('status', e.target.value)}
                    >
                        {statuses.map((status) => (
                            <option key={status} value={status}>{status}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    Guardian (optional)
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.guardian_id}
                        onChange={(e) => createForm.setData('guardian_id', e.target.value)}
                    >
                        <option value="">None</option>
                        {guardians.map((guardian) => (
                            <option key={guardian.id} value={guardian.id}>{guardian.name}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    Relationship
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.guardian_relationship}
                        onChange={(e) => createForm.setData('guardian_relationship', e.target.value)}
                    >
                        {relationships.map((rel) => (
                            <option key={rel} value={rel}>{rel}</option>
                        ))}
                    </select>
                </label>
                <label className="flex items-end gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={createForm.data.is_primary}
                        onChange={(e) => createForm.setData('is_primary', e.target.checked)}
                    />
                    Primary guardian
                </label>
                <div className="md:col-span-3">
                    <button type="submit" className="btn-primary" disabled={createForm.processing}>Add student</button>
                </div>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">National ID</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        {students.map((student) => (
                            <tr key={student.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/people/students/${student.id}`} className="text-[#7C2D37] hover:underline">
                                        {student.first_name} {student.last_name}
                                    </Link>
                                </td>
                                <td className="px-3 py-2">{student.student_id}</td>
                                <td className="px-3 py-2">{student.national_id}</td>
                                <td className="px-3 py-2">{student.status}</td>
                                <td className="px-3 py-2">{student.class_name}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
