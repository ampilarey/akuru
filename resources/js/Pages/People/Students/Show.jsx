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
    documents,
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
                    <section className="rounded-lg border bg-white p-4 text-sm">
                        <h2 className="mb-3 font-semibold">Profile</h2>
                        <dl className="grid grid-cols-2 gap-2">
                            <dt className="text-gray-500">English</dt>
                            <dd>{student.first_name} {student.last_name}</dd>
                            <dt className="text-gray-500">Dhivehi</dt>
                            <dd>{student.first_name_dhivehi} {student.last_name_dhivehi}</dd>
                            <dt className="text-gray-500">Arabic</dt>
                            <dd>{student.first_name_arabic} {student.last_name_arabic}</dd>
                            <dt className="text-gray-500">Date of birth</dt>
                            <dd>{student.date_of_birth}</dd>
                            <dt className="text-gray-500">National ID</dt>
                            <dd>{student.national_id}</dd>
                        </dl>
                    </section>
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

            {tab === 'consents' && (
                <p className="rounded border bg-white p-4 text-sm text-gray-500">
                    {consents.length === 0 ? 'No consent records yet.' : `${consents.length} consent record(s).`}
                </p>
            )}
        </AppShell>
    );
}
