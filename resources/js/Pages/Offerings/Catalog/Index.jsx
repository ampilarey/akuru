import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

/**
 * SPEC §39: "Certificate rules may be set at course level and overridden at
 * offering level."
 *
 * Blank is not "no" here — it means inherit whatever the course's certificate
 * template says. That is why the flags are three-way selects rather than
 * checkboxes: a checkbox has no way to say "leave this one alone", and an
 * unticked one would post `false` and quietly switch the course's requirement
 * off for this batch.
 */
const BLANK_RULES = {
    min_progress_percent: '',
    min_attendance_percent: '',
    min_score: '',
    assessment_id: '',
    require_final_assessment: '',
    require_teacher_approval: '',
    require_payment: '',
};

const RULE_FLAGS = [
    ['require_final_assessment', 'Final assessment'],
    ['require_teacher_approval', 'Teacher approval'],
    ['require_payment', 'Payment complete'],
];

const RULE_NUMBERS = [
    ['min_progress_percent', 'Min progress %'],
    ['min_attendance_percent', 'Min attendance %'],
    ['min_score', 'Min score %'],
];

function rulesToForm(rules) {
    if (!rules) {
        return { ...BLANK_RULES };
    }
    const out = { ...BLANK_RULES };
    Object.keys(BLANK_RULES).forEach((key) => {
        const value = rules[key];
        if (value === undefined || value === null) {
            return;
        }
        out[key] = typeof value === 'boolean' ? (value ? '1' : '0') : String(value);
    });
    return out;
}

function ruleSummary(rules) {
    if (!rules) {
        return null;
    }
    const parts = [];
    RULE_NUMBERS.forEach(([key, label]) => {
        if (rules[key] !== undefined && rules[key] !== null) {
            parts.push(`${label} ${rules[key]}`);
        }
    });
    RULE_FLAGS.forEach(([key, label]) => {
        if (rules[key] !== undefined && rules[key] !== null) {
            parts.push(`${label}: ${rules[key] ? 'required' : 'not required'}`);
        }
    });
    if (rules.assessment_id) {
        parts.push(`Assessment #${rules.assessment_id}`);
    }
    return parts.length > 0 ? parts.join(' · ') : null;
}

