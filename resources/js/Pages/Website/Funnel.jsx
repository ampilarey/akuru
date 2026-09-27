import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * The course funnel report (W16, ADR-022; docs/ADMIN_PANEL.md; C9 slice 6,
 * STATUS §5jh): per course, the views, enrol clicks, registrations started
 * and paid, the WhatsApp and syllabus clicks, the view-to-click rate and
 * the decision rule's verdict — the sentence the Website domain composes,
 * so it reads the same here and in the CSV. A course-id filter as an
 * Inertia visit, and a CSV carrying it. Every UI string is a key in the
 * admin tranche.
 */
export default function Funnel({ reports = [], course_id: courseId = null, t = {} }) {
    const [course, setCourse] = useState(courseId ? String(courseId) : '');
    const query = course ? `?course_id=${encodeURIComponent(course)}` : '';
    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/public-site/funnel', course ? { course_id: course } : {}, { preserveState: true, preserveScroll: true });
    };
    const rate = (value) => (value === null || value === undefined ? '—' : `${(value * 100).toFixed(1)}%`);

    return (
        <AppShell title={t.funnel_title || 'Course funnel'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/admin/public-site/leads" className="underline" data-testid="funnel-leads-link">{t.funnel_link_leads || 'Leads →'}</Link>
                <Link href="/admin/public-site/courses" className="underline">{t.leads_link_courses || 'Manage Courses →'}</Link>
                <Link href="/admin/public-site/daily-content" className="underline">{t.leads_link_daily || 'Daily content →'}</Link>
                <a href={`/admin/public-site/funnel/export${query}`} className="ms-auto underline" data-testid="export-csv">{t.funnel_export || 'Export CSV'}</a>
            </div>
            <p className="mb-4 text-sm text-gray-600" data-testid="funnel-rule">{t.funnel_rule || 'Decision rule (ADR-022): iterate W1 content from this funnel — hero, urgency, outcomes, sticky CTA, checkout, or payment copy — when a stage is stuck.'}</p>

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-3" data-testid="funnel-filter">
                <label className="text-xs text-gray-600">
                    {t.funnel_course_id || 'Course id'}
                    <input type="number" name="course_id" min="1" className="form-input mt-1 block w-32" value={course} onChange={(e) => setCourse(e.target.value)} placeholder={t.leads_all || 'All'} />
                </label>
                <button type="submit" className="btn-primary">{t.leads_filter || 'Filter'}</button>
                {courseId && <Link href="/admin/public-site/funnel" className="btn-secondary">{t.leads_clear || 'Clear'}</Link>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="funnel-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.leads_col_course || 'Course'}</th>
                            <th className="px-3 py-2">{t.funnel_col_views || 'Views'}</th>
                            <th className="px-3 py-2">{t.funnel_col_clicks || 'Register clicks'}</th>
                            <th className="px-3 py-2">{t.funnel_col_started || 'Started'}</th>
                            <th className="px-3 py-2">{t.funnel_col_paid || 'Paid'}</th>
                            <th className="px-3 py-2">{t.funnel_col_whatsapp || 'WhatsApp'}</th>
                            <th className="px-3 py-2">{t.funnel_col_syllabus || 'Syllabus'}</th>
                            <th className="px-3 py-2">{t.funnel_col_rate || 'View → click'}</th>
                            <th className="px-3 py-2">{t.funnel_col_decision || 'Decision'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reports.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="9">{t.funnel_none || 'No funnel events yet.'}</td></tr>}
                        {reports.map((r) => (
                            <tr key={r.course_id} className="border-t align-top" data-testid="funnel-row">
                                <td className="px-3 py-2 font-medium text-gray-900">{r.course_title}</td>
                                <td className="px-3 py-2 text-gray-900">{r.counts.course_view}</td>
                                <td className="px-3 py-2 text-gray-900">{r.counts.register_click}</td>
                                <td className="px-3 py-2 text-gray-900">{r.counts.registration_started}</td>
                                <td className="px-3 py-2 text-gray-900">{r.counts.payment_completed}</td>
                                <td className="px-3 py-2 text-gray-900">{r.counts.whatsapp_click}</td>
                                <td className="px-3 py-2 text-gray-900">{r.counts.syllabus_download}</td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-700">{rate(r.rates.view_to_register)}</td>
                                <td className="min-w-[16rem] px-3 py-2 text-gray-700" data-testid="funnel-decision">{r.decision}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
