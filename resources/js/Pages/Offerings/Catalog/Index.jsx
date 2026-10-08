import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';
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

// Each rule and the `teach` phrase that names it (slice CT8).
const RULE_FLAGS = [
    ['require_final_assessment', 'offerings_rule_final'],
    ['require_teacher_approval', 'offerings_rule_approval'],
    ['require_payment', 'offerings_rule_payment'],
];

const RULE_NUMBERS = [
    ['min_progress_percent', 'offerings_rule_progress'],
    ['min_attendance_percent', 'offerings_rule_attendance'],
    ['min_score', 'offerings_rule_score'],
];

// The English for each rule, beside its phrase, for a page without the book.
const RULE_ENGLISH = {
    offerings_rule_final: 'Final assessment',
    offerings_rule_approval: 'Teacher approval',
    offerings_rule_payment: 'Payment complete',
    offerings_rule_progress: 'Min progress %',
    offerings_rule_attendance: 'Min attendance %',
    offerings_rule_score: 'Min score %',
};

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

function ruleSummary(rules, t) {
    if (!rules) {
        return null;
    }
    const name = (phrase) => t[phrase] || RULE_ENGLISH[phrase];
    const parts = [];
    RULE_NUMBERS.forEach(([key, phrase]) => {
        if (rules[key] !== undefined && rules[key] !== null) {
            parts.push(`${name(phrase)} ${rules[key]}`);
        }
    });
    RULE_FLAGS.forEach(([key, phrase]) => {
        if (rules[key] !== undefined && rules[key] !== null) {
            parts.push(rules[key]
                ? (t.offerings_rule_required || ':rule: required').replace(':rule', name(phrase))
                : (t.offerings_rule_not_required || ':rule: not required').replace(':rule', name(phrase)));
        }
    });
    if (rules.assessment_id) {
        parts.push((t.offerings_rule_assessment || 'Assessment #:id').replace(':id', rules.assessment_id));
    }
    return parts.length > 0 ? parts.join(' · ') : null;
}

