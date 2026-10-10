import { useForm, Link } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * The school's students. Every word is the `people` book's (slice PE1,
 * STATUS §5qp); a pupil's status, a gender and a guardian's relationship are
 * named rather than printed as codes. A class reads with its section — two
 * sections of one grade read alike — and the list can be read for a class,
 * which the server took and the screen never offered.
 */
export default function Index({
    students,
    filters,
    statuses,
    schools = [],
    classes = [],
    guardians = [],
    relationships = [],
    awaitingVerification = 0,
    t = {},
}) {
    const statusName = (status) => t[`student_status_${status}`] || status;
    const relationshipName = (relationship) => t[`relationship_${relationship}`] || relationship;
    const form = useForm({
        search: filters.search || '',
        status: filters.status || '',
        class_id: filters.class_id || '',
        awaiting_verification: filters.awaiting_verification || '',
    });

    const createForm = useForm({
        first_name: '',
        middle_name: '',
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
    const exportQuery = new URLSearchParams({
        search: form.data.search || '',
        status: form.data.status || '',
        class_id: form.data.class_id || '',
    }).toString();

    return (
        <AppShell title={t.students_title || 'Students'}>
            <form onSubmit={apply} className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4">
                <input
                    className="form-input min-w-56"
                    aria-label={t.students_search || 'Search name, ID, student number'}
                    placeholder={t.students_search || 'Search name, ID, student number'}
                    value={form.data.search}
                    onChange={(e) => form.setData('search', e.target.value)}
                />
                <select className="form-input" aria-label={t.status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    <option value="">{t.students_all_statuses || 'All statuses'}</option>
                    {statuses.map((status) => (
                        <option key={status} value={status}>{statusName(status)}</option>
                    ))}
                </select>
                <select className="form-input" aria-label={t.col_class || 'Class'} value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)} data-testid="class-filter">
                    <option value="">{t.students_all_classes || 'All classes'}</option>
                    {classes.map((room) => (
                        <option key={room.id} value={room.id}>{room.label}</option>
                    ))}
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={Boolean(form.data.awaiting_verification)}
                        onChange={(e) => form.setData('awaiting_verification', e.target.checked ? '1' : '')}
                    />
                    {t.students_awaiting || 'Awaiting parent verification'}
                    {awaitingVerification > 0 && (
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900" data-testid="awaiting-verification-count">{awaitingVerification}</span>
                    )}
                </label>
                <button type="submit" className="btn-primary">{t.students_filter || 'Filter'}</button>
                <a className="btn-secondary" href={`/people/students/export?${exportQuery}`}>
                    {t.export_csv || 'Export CSV'}
                </a>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    createForm.post('/people/students');
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <h2 className="md:col-span-3 font-semibold">{t.students_add || 'Add student'}</h2>
                <FormErrors errors={createForm.errors} className="md:col-span-3" />
                <label className="text-xs text-gray-500">
                    {t.first_name || 'First name'}
                    <input
                        className="form-input mt-1 w-full"
                        value={createForm.data.first_name}
                        onChange={(e) => createForm.setData('first_name', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    {t.middle_name || 'Middle name'}
                    <input
                        className="form-input mt-1 w-full"
                        value={createForm.data.middle_name}
                        onChange={(e) => createForm.setData('middle_name', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    {t.last_name || 'Last name'}
                    <input
                        className="form-input mt-1 w-full"
                        value={createForm.data.last_name}
                        onChange={(e) => createForm.setData('last_name', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    {t.date_of_birth || 'Date of birth'}
                    <input
                        type="date"
                        className="form-input mt-1 w-full"
                        value={createForm.data.date_of_birth}
                        onChange={(e) => createForm.setData('date_of_birth', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    {t.gender || 'Gender'}
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.gender}
                        onChange={(e) => createForm.setData('gender', e.target.value)}
                    >
                        <option value="">{t.gender_choose || 'Select'}</option>
                        <option value="female">{t.gender_female || 'Female'}</option>
                        <option value="male">{t.gender_male || 'Male'}</option>
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    {t.student_number_optional || 'Student number (optional)'}
                    <input
                        className="form-input mt-1 w-full"
                        placeholder={t.student_number_hint || 'Leave blank for course-only'}
                        value={createForm.data.student_id}
                        onChange={(e) => createForm.setData('student_id', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    {t.school_optional || 'School (optional)'}
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.school_id}
                        onChange={(e) => createForm.setData('school_id', e.target.value)}
                    >
                        <option value="">{t.none || 'None'}</option>
                        {schools.map((school) => (
                            <option key={school.id} value={school.id}>{school.name}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    {t.class_optional || 'Class (optional)'}
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.class_id}
                        onChange={(e) => createForm.setData('class_id', e.target.value)}
                    >
                        <option value="">{t.none || 'None'}</option>
                        {classes.map((room) => (
                            <option key={room.id} value={room.id}>{room.label}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    {t.admission_date_optional || 'Admission date (optional)'}
                    <input
                        type="date"
                        className="form-input mt-1 w-full"
                        value={createForm.data.admission_date}
                        onChange={(e) => createForm.setData('admission_date', e.target.value)}
                    />
                </label>
                <label className="text-xs text-gray-500">
                    {t.status || 'Status'}
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.status}
                        onChange={(e) => createForm.setData('status', e.target.value)}
                    >
                        {statuses.map((status) => (
                            <option key={status} value={status}>{statusName(status)}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    {t.guardian_optional || 'Guardian (optional)'}
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.guardian_id}
                        onChange={(e) => createForm.setData('guardian_id', e.target.value)}
                    >
                        <option value="">{t.none || 'None'}</option>
                        {guardians.map((guardian) => (
                            <option key={guardian.id} value={guardian.id}>{guardian.name}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-gray-500">
                    {t.relationship || 'Relationship'}
                    <select
                        className="form-input mt-1 w-full"
                        value={createForm.data.guardian_relationship}
                        onChange={(e) => createForm.setData('guardian_relationship', e.target.value)}
                    >
                        {relationships.map((rel) => (
                            <option key={rel} value={rel}>{relationshipName(rel)}</option>
                        ))}
                    </select>
                </label>
                <label className="flex items-end gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={createForm.data.is_primary}
                        onChange={(e) => createForm.setData('is_primary', e.target.checked)}
                    />
                    {t.primary_guardian || 'Primary guardian'}
                </label>
                <div className="md:col-span-3">
                    <button type="submit" className="btn-primary" disabled={createForm.processing}>{t.students_add || 'Add student'}</button>
                </div>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.col_number || 'Number'}</th>
                            <th className="px-3 py-2">{t.col_national_id || 'National ID'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.col_class || 'Class'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {students.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.students_none || 'No student matches.'}</td></tr>
                        )}
                        {students.map((student) => (
                            <tr key={student.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/people/students/${student.id}`} className="text-[#7C2D37] hover:underline">
                                        {[student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ')}
                                    </Link>
                                </td>
                                <td className="px-3 py-2">{student.student_id}</td>
                                <td className="px-3 py-2">{student.national_id}</td>
                                <td className="px-3 py-2">{statusName(student.status)}</td>
                                <td className="px-3 py-2">{[student.class_name, student.class_section].filter(Boolean).join(' ')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
