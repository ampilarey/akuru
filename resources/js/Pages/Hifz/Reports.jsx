import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The Hifz reports hub (the Hifz port, slice 3, STATUS §5jx): five doors,
 * and the sessions CSV for whoever may export — a plain link, since it is
 * a file, not a page.
 */
const LABELS = { weak_students: 'Weak Students', haraka: 'Haraka Mistake Report', parent_follow_up: 'Parent Follow-up', teacher_completion: 'Teacher Completion', milestones: 'Milestone Approval' };

export default function Reports({ reports = [], export_href = null, t = {} }) {
    return (
        <AppShell title={t.hifz_reports_title || 'Hifz Reports'}>
            <div className="grid gap-4 sm:grid-cols-2" data-testid="hifz-reports">
                {reports.map((report) => (
                    <Link key={report.key} href={report.href} className="rounded-lg border bg-white p-4 hover:shadow-md" data-testid={`hifz-report-${report.key}`}>
                        {t[`hifz_report_${report.key}`] || LABELS[report.key]}
                    </Link>
                ))}
                {export_href && (
                    <a href={export_href} className="rounded-lg border bg-white p-4 hover:shadow-md" data-testid="hifz-report-export">{t.hifz_report_export || 'Export Sessions CSV'}</a>
                )}
            </div>
        </AppShell>
    );
}