export default function Index({ rows, courses, modes, statuses = [], assessments = [], audiences = [], levels = [], t = {} }) {
    const [editing, setEditing] = useState(null);
    // Codes the server sends, named in the page's language (slice CT8).
    const modeName = (mode) => t[`delivery_mode_${mode}`] || mode;
    const statusName = (status, fallback) => t[`offering_status_${status}`] || fallback || status;
    const pinName = (mode) => t[`pin_mode_${mode}`] || mode;

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
    // The form showed its status and certificate-rule refusals and dropped the
    // rest (a delivery mode, a slug taken, an audience or level gone), and a
    // refused Pin now was shown nowhere (slice CT6b-2c).
    const refusals = useRowRefusals(form);

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
        <AppShell title={t.offerings_title || 'Offerings'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/offerings/export">{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form onSubmit={submit} className="mb-4 rounded-lg border bg-white p-4">
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="font-medium">
                        {editing ? (t.offerings_editing || 'Editing “:title”').replace(':title', () => editing.title) : (t.offerings_new || 'New offering')}
                    </h2>
                    {editing && (
                        <button type="button" className="btn-secondary" onClick={cancelEdit}>
                            {t.offerings_cancel || 'Cancel'}
                        </button>
                    )}
                </div>
                <div className="grid gap-3 md:grid-cols-4 lg:grid-cols-5">
                    <select className="form-input" aria-label={t.offerings_course || 'Course'} value={form.data.course_id} onChange={(e) => form.setData('course_id', e.target.value)}>
                        {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                    </select>
                    <input className="form-input" placeholder={t.offerings_offering_title || 'Offering title'} aria-label={t.offerings_offering_title || 'Offering title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    <select className="form-input" aria-label={t.offerings_mode || 'Delivery mode'} value={form.data.delivery_mode} onChange={(e) => form.setData('delivery_mode', e.target.value)}>
                        {modes.map((mode) => <option key={mode} value={mode}>{modeName(mode)}</option>)}
                    </select>
                    <select className="form-input" aria-label={t.offerings_status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statusChoices.map((status) => (
                            <option key={status.value} value={status.value}>{statusName(status.value, status.label)}</option>
                        ))}
                    </select>
                    <select className="form-input" aria-label={t.offerings_audience || 'Audience'} value={form.data.audience_id} onChange={(e) => form.setData('audience_id', e.target.value)}>
                        <option value="">{t.offerings_audience_any || 'Audience —'}</option>
                        {audiences.map((a) => <option key={a.id} value={a.id}>{a.label}</option>)}
                    </select>
                    <select className="form-input" aria-label={t.offerings_level || 'Level'} value={form.data.level_id} onChange={(e) => form.setData('level_id', e.target.value)}>
                        <option value="">{t.offerings_level_any || 'Level —'}</option>
                        {levels.map((l) => <option key={l.id} value={l.id}>{l.label}</option>)}
                    </select>
                    <input className="form-input" placeholder={t.offerings_seat_limit || 'Seat limit'} aria-label={t.offerings_seat_limit || 'Seat limit'} value={form.data.seat_limit} onChange={(e) => form.setData('seat_limit', e.target.value)} />
                    <input className="form-input" placeholder={t.offerings_price_override || 'Price override (MVR)'} aria-label={t.offerings_price_override || 'Price override (MVR)'} value={form.data.price_override} onChange={(e) => form.setData('price_override', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={form.processing || courses.length === 0}>
                        {editing ? (t.offerings_save_changes || 'Save changes') : (t.offerings_save || 'Save offering')}
                    </button>
                </div>
                {form.errors.status && <p className="mt-2 text-sm text-red-600">{form.errors.status}</p>}
                <FormErrors errors={form.errors} except={['status', ...RULE_NUMBERS.map(([key]) => `certificate_rules.${key}`)]} className="mt-2" />

                <details className="mt-3 rounded border bg-[#FAF7F2] p-3" open={Boolean(ruleSummary(editing?.certificate_rules, t))}>
                    <summary className="cursor-pointer text-sm font-medium">
                        {t.offerings_rules || 'Certificate rules for this offering'}
                    </summary>
                    <p className="mt-2 text-xs text-gray-600">
                        {t.offerings_rules_help || 'Leave a field blank to use the course certificate template’s rule. A value here applies to this offering only.'}
                    </p>
                    <div className="mt-3 grid gap-3 md:grid-cols-4">
                        {RULE_NUMBERS.map(([key, phrase]) => (
                            <label key={key} className="text-xs text-gray-700">
                                {t[phrase] || RULE_ENGLISH[phrase]}
                                <input
                                    className="form-input mt-1"
                                    type="number"
                                    min="0"
                                    max="100"
                                    placeholder={t.offerings_inherit || 'inherit'}
                                    value={form.data.certificate_rules[key]}
                                    onChange={(e) => setRule(key, e.target.value)}
                                />
                                {form.errors[`certificate_rules.${key}`] && (
                                    <span className="mt-1 block text-red-600">{form.errors[`certificate_rules.${key}`]}</span>
                                )}
                            </label>
                        ))}
                        <label className="text-xs text-gray-700">
                            {t.offerings_rule_final || 'Final assessment'}
                            <select
                                className="form-input mt-1"
                                value={form.data.certificate_rules.assessment_id}
                                onChange={(e) => setRule('assessment_id', e.target.value)}
                            >
                                <option value="">{t.offerings_inherit_template || 'Inherit from template'}</option>
                                {courseAssessments.map((a) => (
                                    <option key={a.id} value={a.id}>{a.title}</option>
                                ))}
                            </select>
                        </label>
                        {RULE_FLAGS.map(([key, phrase]) => (
                            <label key={key} className="text-xs text-gray-700">
                                {t[phrase] || RULE_ENGLISH[phrase]}
                                <select
                                    className="form-input mt-1"
                                    value={form.data.certificate_rules[key]}
                                    onChange={(e) => setRule(key, e.target.value)}
                                >
                                    <option value="">{t.offerings_inherit_template || 'Inherit from template'}</option>
                                    <option value="1">{t.offerings_required || 'Required'}</option>
                                    <option value="0">{t.offerings_not_required || 'Not required'}</option>
                                </select>
                            </label>
                        ))}
                    </div>
                </details>
            </form>
            <FormErrors errors={refusals.unplaced} className="mb-4 rounded border border-red-200 bg-red-50 py-2 pe-3" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.catalog_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.offerings_course || 'Course'}</th>
                            <th className="px-3 py-2">{t.offerings_col_mode || 'Mode'}</th>
                            <th className="px-3 py-2">{t.offerings_audience || 'Audience'}</th>
                            <th className="px-3 py-2">{t.offerings_level || 'Level'}</th>
                            <th className="px-3 py-2">{t.offerings_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.offerings_col_price || 'Price'}</th>
                            <th className="px-3 py-2">{t.offerings_col_rules || 'Certificate rules'}</th>
                            <th className="px-3 py-2">{t.offerings_col_pin || 'Pin'}</th>
                            <th className="px-3 py-2">{t.offerings_col_sessions || 'Sessions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={10}>{t.offerings_none || 'No offerings yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => startEdit(row)}>
                                        {row.title}
                                    </button>
                                </td>
                                <td className="px-3 py-2">{row.course_title}</td>
                                <td className="px-3 py-2">{modeName(row.delivery_mode)}</td>
                                <td className="px-3 py-2">{row.audience || '—'}</td>
                                <td className="px-3 py-2">{row.level || '—'}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                                <td className="px-3 py-2">{row.price_override !== null && row.price_override !== undefined ? (t.offerings_price || 'MVR :amount').replace(':amount', row.price_override) : '—'}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">{ruleSummary(row.certificate_rules, t) || t.offerings_inherits || 'Inherits course template'}</td>
                                <td className="px-3 py-2">
                                    <span className="me-2">{pinName(row.pin_mode)}</span>
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
                                                (t.offerings_repin_prompt || 'Re-pin “:title” to the current published revisions? Enrolled students will see the new content. Reason (optional):').replace(':title', () => row.title),
                                                '',
                                            );
                                            if (reason === null) {
                                                return;
                                            }
                                            refusals.actOn(`offering:${row.id}`, () => router.post(`/catalog/offerings/${row.id}/pin`, { reason }, { preserveScroll: true }));
                                        }}
                                    >
                                        {t.offerings_pin_now || 'Pin now'}
                                    </button>
                                    <FormErrors errors={refusals.errorsFor(`offering:${row.id}`)} className="mt-1" />
                                    {(row.repin_events || []).length > 0 && (
                                        <details className="mt-1 text-xs text-gray-600">
                                            <summary className="cursor-pointer">
                                                {row.repin_events.length === 1
                                                    ? (t.offerings_repins_one || '1 re-pin')
                                                    : (t.offerings_repins_many || ':count re-pins').replace(':count', row.repin_events.length)}
                                            </summary>
                                            <ul className="mt-1 space-y-1">
                                                {row.repin_events.map((event) => (
                                                    <li key={event.id}>
                                                        <span className="font-medium">{(event.changed_at || '').slice(0, 10)}</span>
                                                        {` · ${event.changed_by || t.offerings_unknown_admin || 'unknown admin'}`}
                                                        {` · ${event.old_pin_mode ? pinName(event.old_pin_mode) : (t.offerings_unset || 'unset')} → ${pinName(event.new_pin_mode)}`}
                                                        {` · ${event.changed_lessons.length === 1
                                                            ? (t.offerings_lessons_changed_one || '1 lesson changed')
                                                            : (t.offerings_lessons_changed_many || ':count lessons changed').replace(':count', event.changed_lessons.length)}`}
                                                        {event.reason ? ` · ${event.reason}` : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        </details>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/offerings/${row.id}/sessions`}>{t.offerings_col_sessions || 'Sessions'}</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
