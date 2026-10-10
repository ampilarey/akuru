import { Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import CustomFields from '../../../Components/CustomFields';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

const tabs = [
    { id: 'overview', key: 'tab_overview', label: 'Overview' },
    { id: 'guardians', key: 'tab_guardians', label: 'Guardians' },
    { id: 'emergency', key: 'tab_emergency', label: 'Emergency contacts' },
    { id: 'documents', key: 'tab_documents', label: 'Documents' },
    { id: 'medical', key: 'tab_medical', label: 'Medical' },
    { id: 'history', key: 'tab_history', label: 'Status history' },
    { id: 'consents', key: 'tab_consents', label: 'Consents' },
    { id: 'behavior', key: 'tab_behavior', label: 'Behavior' },
];

/**
 * SPEC §9 "Parent-Child Relationship" lists nine things the `guardian_student`
 * pivot must support. Four were written from the day the table shipped;
 * **consent status, verification status, `verified_at` and notes were written
 * by nobody**, so every link in the database read "not asked / not checked"
 * with a NULL timestamp from the moment it was created.
 *
 * Verification here is a **record of whether staff have checked this adult is
 * this child's guardian** — not an access gate. `/portal/children` is still
 * scoped to the signed-in guardian's own links and nothing filters on this
 * column; making it a gate would hide every child from every parent overnight,
 * because every existing link is unverified.
 *
 * A refused Save or Detach is said under its row (slice PE1): it was said
 * nowhere.
 */
function GuardianRow({ student, guardian, consentStatuses, verificationStatuses, t, refusals }) {
    const [consent, setConsent] = useState(guardian.consent_status || 'unknown');
    const [verification, setVerification] = useState(guardian.verification_status || 'unverified');
    const [notes, setNotes] = useState(guardian.notes || '');
    const rowKey = `guardian:${guardian.id}`;

    const save = () => refusals.actOn(rowKey, () => router.put(
        `/people/students/${student.id}/guardians/${guardian.guardian_id ?? guardian.id}`,
        { consent_status: consent, verification_status: verification, notes },
        { preserveScroll: true },
    ));
    const flags = [
        guardian.is_primary && (t.flag_primary || 'Primary'),
        guardian.can_pickup && (t.flag_pickup || 'Collects the child'),
        guardian.financial_responsible && (t.flag_financial || 'Pays the fees'),
    ].filter(Boolean);

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">{guardian.name}</td>
            <td className="px-3 py-2">{t[`relationship_${guardian.relationship}`] || guardian.relationship}</td>
            <td className="px-3 py-2 text-xs">{flags.join(t.list_separator || ', ')}</td>
            <td className="px-3 py-2">
                <select className="form-input" value={consent} onChange={(e) => setConsent(e.target.value)} aria-label={t.consent_status_aria || 'Consent status'}>
                    {consentStatuses.map((option) => (
                        <option key={option.value} value={option.value}>{t[`consent_status_${option.value}`] || option.label}</option>
                    ))}
                </select>
            </td>
            <td className="px-3 py-2">
                <select className="form-input" value={verification} onChange={(e) => setVerification(e.target.value)} aria-label={t.verification_status_aria || 'Verification status'}>
                    {verificationStatuses.map((option) => (
                        <option key={option.value} value={option.value}>{t[`verification_status_${option.value}`] || option.label}</option>
                    ))}
                </select>
                {/* §9 pairs the status with `verified_at`, so the date is shown
                    beside it rather than hidden in the database. */}
                {guardian.verified_at && (
                    <p className="mt-1 text-xs text-gray-600">{(t.guardian_checked_on || 'Checked :date').replace(':date', String(guardian.verified_at).slice(0, 10))}</p>
                )}
            </td>
            <td className="px-3 py-2 text-end">
                <input
                    className="form-input mb-2"
                    placeholder={t.guardian_notes || 'Notes'}
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    aria-label={t.guardian_notes_aria || 'Guardian link notes'}
                />
                <button type="button" className="btn-secondary" onClick={save}>{t.save || 'Save'}</button>
                <button
                    type="button"
                    className="ms-3 text-red-700 hover:underline"
                    onClick={() => refusals.actOn(rowKey, () => router.delete(`/people/students/${student.id}/guardians/${guardian.id}`))}
                >
                    {t.guardian_detach || 'Detach'}
                </button>
                <FormErrors errors={refusals.errorsFor(rowKey)} className="mt-2 text-start" />
            </td>
        </tr>
    );
}

