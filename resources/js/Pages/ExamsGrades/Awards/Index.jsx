import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const LEVELS = ['school', 'class'];

/**
 * Awards and the certificates issued for them. Every word is the `exams`
 * book's (slice EG2, STATUS §5qk); an award's level is named rather than
 * printed as its code, and an award reads by the title the school gave it in
 * the page's language. A refused award or issue is said under the form that
 * asked — the issue form said nothing.
 */
export default function Index({ awards, students, issued, years, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const titled = (row) => ({ dv: row?.title_dhivehi, ar: row?.title_arabic }[locale]) || row?.title;
    const levelName = (level) => t[`award_level_${level}`] || level;

    const award = useForm({
        title: '',
        title_arabic: '',
        title_dhivehi: '',
        level: 'school',
        description: '',
        active: true,
    });
    const issue = useForm({
        award_id: awards[0]?.id || '',
        student_ids: students[0] ? [students[0].id] : [],
        academic_year_id: years[0]?.id || '',
        term_id: '',
        awarded_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const awardRefused = award.errors.title || award.errors.level;
    const issueRefused = issue.errors.student_ids || issue.errors.award_id || issue.errors.academic_year_id || issue.errors.awarded_date;

    return (
        <AppShell title={t.awards_title || 'Awards'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/awards/export">{t.export_csv || 'Export CSV'}</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    award.post('/exams/awards', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" aria-label={t.title_en || 'Title (EN)'} placeholder={t.title_en || 'Title (EN)'} value={award.data.title} onChange={(e) => award.setData('title', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.title_dv || 'Title (DV)'} placeholder={t.title_dv || 'Title (DV)'} value={award.data.title_dhivehi} onChange={(e) => award.setData('title_dhivehi', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.title_ar || 'Title (AR)'} placeholder={t.title_ar || 'Title (AR)'} value={award.data.title_arabic} onChange={(e) => award.setData('title_arabic', e.target.value)} />
                <select className="form-input" aria-label={t.awards_level || 'Level'} value={award.data.level} onChange={(e) => award.setData('level', e.target.value)}>
                    {LEVELS.map((level) => <option key={level} value={level}>{levelName(level)}</option>)}
                </select>
                <input className="form-input md:col-span-3" aria-label={t.awards_description || 'Description'} placeholder={t.awards_description || 'Description'} value={award.data.description} onChange={(e) => award.setData('description', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={award.processing}>{t.awards_create || 'Create award'}</button>
                {awardRefused && <p className="text-sm text-red-600 md:col-span-4">{awardRefused}</p>}
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    issue.transform((data) => ({
                        ...data,
                        student_ids: Array.isArray(data.student_ids) ? data.student_ids : [data.student_ids],
                    }));
                    issue.post('/exams/awards/issue', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" aria-label={t.awards_award || 'Award'} value={issue.data.award_id} onChange={(e) => issue.setData('award_id', e.target.value)}>
                    {awards.map((row) => <option key={row.id} value={row.id}>{titled(row)}</option>)}
                </select>
                <select
                    className="form-input"
                    multiple
                    aria-label={t.awards_students || 'Students'}
                    value={issue.data.student_ids}
                    onChange={(e) => issue.setData('student_ids', Array.from(e.target.selectedOptions).map((option) => option.value))}
                >
                    {students.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <select className="form-input" aria-label={t.year || 'Year'} value={issue.data.academic_year_id} onChange={(e) => issue.setData('academic_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <input className="form-input" type="date" aria-label={t.awards_date || 'Date awarded'} value={issue.data.awarded_date} onChange={(e) => issue.setData('awarded_date', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={issue.processing}>{t.awards_issue || 'Issue certificates'}</button>
                {issueRefused && <p className="text-sm text-red-600 md:col-span-3">{issueRefused}</p>}
            </form>

            <form className="mb-4 flex flex-wrap gap-2 rounded-lg border bg-white p-4" method="get" action="/exams/awards/id-card">
                <select className="form-input" name="student_id" aria-label={t.student || 'Student'} defaultValue={students[0]?.id || ''}>
                    {students.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary">{t.awards_id_card || 'ID card'}</button>
                <button type="submit" className="btn-secondary" formAction="/exams/awards/transfer">{t.awards_transfer || 'Transfer cert'}</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            <th className="px-3 py-2">{t.awards_award || 'Award'}</th>
                            <th className="px-3 py-2">{t.awards_level || 'Level'}</th>
                            <th className="px-3 py-2">{t.date || 'Date'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {issued.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.awards_none || 'No awards issued yet.'}</td></tr>
                        )}
                        {issued.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{({ dv: row.award_dhivehi, ar: row.award_arabic }[locale]) || row.award}</td>
                                <td className="px-3 py-2">{row.level ? levelName(row.level) : '—'}</td>
                                <td className="px-3 py-2">{row.awarded_date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
