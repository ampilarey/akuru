import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

// BP3 (BACKLOG B11): reports that a work copies someone's own. The office
// upholds one (the work leaves the shelf, its writer is told) or dismisses
// it, and says why either way. The words are the `library_copyright` book.
export default function CopyrightReports({ reports = [], open_only = true, open_count = 0, t = {} }) {
    const refusals = useRowRefusals();
    const [notes, setNotes] = useState({});
    const decide = (id, outcome) => refusals.actOn(`report:${id}`, () => router.post(`/admin/library/copyright/${id}`, { outcome, note: notes[id] ?? '' }, { preserveScroll: true }));
    const statusLabel = (status) => t[`status_${status}`] || status;

    return (
        <AppShell title={t.title || 'Copyright reports'}>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <div data-phone-main>
                <p className="mb-3 text-sm text-gray-600">{t.intro}</p>

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <button type="button" className="btn-secondary" data-testid="copyright-toggle" onClick={() => router.get(`/admin/library/copyright${open_only ? '?all=1' : ''}`)}>
                        {open_only ? t.show_all || 'Show decided too' : t.show_open || 'Show open only'}
                    </button>
                    <a className="btn-secondary" href={`/admin/library/copyright/export${open_only ? '' : '?all=1'}`}>{t.export_csv || 'Export CSV'}</a>
                    <span className="text-sm text-gray-600" data-testid="copyright-open-count">{statusLabel('open')}: {open_count}</span>
                    <Link className="text-sm text-[#7C2D37] hover:underline" href="/admin/library" data-testid="back-link">{t.back || '← Library office'}</Link>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="table-stack min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.col_work || 'Work'}</th>
                                <th className="px-3 py-2">{t.col_reporter || 'Reported by'}</th>
                                <th className="px-3 py-2">{t.col_claim || 'Their work, and what is copied'}</th>
                                <th className="px-3 py-2">{t.col_status || 'State'}</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {reports.length === 0 && (
                                <tr>
                                    <td className="px-3 py-6 text-center text-gray-500" colSpan={5}>{t.none || 'No copyright reports.'}</td>
                                </tr>
                            )}
                            {reports.map((report) => (
                                <tr key={report.id} className="border-t align-top" data-testid={`copyright-report-${report.id}`}>
                                    <td data-label={t.col_work || 'Work'} className="px-3 py-2">
                                        {report.item_slug ? <a className="text-[#7C2D37] hover:underline" href={`/library/${report.item_slug}`}>{report.item_title}</a> : report.item_title ?? '—'}
                                    </td>
                                    <td data-label={t.col_reporter || 'Reported by'} className="px-3 py-2">
                                        {report.name}
                                        <span className="block text-xs text-gray-500" dir="ltr">{report.email}</span>
                                        <span className="block text-xs text-gray-500">{report.reported_at}</span>
                                    </td>
                                    <td data-label={t.col_claim || 'Their work, and what is copied'} className="px-3 py-2">
                                        <strong className="block">{report.work_claimed}</strong>
                                        <span className="whitespace-pre-line">{report.statement}</span>
                                    </td>
                                    <td data-label={t.col_status || 'State'} className="px-3 py-2">
                                        {statusLabel(report.status)}
                                        {report.decision_note && <p className="mt-1 text-xs text-gray-500">{report.decision_note}</p>}
                                    </td>
                                    <td className="table-actions px-3 py-2">
                                        {report.status === 'open' && (
                                            <div className="grid gap-2">
                                                <label className="block text-xs text-gray-600">
                                                    {t.note || 'Why'}
                                                    <textarea
                                                        rows={2}
                                                        maxLength={1000}
                                                        className="mt-1 block w-full rounded border-gray-300 text-sm"
                                                        data-testid={`copyright-note-${report.id}`}
                                                        value={notes[report.id] ?? ''}
                                                        onChange={(e) => setNotes({ ...notes, [report.id]: e.target.value })}
                                                    />
                                                </label>
                                                <div className="flex flex-wrap gap-2">
                                                    <button type="button" className="btn-secondary text-xs" data-testid={`copyright-uphold-${report.id}`} onClick={() => decide(report.id, 'upheld')}>{t.uphold || 'Uphold and take down'}</button>
                                                    <button type="button" className="btn-secondary text-xs" data-testid={`copyright-dismiss-${report.id}`} onClick={() => decide(report.id, 'dismissed')}>{t.dismiss || 'Dismiss'}</button>
                                                </div>
                                            </div>
                                        )}
                                        <FormErrors errors={refusals.errorsFor(`report:${report.id}`)} className="mt-1" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppShell>
    );
}