export default function Show({
    student,
    tab,
    canViewSensitive,
    customFields,
    guardians,
    emergencyContacts = [],
    availableGuardians,
    relationships,
    consentStatuses = [],
    verificationStatuses = [],
    statusHistory,
    consents,
    consentTypes = [],
    documents,
    behaviorRecords = [],
    statuses = [],
    schools = [],
    classes = [],
    hifzProgressUrl = null,
    t = {},
}) {
    const statusName = (status) => t[`student_status_${status}`] || status;
    const relationshipName = (relationship) => t[`relationship_${relationship}`] || relationship;
    const consentTypeName = (type) => t[`consent_type_${type}`] || type;
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
        middle_name: student.middle_name || '',
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

    const contactForm = useForm({
        name: '',
        phone: '',
        relationship: '',
        priority: 1,
    });
    const refusals = useRowRefusals(fieldForm, editForm, guardianForm, contactForm);

    const saveFields = (e) => {
        e.preventDefault();
        fieldForm.transform(() => ({ values }));
        fieldForm.put(`/people/students/${student.id}/custom-fields`);
    };

    return (
        <AppShell title={[student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ')}>
            <p className="mb-4 text-sm text-gray-600">
                {student.student_id || t.no_student_number || 'No student number'} · {statusName(student.status)}
            </p>
            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((item) => (
                    <Link
                        key={item.id}
                        href={`/people/students/${student.id}?tab=${item.id}`}
                        className={`rounded px-3 py-1 text-sm ${tab === item.id ? 'bg-[#7C2D37] text-white' : 'bg-white border'}`}
                    >
                        {t[item.key] || item.label}
                    </Link>
                ))}
                {/* The retired Blade student record carried a Hifz progress
                    tab. It is still a Blade screen (module data, no React
                    equivalent yet), so it is a plain link rather than a tab,
                    and it is absent when the Qur'an module is switched off. */}
                {hifzProgressUrl && (
                    <a href={hifzProgressUrl} className="rounded border bg-white px-3 py-1 text-sm">
                        {t.quran_progress || 'Qur’an progress'}
                    </a>
                )}
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            {tab === 'overview' && (
                <div className="grid gap-6 md:grid-cols-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            editForm.put(`/people/students/${student.id}`);
                        }}
                        className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
                    >
                        <h2 className="md:col-span-2 font-semibold">{t.profile_title || 'Profile'}</h2>
                        <FormErrors errors={editForm.errors} className="md:col-span-2" />
                        <label className="text-xs text-gray-500">
                            {t.first_name || 'First name'}
                            <input className="form-input mt-1 w-full" value={editForm.data.first_name} onChange={(e) => editForm.setData('first_name', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.middle_name || 'Middle name'}
                            <input className="form-input mt-1 w-full" value={editForm.data.middle_name} onChange={(e) => editForm.setData('middle_name', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.last_name || 'Last name'}
                            <input className="form-input mt-1 w-full" value={editForm.data.last_name} onChange={(e) => editForm.setData('last_name', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.first_name_dhivehi || 'Dhivehi first'}
                            <input className="form-input mt-1 w-full" value={editForm.data.first_name_dhivehi} onChange={(e) => editForm.setData('first_name_dhivehi', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.last_name_dhivehi || 'Dhivehi last'}
                            <input className="form-input mt-1 w-full" value={editForm.data.last_name_dhivehi} onChange={(e) => editForm.setData('last_name_dhivehi', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.first_name_arabic || 'Arabic first'}
                            <input className="form-input mt-1 w-full" value={editForm.data.first_name_arabic} onChange={(e) => editForm.setData('first_name_arabic', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.last_name_arabic || 'Arabic last'}
                            <input className="form-input mt-1 w-full" value={editForm.data.last_name_arabic} onChange={(e) => editForm.setData('last_name_arabic', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.date_of_birth || 'Date of birth'}
                            <input type="date" className="form-input mt-1 w-full" value={editForm.data.date_of_birth} onChange={(e) => editForm.setData('date_of_birth', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.gender || 'Gender'}
                            {/* A student registered without a gender has none on file (STATUS §5jp); the empty option says so rather than showing the first one. */}
                            <select className="form-input mt-1 w-full" value={editForm.data.gender} onChange={(e) => editForm.setData('gender', e.target.value)} data-testid="student-gender">
                                <option value="">—</option>
                                <option value="female">{t.gender_female || 'Female'}</option>
                                <option value="male">{t.gender_male || 'Male'}</option>
                            </select>
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.student_number || 'Student number'}
                            <input className="form-input mt-1 w-full" value={editForm.data.student_id} onChange={(e) => editForm.setData('student_id', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.col_national_id || 'National ID'}
                            <input className="form-input mt-1 w-full" value={editForm.data.national_id} onChange={(e) => editForm.setData('national_id', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.school || 'School'}
                            <select className="form-input mt-1 w-full" value={editForm.data.school_id} onChange={(e) => editForm.setData('school_id', e.target.value)}>
                                <option value="">{t.none || 'None'}</option>
                                {schools.map((school) => (
                                    <option key={school.id} value={school.id}>{school.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.col_class || 'Class'}
                            <select className="form-input mt-1 w-full" value={editForm.data.class_id} onChange={(e) => editForm.setData('class_id', e.target.value)}>
                                <option value="">{t.none || 'None'}</option>
                                {classes.map((room) => (
                                    <option key={room.id} value={room.id}>{room.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.admission_date || 'Admission date'}
                            <input type="date" className="form-input mt-1 w-full" value={editForm.data.admission_date} onChange={(e) => editForm.setData('admission_date', e.target.value)} />
                        </label>
                        <label className="text-xs text-gray-500">
                            {t.status || 'Status'}
                            <select className="form-input mt-1 w-full" value={editForm.data.status} onChange={(e) => editForm.setData('status', e.target.value)}>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>{statusName(status)}</option>
                                ))}
                            </select>
                        </label>
                        <div className="md:col-span-2">
                            <button type="submit" className="btn-primary" disabled={editForm.processing}>{t.save_profile || 'Save profile'}</button>
                        </div>
                    </form>
                    <form onSubmit={saveFields} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-3 font-semibold">{t.custom_fields || 'Custom fields'}</h2>
                        <CustomFields
                            fields={customFields}
                            values={values}
                            errors={fieldForm.errors}
                            onChange={(id, value) => setValues((current) => ({ ...current, [id]: value }))}
                        />
                        <button type="submit" className="btn-primary mt-4" disabled={fieldForm.processing}>{t.save_fields || 'Save fields'}</button>
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
                        className="flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"
                    >
                        {availableGuardians.length === 0 ? (
                            <p className="text-sm text-gray-600" data-testid="no-guardian-to-link">{t.guardians_none_to_link || 'No other guardian is on file to link.'}</p>
                        ) : (
                            <>
                                <select className="form-input" aria-label={t.guardian || 'Guardian'} value={guardianForm.data.guardian_id} onChange={(e) => guardianForm.setData('guardian_id', e.target.value)}>
                                    {availableGuardians.map((guardian) => (
                                        <option key={guardian.id} value={guardian.id}>{guardian.name}</option>
                                    ))}
                                </select>
                                <select className="form-input" aria-label={t.relationship || 'Relationship'} value={guardianForm.data.relationship} onChange={(e) => guardianForm.setData('relationship', e.target.value)}>
                                    {relationships.map((rel) => (
                                        <option key={rel} value={rel}>{relationshipName(rel)}</option>
                                    ))}
                                </select>
                                <label className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" checked={guardianForm.data.is_primary} onChange={(e) => guardianForm.setData('is_primary', e.target.checked)} />
                                    {t.flag_primary || 'Primary'}
                                </label>
                                <button type="submit" className="btn-primary" disabled={guardianForm.processing}>{t.guardian_attach || 'Attach'}</button>
                            </>
                        )}
                        <FormErrors errors={guardianForm.errors} className="w-full" />
                    </form>
                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                                    <th className="px-3 py-2">{t.relationship || 'Relationship'}</th>
                                    <th className="px-3 py-2">{t.col_responsibilities || 'Responsibilities'}</th>
                                    <th className="px-3 py-2">{t.col_consent || 'Consent'}</th>
                                    <th className="px-3 py-2">{t.col_verification || 'Verification'}</th>
                                    <th className="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {guardians.length === 0 && (
                                    <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.guardians_none || 'No guardian is linked to this pupil.'}</td></tr>
                                )}
                                {guardians.map((guardian) => (
                                    <GuardianRow
                                        key={guardian.id}
                                        student={student}
                                        guardian={guardian}
                                        consentStatuses={consentStatuses}
                                        verificationStatuses={verificationStatuses}
                                        t={t}
                                        refusals={refusals}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {tab === 'emergency' && (
                <section className="grid gap-4">
                    <p className="text-sm text-gray-600">
                        {t.emergency_intro || 'Who to ring when this child is hurt or unwell. Priority 1 is called first, and these numbers appear beside an unexplained absence.'}
                    </p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            contactForm.post(`/people/students/${student.id}/emergency-contacts`, {
                                preserveScroll: true,
                                onSuccess: () => contactForm.reset(),
                            });
                        }}
                        className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
                    >
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">{t.col_name || 'Name'}</span>
                            <input className="form-input w-full" value={contactForm.data.name} onChange={(e) => contactForm.setData('name', e.target.value)} />
                            {contactForm.errors.name && <span className="text-xs text-red-600">{contactForm.errors.name}</span>}
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">{t.phone || 'Phone'}</span>
                            <input className="form-input w-full" value={contactForm.data.phone} onChange={(e) => contactForm.setData('phone', e.target.value)} />
                            {contactForm.errors.phone && <span className="text-xs text-red-600">{contactForm.errors.phone}</span>}
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">{t.relationship || 'Relationship'}</span>
                            <input className="form-input w-full" value={contactForm.data.relationship} onChange={(e) => contactForm.setData('relationship', e.target.value)} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">{t.priority || 'Priority'}</span>
                            <input type="number" min="1" className="form-input w-full" value={contactForm.data.priority} onChange={(e) => contactForm.setData('priority', e.target.value)} />
                        </label>
                        <div className="flex items-end">
                            <button type="submit" className="btn-primary" disabled={contactForm.processing}>{t.contact_add || 'Add contact'}</button>
                        </div>
                        <FormErrors errors={contactForm.errors} except={['name', 'phone']} className="md:col-span-5" />
                    </form>
                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">#</th>
                                    <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                                    <th className="px-3 py-2">{t.phone || 'Phone'}</th>
                                    <th className="px-3 py-2">{t.relationship || 'Relationship'}</th>
                                    <th className="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {emergencyContacts.length === 0 && (
                                    <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>
                                        {t.contacts_none || 'No emergency contact on file. If this child is hurt, nobody knows who to ring.'}
                                    </td></tr>
                                )}
                                {emergencyContacts.map((contact) => (
                                    <tr key={contact.id} className="border-t align-top">
                                        <td className="px-3 py-2">{contact.priority}</td>
                                        <td className="px-3 py-2">{contact.name}</td>
                                        <td className="px-3 py-2">
                                            <a className="text-[#7C2D37] underline" href={`tel:${contact.phone}`}>{contact.phone}</a>
                                        </td>
                                        <td className="px-3 py-2">{contact.relationship || '—'}</td>
                                        <td className="px-3 py-2 text-end">
                                            <button
                                                type="button"
                                                className="text-red-700 hover:underline"
                                                onClick={() => refusals.actOn(`contact:${contact.id}`, () => router.delete(`/people/students/${student.id}/emergency-contacts/${contact.id}`, { preserveScroll: true }))}
                                            >
                                                {t.remove || 'Remove'}
                                            </button>
                                            <FormErrors errors={refusals.errorsFor(`contact:${contact.id}`)} className="mt-1 text-start" />
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
                    {documents.length === 0
                        ? (t.documents_none || 'No documents uploaded yet.')
                        : (t.documents_count || 'Documents: :count').replace(':count', documents.length)}
                </p>
            )}

            {tab === 'medical' && (
                canViewSensitive ? (
                    <section className="rounded-lg border bg-white p-4 text-sm">
                        <p><strong>{t.medical_conditions || 'Conditions'}:</strong> {student.medical?.medical_conditions || '—'}</p>
                        <p><strong>{t.medical_allergies || 'Allergies'}:</strong> {student.medical?.allergies || '—'}</p>
                        <p><strong>{t.medical_doctor || 'Doctor'}:</strong> {student.medical?.doctor_name || '—'} {student.medical?.doctor_phone}</p>
                    </section>
                ) : (
                    <p className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        {t.medical_forbidden || 'You do not have permission to view medical information.'}
                    </p>
                )
            )}

            {tab === 'history' && (
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.history_from || 'From'}</th>
                                <th className="px-3 py-2">{t.history_to || 'To'}</th>
                                <th className="px-3 py-2">{t.history_reason || 'Reason'}</th>
                                <th className="px-3 py-2">{t.col_date || 'Date'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {statusHistory.length === 0 && (
                                <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.history_none || 'No status change is recorded.'}</td></tr>
                            )}
                            {statusHistory.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">{row.from_status ? statusName(row.from_status) : '—'}</td>
                                    <td className="px-3 py-2">{statusName(row.to_status)}</td>
                                    <td className="px-3 py-2">{(row.reason_key && t[row.reason_key]) || row.reason}</td>
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
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.col_date || 'Date'}</th>
                                <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                                <th className="px-3 py-2">{t.behavior_category || 'Category'}</th>
                                <th className="px-3 py-2">{t.behavior_description || 'Description'}</th>
                                <th className="px-3 py-2">{t.behavior_visible || 'Visible'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {behaviorRecords.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">{row.date}</td>
                                    <td className="px-3 py-2">{t[`behavior_type_${row.type}`] || row.type}</td>
                                    <td className="px-3 py-2">{t[`behavior_category_${row.category}`] || row.category}</td>
                                    <td className="px-3 py-2">{row.description}</td>
                                    <td className="px-3 py-2">{row.parent_visible ? (t.yes || 'yes') : (t.no || 'no')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {behaviorRecords.length === 0 && <p className="p-4 text-sm text-gray-600">{t.behavior_none || 'No behavior records.'}</p>}
                </section>
            )}

            {tab === 'consents' && (
                <section className="grid gap-4">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.currentTarget;
                            refusals.actOn('consent', () => router.post(`/people/students/${student.id}/consents`, {
                                consent_type: form.consent_type.value,
                                granted: form.granted.value === '1',
                            }));
                        }}
                        className="flex flex-wrap gap-3 rounded-lg border bg-white p-4"
                    >
                        <select name="consent_type" className="form-input" aria-label={t.col_consent || 'Consent'}>
                            {consentTypes.map((type) => (
                                <option key={type} value={type}>{consentTypeName(type)}</option>
                            ))}
                        </select>
                        <select name="granted" className="form-input" aria-label={t.consent_answer || 'Answer'}>
                            <option value="1">{t.consent_grant || 'Grant'}</option>
                            <option value="0">{t.consent_revoke || 'Revoke'}</option>
                        </select>
                        <button type="submit" className="btn-primary">{t.consent_record || 'Record'}</button>
                        <FormErrors errors={refusals.errorsFor('consent')} className="w-full" />
                    </form>
                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                                    <th className="px-3 py-2">{t.consent_answer || 'Answer'}</th>
                                    <th className="px-3 py-2">{t.col_source || 'Source'}</th>
                                    <th className="px-3 py-2">{t.col_at || 'At'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {consents.length === 0 && (
                                    <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.consents_none || 'No consent is recorded.'}</td></tr>
                                )}
                                {consents.map((row) => (
                                    <tr key={row.id} className="border-t" data-consent-type={row.consent_type}>
                                        <td className="px-3 py-2">{consentTypeName(row.consent_type)}</td>
                                        <td className="px-3 py-2">{row.granted ? (t.consent_granted || 'granted') : (t.consent_revoked || 'revoked')}</td>
                                        <td className="px-3 py-2">{t[`consent_source_${row.source}`] || row.source}</td>
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
