import { Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import CustomFields from '../../../Components/CustomFields';

const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'guardians', label: 'Guardians' },
    { id: 'documents', label: 'Documents' },
    { id: 'medical', label: 'Medical' },
    { id: 'history', label: 'Status history' },
    { id: 'consents', label: 'Consents' },
    { id: 'behavior', label: 'Behavior' },
];

export default function Show({
    student,
    tab,
    canViewSensitive,
    customFields,
    guardians,
    availableGuardians,
    relationships,
    statusHistory,
    consents,
    consentTypes = [],
    documents,
    behaviorRecords = [],
    statuses = [],
    schools = [],
    classes = [],
}) {
    const initialValues = useMemo(() => {
        const next = {};
        customFields.forEach((field) => {
            next[field.id] = field.value ?? (field.field_type === 'multiselect' ? [] : field.field_type === 'boolean' ? false : '');
        });
        return next;
    }, [customFields]);

    const [values, setValues] = useState(initialValues);
    const fieldForm = useForm({ values });
    const editForm = useForm({
        first_name: student.first_name || '',
        last_name: student.last_name || '',
        first_name_dhivehi: student.first_name_dhivehi || '',
        last_name_dhivehi: student.last_name_dhivehi || '',
        first_name_arabic: student.first_name_arabic || '',
        last_name_arabic: student.last_name_arabic || '',
        date_of_birth: student.date_of_birth || '',
        gender: student.gender || '',
        national_id: student.national_id || '',
        student_id: student.student_id || '',
        school_id: student.school_id || '',
        class_id: student.class_id || '',
        admission_date: student.admission_date || '',
        status: student.status || 'active',
        place_of_birth: student.place_of_birth || '',
        phone: student.phone || '',
        address: student.address || '',
    });
    const guardianForm = useForm({
        guardian_id: availableGuardians[0]?.id || '',
        relationship: relationships[0] || 'guardian',
        is_primary: false,
        can_pickup: true,
        financial_responsible: false,
    });

    const saveFields = (e) => {
        e.preventDefault();
        fieldForm.transform(() => ({ values })).put(`/people/students/${student.id}/custom-fields`);
    };

    return (
        <AppShell title={`${student.first_name} ${student.last_name}`}>
            <p className="mb-4 text-sm text-gray-600">
                {student.student_id || 'No student number'} · {student.status}
            </p>
            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((item) => (
                    <Link
                        key={item.id}
                        href={`/people/students/${student.id}?tab=${item.id}`}
                        className={`rounded px-3 py-1 text-sm ${tab === item.id ? 'bg-[#7C2D37] text-white' : 'bg-white border'}`}
                    >
                        {item.label}
                    </Link>
                ))}
            </div>

            {tab === 'overview' && (
                <div className="grid gap-6 md:grid-cols-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            editForm.put(`/people/students/${student.id}`);
                        }}
                        className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
                    >
                        <h2 className="md:col-span-2 font-semibold">Profile</h2>
                        {Object.keys(editForm.errors).length > 0 && (
                            <p className="md:col-span-2 text-sm text-red-600">{Object.values(editForm.errors).join(' ')}</p>
                        )}
                        <label className="text-xs text-gray-500">
                            First name
                            <input className="form-input mt-1 w-full" value={editForm.data.first_name} onChange={(e) => editForm.setData('first_name', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Last name
                            <input className="form-input mt-1 w-full" value={editForm.data.last_name} onChange={(e) => editForm.setData('last_name', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Dhivehi first
                            <input className="form-input mt-1 w-full" value={editForm.data.first_name_dhivehi} onChange={(e) => editForm.setData('first_name_dhivehi', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Dhivehi last
                            <input className="form-input mt-1 w-full" value={editForm.data.last_name_dhivehi} onChange={(e) => editForm.setData('last_name_dhivehi', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Arabic first
                            <input className="form-input mt-1 w-full" value={editForm.data.first_name_arabic} onChange={(e) => editForm.setData('first_name_arabic', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Arabic last
                            <input className="form-input mt-1 w-full" value={editForm.data.last_name_arabic} onChange={(e) => editForm.setData('last_name_arabic', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Date of birth
                            <input type="date" className="form-input mt-1 w-full" value={editForm.data.date_of_birth} onChange={(e) => editForm.setData('date_of_birth', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Gender
                            <select className="form-input mt-1 w-full" value={editForm.data.gender} onChange={(e) => editForm.setData('gender', e.target.value)}>
                                <option value="female">female</option>
                                <option value="male">male</option>
                            </select>
                        </label>
                        <label className="text-xs text-gray-500">
                            Student number
                            <input className="form-input mt-1 w-full" value={editForm.data.student_id} onChange={(e) => editForm.setData('student_id', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            National ID
                            <input className="form-input mt-1 w-full" value={editForm.data.national_id} onChange={(e) => editForm.setData('national_id', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            School
                            <select className="form-input mt-1 w-full" value={editForm.data.school_id} onChange={(e) => editForm.setData('school_id', e.target.value)}>
                                <option value="">None</option>
                                {schools.map((school) => (
                                    <option key={school.id} value={school.id}>{school.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs text-gray-500">
                            Class
                            <select className="form-input mt-1 w-full" value={editForm.data.class_id} onChange={(e) => editForm.setData('class_id', e.target.value)}>
                                <option value="">None</option>
                                {classes.map((room) => (
                                    <option key={room.id} value={room.id}>{room.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs text-gray-500">
                            Admission date
                            <input type="date" className="form-input mt-1 w-full" value={editForm.data.admission_date} onChange={(e) => editForm.setData('admission_date', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            Status
                            <select className="form-input mt-1 w-full" value={editForm.data.status} onChange={(e) => editForm.setData('status', e.target.value)}>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>{status}</option>
                                ))}
                            </select>
                        </label>
                        <div className="md:col-span-2">
                            <button type="submit" className="btn-primary" disabled={editForm.processing}>Save profile</button>
                        </div>
                    </form>
                    <form onSubmit={saveFields} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-3 font-semibold">Custom fields</h2>
                        <CustomFields
                            fields={customFields}
                            values={values}
                            errors={fieldForm.errors}
                            onChange={(id, value) => setValues((current) => ({ ...current, [id]: value }))}
                        />
                        <button type="submit" className="btn-primary mt-4" disabled={fieldForm.processing}>Save fields</button>
                    </form>
                </div>
            )}

            {tab === 'guardians' && (
                <section className="grid gap-4">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            guardianForm.post(`/people/students/${student.id}/guardians`);
                        }}
                        className="flex flex-wrap gap-3 rounded-lg border bg-white p-4"
                    >
                        <select className="form-input" value={guardianForm.data.guardian_id} onChange={(e) => guardianForm.setData('guardian_id', e.target.value)}>
                            {availableGuardians.map((guardian) => (
                                <option key={guardian.id} value={guardian.id}>{guardian.name}</option>
                            ))}
                        </select>
                        <select className="form-input" value={guardianForm.data.relationship} onChange={(e) => guardianForm.setData('relationship', e.target.value)}>
                            {relationships.map((rel) => (
                                <option key={rel} value={rel}>{rel}</option>
                            ))}
                        </select>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={guardianForm.data.is_primary} onChange={(e) => guardianForm.setData('is_primary', e.target.checked)} />
                            Primary
                        </label>
                        <button type="submit" className="btn-primary">Attach</button>
                    </form>
                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-left">
                                <tr>
                                    <th className="px-3 py-2">Name</th>
                                    <th className="px-3 py-2">Relationship</th>
                                    <th className="px-3 py-2">Flags</th>
                                    <th className="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {guardians.map((guardian) => (
                                    <tr key={guardian.id} className="border-t">
                                        <td className="px-3 py-2">{guardian.name}</td>
                                        <td className="px-3 py-2">{guardian.relationship}</td>
                                        <td className="px-3 py-2 text-xs">
                                            {guardian.is_primary ? 'primary ' : ''}
                                            {guardian.can_pickup ? 'pickup ' : ''}
                                            {guardian.financial_responsible ? 'financial' : ''}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <button
                                                type="button"
                                                className="text-red-700 hover:underline"
                                                onClick={() => router.delete(`/people/students/${student.id}/guardians/${guardian.id}`)}
                                            >
                                                Detach
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {tab === 'documents' && (
                <p className="rounded border bg-white p-4 text-sm text-gray-500">
                    {documents.length === 0 ? 'No documents uploaded yet.' : `${documents.length} document(s).`}
                </p>
            )}

            {tab === 'medical' && (
                canViewSensitive ? (
                    <section className="rounded-lg border bg-white p-4 text-sm">
                        <p><strong>Conditions:</strong> {student.medical?.medical_conditions || '—'}</p>
                        <p><strong>Allergies:</strong> {student.medical?.allergies || '—'}</p>
                        <p><strong>Doctor:</strong> {student.medical?.doctor_name || '—'} {student.medical?.doctor_phone}</p>
                    </section>
                ) : (
                    <p className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        You do not have permission to view medical information.
                    </p>
                )
            )}

            {tab === 'history' && (
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-left">
                            <tr>
                                <th className="px-3 py-2">From</th>
                                <th className="px-3 py-2">To</th>
                                <th className="px-3 py-2">Reason</th>
                                <th className="px-3 py-2">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {statusHistory.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">{row.from_status}</td>
                                    <td className="px-3 py-2">{row.to_status}</td>
                                    <td className="px-3 py-2">{row.reason}</td>
                                    <td className="px-3 py-2">{row.effective_date}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {tab === 'behavior' && (
                <section className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-left">
                            <tr>
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Type</th>
                                <th className="px-3 py-2">Category</th>
                                <th className="px-3 py-2">Description</th>
                                <th className="px-3 py-2">Visible</th>
                            </tr>
                        </thead>
                        <tbody>
                            {behaviorRecords.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">{row.date}</td>
                                    <td className="px-3 py-2">{row.type}</td>
                                    <td className="px-3 py-2">{row.category}</td>
                                    <td className="px-3 py-2">{row.description}</td>
                                    <td className="px-3 py-2">{row.parent_visible ? 'yes' : 'no'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {behaviorRecords.length === 0 && <p className="p-4 text-sm text-gray-600">No behavior records.</p>}
                </section>
            )}

            {tab === 'consents' && (
                <section className="grid gap-4">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.currentTarget;
                            router.post(`/people/students/${student.id}/consents`, {
                                consent_type: form.consent_type.value,
                                granted: form.granted.value === '1',
                            });
                        }}
                        className="flex flex-wrap gap-3 rounded-lg border bg-white p-4"
                    >
                        <select name="consent_type" className="form-input">
                            {consentTypes.map((type) => (
                                <option key={type} value={type}>{type}</option>
                            ))}
                        </select>
                        <select name="granted" className="form-input">
                            <option value="1">Grant</option>
                            <option value="0">Revoke</option>
                        </select>
                        <button type="submit" className="btn-primary">Record</button>
                    </form>
                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-left">
                                <tr>
                                    <th className="px-3 py-2">Type</th>
                                    <th className="px-3 py-2">Granted</th>
                                    <th className="px-3 py-2">Source</th>
                                    <th className="px-3 py-2">At</th>
                                </tr>
                            </thead>
                            <tbody>
                                {consents.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-3 py-2">{row.consent_type}</td>
                                        <td className="px-3 py-2">{row.granted ? 'yes' : 'revoked'}</td>
                                        <td className="px-3 py-2">{row.source}</td>
                                        <td className="px-3 py-2">{row.granted_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </AppShell>
    );
}
