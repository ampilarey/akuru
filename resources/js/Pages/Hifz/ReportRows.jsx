import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The page four Hifz reports share (the Hifz port, slice 3, STATUS §5jx):
 * a name a row, with a figure beside it where the report has one (weak
 * sessions, haraka mistakes, a date) and a note under it where it has
 * that (the parent follow-up). The controller says which report, and the
 * title and the empty line are keys off its name.
 */
const TITLES = { weak_students: 'Weak Students', haraka: 'Haraka Mistakes', parent_follow_up: 'Parent Follow-up Cases', teacher_completion: "Teachers Missing Today's Records" };
const EMPTY = { weak_students: 'No data.', haraka: 'No data.', parent_follow_up: 'No follow-up cases.', teacher_completion: 'All teachers have submitted today.' };
const UNITS = { hifz_weak_unit: ':count weak sessions' };

export default function ReportRows({ report, rows = [], figure = null, unit = null, tone = null, back_href = '/hifz/reports', t = {} }) {
    const figureOf = (row) => {
        if (!figure || row[figure] === null || row[figure] === undefined) return null;
        return unit ? (t[unit] || UNITS[unit] || ':count').replace(':count', String(row[figure])) : String(row[figure]);
    };

    return (
        <AppShell title={t[`hifz_report_title_${report}`] || TITLES[report] || report}>
            <p className="mb-4 text-sm"><Link href={back_href} className="underline" data-testid="hifz-reports-back">{t.hifz_back_reports || '← Hifz Reports'}</Link></p>
            <div className="rounded-lg border bg-white p-4" data-testid="hifz-report-rows">
                {rows.length === 0 && <p className="text-gray-500">{t[`hifz_report_empty_${report}`] || EMPTY[report] || '—'}</p>}
                {rows.map((row) => (
                    <div key={row.id ?? row.student} className="border-b py-2 last:border-b-0" data-testid="hifz-report-row">
                        <div className="flex justify-between gap-3">
                            <span className="font-medium">{row.student}</span>
                            {figureOf(row) !== null && <span className={tone === 'danger' ? 'font-medium text-red-600' : 'text-gray-600'}>{figureOf(row)}</span>}
                        </div>
                        {row.note && <p className="mt-1 text-sm text-gray-700">{row.note}</p>}
                    </div>
                ))}
            </div>
        </AppShell>
    );
}
