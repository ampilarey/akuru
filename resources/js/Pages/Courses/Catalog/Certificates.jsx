import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const EMPTY_TEMPLATE = {
    name: '',
    name_dv: '',
    name_ar: '',
    kind: 'course_completion',
    course_id: '',
    body_html: 'This certifies that <strong>{{student_name}}</strong> completed {{course_name}}.',
    active: true,
    rules: {
        min_progress_percent: '',
        min_attendance_percent: '',
        min_score: '',
        require_final_assessment: false,
        require_teacher_approval: false,
        require_payment: false,
    },
};

export default function Certificates({
    templates = [],
    issued = [],
    kinds = [],
    students = [],
    years = [],
    courses = [],
    offerings = [],
    filters = {},
}) {
    const [editingId, setEditingId] = useState(null);
    const templateForm = useForm({ ...EMPTY_TEMPLATE });
    const issueForm = useForm({
        certificate_template_id: templates[0]?.id || '',
        student_id: students[0]?.id || '',
        academic_year_id: filters.academic_year_id || years[0]?.id || '',
        course_id: '',
        course_offering_id: '',
        grade: '',
        completion_date: new Date().toISOString().slice(0, 10),
        teacher_approved: false,
    });

    const startEdit = (row) => {
        setEditingId(row.id);
        templateForm.setData({
            name: row.name || '',
            name_dv: row.name_dv || '',
            name_ar: row.name_ar || '',
            kind: row.kind || 'course_completion',
            course_id: row.course_id || '',
            body_html: row.body_html || EMPTY_TEMPLATE.body_html,
            active: Boolean(row.active),
            rules: {
                min_progress_percent: row.rules?.min_progress_percent ?? '',
                min_attendance_percent: row.rules?.min_attendance_percent ?? '',
                min_score: row.rules?.min_score ?? '',
                require_final_assessment: Boolean(row.rules?.require_final_assessment),
                require_teacher_approval: Boolean(row.rules?.require_teacher_approval),
                require_payment: Boolean(row.rules?.require_payment),
            },
        });
    };

    const cancelEdit = () => {
        setEditingId(null);
        templateForm.setData({ ...EMPTY_TEMPLATE });
    };

    const exportHref = filters.academic_year_id
        ? `/catalog/certificates/export?academic_year_id=${filters.academic_year_id}`
        : '/catalog/certificates/export';

    return (
        <AppShell title="Certificates">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href={exportHref}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    if (editingId) {
                        templateForm.put(`/catalog/certificates/${editingId}`, { preserveScroll: true, onSuccess: cancelEdit });
                    } else {
                        templateForm.post('/catalog/certificates', { preserveScroll: true });
                    }
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <p className="md:col-span-3 text-sm font-medium">{editingId ? 'Edit template' : 'New template'}</p>
                <input className="form-input" placeholder="Name (EN)" dir="ltr" value={templateForm.data.name} onChange={(e) => templateForm.setData('name', e.target.value)} />
                <input className="form-input" placeholder="Name (DV)" dir="rtl" value={templateForm.data.name_dv} onChange={(e) => templateForm.setData('name_dv', e.target.value)} />
                <input className="form-input" placeholder="Name (AR)" dir="rtl" value={templateForm.data.name_ar} onChange={(e) => templateForm.setData('name_ar', e.target.value)} />
                <select className="form-input" value={templateForm.data.kind} onChange={(e) => templateForm.setData('kind', e.target.value)}>
                    {kinds.map((kind) => <option key={kind.value} value={kind.value}>{kind.label}</option>)}
                </select>
                <select className="form-input" value={templateForm.data.course_id} onChange={(e) => templateForm.setData('course_id', e.target.value)}>
                    <option value="">Any course</option>
                    {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={templateForm.data.active} onChange={(e) => templateForm.setData('active', e.target.checked)} />
                    Active
                </label>
                <input className="form-input" type="number" min="0" max="100" placeholder="Min progress %" value={templateForm.data.rules.min_progress_percent} onChange={(e) => templateForm.setData('rules', { ...templateForm.data.rules, min_progress_percent: e.target.value })} />
                <input className="form-input" type="number" min="0" max="100" placeholder="Min attendance %" value={templateForm.data.rules.min_attendance_percent} onChange={(e) => templateForm.setData('rules', { ...templateForm.data.rules, min_attendance_percent: e.target.value })} />
                <input className="form-input" type="number" min="0" max="100" placeholder="Min score" value={templateForm.data.rules.min_score} onChange={(e) => templateForm.setData('rules', { ...templateForm.data.rules, min_score: e.target.value })} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={templateForm.data.rules.require_final_assessment} onChange={(e) => templateForm.setData('rules', { ...templateForm.data.rules, require_final_assessment: e.target.checked })} />
                    Require final assessment
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={templateForm.data.rules.require_teacher_approval} onChange={(e) => templateForm.setData('rules', { ...templateForm.data.rules, require_teacher_approval: e.target.checked })} />
                    Require teacher approval
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={templateForm.data.rules.require_payment} onChange={(e) => templateForm.setData('rules', { ...templateForm.data.rules, require_payment: e.target.checked })} />
                    Require payment
                </label>
                <textarea
                    className="form-input md:col-span-3 min-h-24"
                    dir="auto"
                    placeholder="Body HTML. Placeholders: {{student_name}} {{course_name}} {{offering_name}} {{completion_date}} {{grade}} {{certificate_number}} {{institute}}"
                    value={templateForm.data.body_html}
                    onChange={(e) => templateForm.setData('body_html', e.target.value)}
                />
                <div className="md:col-span-3 flex flex-wrap gap-2">
                    <button type="submit" className="btn-primary" disabled={templateForm.processing}>{editingId ? 'Update template' : 'Save template'}</button>
                    {editingId && <button type="button" className="btn-secondary" onClick={cancelEdit}>Cancel</button>}
                </div>
                {templateForm.errors.name && <p className="md:col-span-3 text-sm text-red-600">{templateForm.errors.name}</p>}
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    issueForm.post('/catalog/certificates/issue', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <p className="md:col-span-3 text-sm font-medium">Issue certificate</p>
                <select className="form-input" value={issueForm.data.certificate_template_id} onChange={(e) => issueForm.setData('certificate_template_id', e.target.value)}>
                    {templates.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <select className="form-input" value={issueForm.data.student_id} onChange={(e) => issueForm.setData('student_id', e.target.value)}>
                    {students.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <select className="form-input" value={issueForm.data.academic_year_id} onChange={(e) => issueForm.setData('academic_year_id', e.target.value)}>
                    {years.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <select className="form-input" value={issueForm.data.course_id} onChange={(e) => issueForm.setData('course_id', e.target.value)}>
                    <option value="">Course (from template)</option>
                    {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                </select>
                <select className="form-input" value={issueForm.data.course_offering_id} onChange={(e) => issueForm.setData('course_offering_id', e.target.value)}>
                    <option value="">Offering (optional)</option>
                    {offerings.map((row) => <option key={row.id} value={row.id}>{row.title}</option>)}
                </select>
                <input className="form-input" placeholder="Grade (optional)" value={issueForm.data.grade} onChange={(e) => issueForm.setData('grade', e.target.value)} />
                <input className="form-input" type="date" value={issueForm.data.completion_date} onChange={(e) => issueForm.setData('completion_date', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={issueForm.data.teacher_approved} onChange={(e) => issueForm.setData('teacher_approved', e.target.checked)} />
                    Teacher approved
                </label>
                <div className="md:col-span-3">
                    <button type="submit" className="btn-primary" disabled={issueForm.processing}>Issue</button>
                </div>
                {issueForm.errors.student_id && <p className="md:col-span-3 text-sm text-red-600">{issueForm.errors.student_id}</p>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Course</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {issued.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.certificate_number}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.course_name || '—'}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2 text-end">
                                    <a className="text-sm text-[#7C2D37] hover:underline" href={`/catalog/certificates/${row.id}/download`}>Open HTML</a>
                                    {' · '}
                                    <a className="text-sm text-[#7C2D37] hover:underline" href={row.verify_url}>Verify</a>
                                    {!row.revoked && (
                                        <>
                                            {' · '}
                                            <button
                                                type="button"
                                                className="text-sm text-red-700"
                                                onClick={() => router.post(`/catalog/certificates/${row.id}/revoke`)}
                                            >
                                                Revoke
                                            </button>
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