export default function Index({ rows, courses, modes, statuses = [], assessments = [], audiences = [], levels = [] }) {
    const t = usePage().props.i18n?.learn || {};
    const [editing, setEditing] = useState(null);

    const blank = {
        course_id: courses[0]?.id || '',
        title: '',
        delivery_mode: modes[0] || 'self_learning',
        status: 'draft',
        pin_mode: 'latest',
        // SPEC §10.5/§10.6: who the batch is for and how advanced it is. Both
        // taxonomies were admin-managed and seeded long before there was
        // anywhere to attach them.
        audience_id: '',
        level_id: '',
        seat_limit: '',
        price_override: '',
        certificate_rules: { ...BLANK_RULES },
    };
    const form = useForm(blank);

    // On a new offering every state is a legal starting point; on an existing
    // one §11.4's transition rules decide, and the server rejects the rest.
    const statusChoices = editing
        ? statuses.filter((s) => s.value === editing.status || (editing.allowed_transitions || []).includes(s.value))
        : statuses.filter((s) => !s.deprecated);

    const courseAssessments = assessments.filter(
        (a) => String(a.course_id || '') === String(form.data.course_id || ''),
    );

    const startEdit = (row) => {
        setEditing(row);
        form.setData({
            course_id: row.course_id || '',
            title: row.title || '',
            delivery_mode: row.delivery_mode || modes[0],
            status: row.status || 'draft',
            pin_mode: row.pin_mode || 'latest',
            audience_id: row.audience_id ? String(row.audience_id) : '',
            level_id: row.level_id ? String(row.level_id) : '',
            seat_limit: row.seat_limit === null || row.seat_limit === undefined ? '' : String(row.seat_limit),
            price_override:
                row.price_override === null || row.price_override === undefined ? '' : String(row.price_override),
            certificate_rules: rulesToForm(row.certificate_rules),
        });
        form.clearErrors();
    };

    const cancelEdit = () => {
        setEditing(null);
        form.setData({ ...blank });
        form.clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            form.put(`/catalog/offerings/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => cancelEdit(),
            });
            return;
        }
        form.post('/catalog/offerings', {
            preserveScroll: true,
            onSuccess: () => form.setData({ ...blank }),
        });
    };

    const setRule = (key, value) =>
        form.setData('certificate_rules', { ...form.data.certificate_rules, [key]: value });

    return (
        <AppShell title={t.offerings || 'Offerings'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/offerings/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form onSubmit={submit} className="mb-4 rounded-lg border bg-white p-4">
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="font-medium">
                        {editing ? `Editing “${editing.title}”` : 'New offering'}
                    </h2>
                    {editing && (
                        <button type="button" className="btn-secondary" onClick={cancelEdit}>
                            Cancel
                        </button>
                    )}
                </div>
                <div className="grid gap-3 md:grid-cols-4 lg:grid-cols-5">
                    <select className="form-input" aria-label="Course" value={form.data.course_id} onChange={(e) => form.setData('course_id', e.target.value)}>
                        {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                    </select>
                    <input className="form-input" placeholder="Offering title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    <select className="form-input" aria-label="Delivery mode" value={form.data.delivery_mode} onChange={(e) => form.setData('delivery_mode', e.target.value)}>
                        {modes.map((mode) => <option key={mode} value={mode}>{mode}</option>)}
                    </select>
                    <select className="form-input" aria-label="Status" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statusChoices.map((status) => (
                            <option key={status.value} value={status.value}>{status.label}</option>
                        ))}
                    </select>
                    <select className="form-input" aria-label="Audience" value={form.data.audience_id} onChange={(e) => form.setData('audience_id', e.target.value)}>
                        <option value="">Audience —</option>
                        {audiences.map((a) => <option key={a.id} value={a.id}>{a.label}</option>)}
                    </select>
                    <select className="form-input" aria-label="Level" value={form.data.level_id} onChange={(e) => form.setData('level_id', e.target.value)}>
                        <option value="">Level —</option>
                        {levels.map((l) => <option key={l.id} value={l.id}>{l.label}</option>)}
                    </select>
                    <input className="form-input" placeholder="Seat limit" value={form.data.seat_limit} onChange={(e) => form.setData('seat_limit', e.target.value)} />
                    <input className="form-input" placeholder="Price override (MVR)" value={form.data.price_override} onChange={(e) => form.setData('price_override', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={form.processing || courses.length === 0}>
                        {editing ? 'Save changes' : 'Save offering'}
                    </button>
                </div>
                {form.errors.status && <p className="mt-2 text-sm text-red-600">{form.errors.status}</p>}

                <details className="mt-3 rounded border bg-[#FAF7F2] p-3" open={Boolean(ruleSummary(editing?.certificate_rules))}>
                    <summary className="cursor-pointer text-sm font-medium">
                        Certificate rules for this offering (SPEC §39 override)
                    </summary>
                    <p className="mt-2 text-xs text-gray-600">
                        Leave a field blank to use the course certificate template’s rule. A value here applies
                        to this offering only.
                    </p>
                    <div className="mt-3 grid gap-3 md:grid-cols-4">
                        {RULE_NUMBERS.map(([key, label]) => (
                            <label key={key} className="text-xs text-gray-700">
                                {label}
                                <input
                                    className="form-input mt-1"
                                    type="number"
                                    min="0"
                                    max="100"
                                    placeholder="inherit"
                                    value={form.data.certificate_rules[key]}
                                    onChange={(e) => setRule(key, e.target.value)}
                                />
                                {form.errors[`certificate_rules.${key}`] && (
                                    <span className="mt-1 block text-red-600">{form.errors[`certificate_rules.${key}`]}</span>
                                )}
                            </label>
                        ))}
                        <label className="text-xs text-gray-700">
                            Final assessment
                            <select
                                className="form-input mt-1"
                                value={form.data.certificate_rules.assessment_id}
                                onChange={(e) => setRule('assessment_id', e.target.value)}
                            >
                                <option value="">Inherit from template</option>
                                {courseAssessments.map((a) => (
                                    <option key={a.id} value={a.id}>{a.title}</option>
                                ))}
                            </select>
                        </label>
                        {RULE_FLAGS.map(([key, label]) => (
                            <label key={key} className="text-xs text-gray-700">
                                {label}
                                <select
                                    className="form-input mt-1"
                                    value={form.data.certificate_rules[key]}
                                    onChange={(e) => setRule(key, e.target.value)}
                                >
                                    <option value="">Inherit from template</option>
                                    <option value="1">Required</option>
                                    <option value="0">Not required</option>
                                </select>
                            </label>
                        ))}
                    </div>
                </details>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Course</th>
                            <th className="px-3 py-2">Mode</th>
                            <th className="px-3 py-2">Audience</th>
                            <th className="px-3 py-2">Level</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Price</th>
                            <th className="px-3 py-2">Certificate rules</th>
                            <th className="px-3 py-2">Pin</th>
                            <th className="px-3 py-2">Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={10}>No offerings yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => startEdit(row)}>
                                        {row.title}
                                    </button>
                                </td>
                                <td className="px-3 py-2">{row.course_title}</td>
                                <td className="px-3 py-2">{row.delivery_mode}</td>
                                <td className="px-3 py-2">{row.audience || '—'}</td>
                                <td className="px-3 py-2">{row.level || '—'}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2">{row.price_override !== null && row.price_override !== undefined ? `MVR ${row.price_override}` : '—'}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">{ruleSummary(row.certificate_rules) || 'Inherits course template'}</td>
                                <td className="px-3 py-2">
                                    <span className="me-2">{row.pin_mode}</span>
                                    {/* SPEC §28.4: re-pinning changes what enrolled
                                        students see mid-offering, so it must be
                                        deliberate and it must record why. The reason
                                        is nullable in the spec, so an empty answer
                                        still pins — but it is asked for, and it was
                                        not captured at all before. */}
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        onClick={() => {
                                            const reason = window.prompt(
                                                `Re-pin "${row.title}" to the current published revisions?\n\nEnrolled students will see the new content. Reason (optional):`,
                                                '',
                                            );
                                            if (reason === null) {
                                                return;
                                            }
                                            router.post(`/catalog/offerings/${row.id}/pin`, { reason }, { preserveScroll: true });
                                        }}
                                    >
                                        Pin now
                                    </button>
                                    {(row.repin_events || []).length > 0 && (
                                        <details className="mt-1 text-xs text-gray-600">
                                            <summary className="cursor-pointer">
                                                {row.repin_events.length} re-pin{row.repin_events.length === 1 ? '' : 's'}
                                            </summary>
                                            <ul className="mt-1 space-y-1">
                                                {row.repin_events.map((event) => (
                                                    <li key={event.id}>
                                                        <span className="font-medium">{(event.changed_at || '').slice(0, 10)}</span>
                                                        {event.changed_by ? ` · ${event.changed_by}` : ' · unknown admin'}
                                                        {` · ${event.old_pin_mode || 'unset'} → ${event.new_pin_mode}`}
                                                        {` · ${event.changed_lessons.length} lesson${event.changed_lessons.length === 1 ? '' : 's'} changed`}
                                                        {event.reason ? ` · ${event.reason}` : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        </details>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/offerings/${row.id}/sessions`}>{t.sessions || 'Sessions'}</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
