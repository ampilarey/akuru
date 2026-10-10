import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const TAGGABLE = ['exam', 'plan_topic'];

/**
 * Curriculum standards and how far the year's exams and plans cover them.
 * Every word is the `exams` book's (slice EG2, STATUS §5qk). A standard reads
 * by the title the school gave it in the page's language, and the form takes
 * its Dhivehi and Arabic titles; a subject reads by the school's name for it.
 * A refused tag is said under its form — it was said nowhere — and a plan
 * topic is chosen from the plans' topics: the list offered exams for it, so
 * a topic was tagged by an exam's id.
 */
export default function Index({ subjects, terms, exams, topics = [], standards, coverage, subjectId, termId, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const titled = (row) => ({ dv: row?.title_dhivehi, ar: row?.title_arabic }[locale]) || row?.title;
    const form = useForm({
        subject_id: subjectId || '',
        code: '',
        title: '',
        title_arabic: '',
        title_dhivehi: '',
        parent_id: '',
        active: true,
    });
    const tag = useForm({
        standard_id: standards[0]?.id || '',
        taggable_type: 'exam',
        taggable_id: exams[0]?.id || '',
    });
    const refused = form.errors.code || form.errors.title || form.errors.subject_id || form.errors.parent_id;
    const tagRefused = tag.errors.standard_id || tag.errors.taggable_type || tag.errors.taggable_id;
    const taggables = tag.data.taggable_type === 'plan_topic'
        ? topics.map((topic) => ({ id: topic.id, name: `${topic.plan_title} — ${topic.title}` }))
        : exams.map((exam) => ({ id: exam.id, name: exam.name }));

    return (
        <AppShell title={t.standards_title || 'Standards'}>
            <div className="mb-4 flex flex-wrap justify-between gap-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(e.currentTarget);
                        router.get('/exams/standards', {
                            subject_id: data.get('subject_id'),
                            term_id: data.get('term_id'),
                        });
                    }}
                    className="flex gap-2"
                >
                    <select className="form-input" name="subject_id" aria-label={t.subject || 'Subject'} defaultValue={subjectId || ''}>
                        <option value="">{t.all_subjects || 'All subjects'}</option>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{named(subject)}</option>)}
                    </select>
                    <select className="form-input" name="term_id" aria-label={t.term || 'Term'} defaultValue={termId || ''}>
                        <option value="">{t.all_terms || 'All terms'}</option>
                        {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                    </select>
                    <button type="submit" className="btn-secondary">{t.standards_coverage || 'Coverage'}</button>
                </form>
                <a className="btn-secondary" href={`/exams/standards/export?subject_id=${subjectId || ''}&term_id=${termId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/standards', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" dir="ltr" aria-label={t.standards_code || 'Code'} placeholder={t.standards_code || 'Code'} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                <input className="form-input" aria-label={t.title_en || 'Title (EN)'} placeholder={t.title_en || 'Title (EN)'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.title_dv || 'Title (DV)'} placeholder={t.title_dv || 'Title (DV)'} value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.title_ar || 'Title (AR)'} placeholder={t.title_ar || 'Title (AR)'} value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                <select className="form-input" aria-label={t.subject || 'Subject'} value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">{t.any_subject || 'Any subject'}</option>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{named(subject)}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.standards_create || 'Create standard'}</button>
                {refused && <span className="text-xs text-red-600 md:col-span-4">{refused}</span>}
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    tag.post('/exams/standards/tag', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.standards_standard || 'Standard'} value={tag.data.standard_id} onChange={(e) => tag.setData('standard_id', e.target.value)}>
                    {standards.map((row) => <option key={row.id} value={row.id}>{row.code} {titled(row)}</option>)}
                </select>
                <select
                    className="form-input"
                    aria-label={t.standards_tag_what || 'Tag what'}
                    value={tag.data.taggable_type}
                    onChange={(e) => {
                        const type = e.target.value;
                        const first = type === 'plan_topic' ? topics[0]?.id : exams[0]?.id;
                        tag.setData({ ...tag.data, taggable_type: type, taggable_id: first || '' });
                    }}
                >
                    {TAGGABLE.map((type) => <option key={type} value={type}>{t[`taggable_${type}`] || type}</option>)}
                </select>
                <select className="form-input" aria-label={t[`taggable_${tag.data.taggable_type}`] || tag.data.taggable_type} value={tag.data.taggable_id} onChange={(e) => tag.setData('taggable_id', e.target.value)}>
                    {taggables.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary" disabled={tag.processing || !tag.data.taggable_id}>{t.standards_tag || 'Tag'}</button>
                {tagRefused && <span className="text-xs text-red-600 md:col-span-4">{tagRefused}</span>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.standards_code || 'Code'}</th>
                            <th className="px-3 py-2">{t.standards_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.standards_col_exams || 'Exams'}</th>
                            <th className="px-3 py-2">{t.standards_col_topics || 'Topics'}</th>
                            <th className="px-3 py-2">{t.standards_col_covered || 'Covered'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {coverage.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.standards_none || 'No standards yet.'}</td></tr>
                        )}
                        {coverage.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2 font-mono" dir="ltr">{row.code}</td>
                                <td className="px-3 py-2">{titled(row)}</td>
                                <td className="px-3 py-2">{row.exams_tagged}</td>
                                <td className="px-3 py-2">{row.topics_tagged}</td>
                                <td className="px-3 py-2">{row.covered ? (t.yes || 'yes') : (t.no || 'no')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
