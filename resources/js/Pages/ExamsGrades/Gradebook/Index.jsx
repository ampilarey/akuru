import { router, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function gradeCell(result, t) {
    if (!result) {
        return '—';
    }
    if (result.is_absent) {
        return t.gradebook_absent_short || 'Abs';
    }
    if (result.is_exempt) {
        return t.gradebook_exempt_short || 'Ex';
    }
    if (result.status === 'submitted') {
        return t.gradebook_pending || 'Pending';
    }
    if (result.score === null || result.score === undefined) {
        return '—';
    }
    return result.score;
}

/**
 * A class's marks for a subject and term, with the term's weighted result.
 * Every word is the `exams` book's (slice EG1, STATUS §5qj); a subject reads
 * by the name the school gave it in the page's language.
 */
export default function Index({ years, terms, classes, subjects, exams, competencies, rows, classId, subjectId, termId, missing_weights = false, grade_items = [], t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const extraItems = grade_items.filter((item) => item.source !== 'exam');
    const emptyColSpan = 4 + exams.length + extraItems.length + competencies.length;

    return (
        <AppShell title={t.gradebook_title || 'Gradebook'}>
            <form
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    const data = new FormData(e.currentTarget);
                    router.get('/exams/gradebook', {
                        class_id: data.get('class_id'),
                        subject_id: data.get('subject_id'),
                        term_id: data.get('term_id'),
                    });
                }}
            >
                <select className="form-input" name="term_id" aria-label={t.term || 'Term'} defaultValue={termId || ''}>
                    <option value="">{t.term || 'Term'}</option>
                    {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                </select>
                <select className="form-input" name="class_id" aria-label={t.class || 'Class'} defaultValue={classId || ''}>
                    <option value="">{t.class || 'Class'}</option>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                </select>
                <select className="form-input" name="subject_id" aria-label={t.subject || 'Subject'} defaultValue={subjectId || ''}>
                    <option value="">{t.subject || 'Subject'}</option>
                    {subjects.map((row) => <option key={row.id} value={row.id}>{named(row)}</option>)}
                </select>
                <div className="flex gap-2">
                    <button type="submit" className="btn-secondary">{t.gradebook_load || 'Load'}</button>
                    {classId && subjectId && termId && (
                        <>
                            <button
                                type="button"
                                className="btn-primary"
                                onClick={() => router.post('/exams/gradebook/compute', {
                                    class_id: classId,
                                    subject_id: subjectId,
                                    term_id: termId,
                                })}
                            >
                                {t.gradebook_recompute || 'Recompute'}
                            </button>
                            <a className="btn-secondary" href={`/exams/gradebook/export?class_id=${classId}&subject_id=${subjectId}&term_id=${termId}`}>{t.export_csv || 'Export CSV'}</a>
                        </>
                    )}
                </div>
            </form>

            {classId && subjectId && termId && missing_weights && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    {t.gradebook_missing_weights || 'Term % / grade / rank stay blank until a weight scheme is saved for this year (and optionally this class or subject).'}
                    {' '}
                    <a className="underline" href="/exams/weights">{t.gradebook_open_weights || 'Open Weights'}</a>
                    {' '}
                    {t.gradebook_missing_weights_then || 'and set type shares that add to 100, then Recompute.'}
                </p>
            )}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            {exams.map((exam) => <th key={exam.id} className="px-3 py-2">{exam.name}</th>)}
                            {extraItems.map((item) => <th key={item.key} className="px-3 py-2">{item.label}</th>)}
                            <th className="px-3 py-2">{t.gradebook_term_percent || 'Term %'}</th>
                            <th className="px-3 py-2">{t.gradebook_grade || 'Grade'}</th>
                            <th className="px-3 py-2">{t.gradebook_rank || 'Rank'}</th>
                            {competencies.map((competency) => <th key={`c-${competency.id}`} className="px-3 py-2">{competency.name}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={emptyColSpan}>{t.gradebook_select || 'Select a class, subject, and term.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.student_id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                {exams.map((exam) => {
                                    const mark = row.marks[exam.id] || {};
                                    return (
                                        <td key={`${row.student_id}-${exam.id}`} className="px-3 py-2">
                                            {mark.is_absent ? (t.gradebook_absent_short || 'Abs') : mark.is_exempt ? (t.gradebook_exempt_short || 'Ex') : (mark.marks ?? '—')}
                                        </td>
                                    );
                                })}
                                {extraItems.map((item) => (
                                    <td key={`${row.student_id}-${item.key}`} className="px-3 py-2">
                                        {gradeCell(row.items?.[item.key], t)}
                                    </td>
                                ))}
                                <td className="px-3 py-2">{row.term?.weighted_percent ?? '—'}</td>
                                <td className="px-3 py-2">{row.term?.grade ?? '—'}</td>
                                <td className="px-3 py-2">{row.term?.rank ?? '—'}</td>
                                {competencies.map((competency) => (
                                    <td key={`${row.student_id}-c-${competency.id}`} className="px-3 py-2">
                                        {row.competencies[competency.id] || '—'}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
