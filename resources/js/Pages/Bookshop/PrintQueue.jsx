import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * NAME_TAGS_PLAN §4.8 (NT4): the print desk's queue. One card per job on a
 * paid order — oldest first, with its due date — and the four steps:
 * *Printing*, *Finished*, *Reprint* (with a reason, no charge) and
 * *Problem* (the order needs attention, with the desk's note). The print
 * file is the office's to download; the customer never gets it.
 */
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');
const badge = { ordered: 'bg-amber-100 text-amber-900', printing: 'bg-blue-100 text-blue-900', finished: 'bg-green-100 text-green-900', cancelled: 'bg-gray-100 text-gray-700' };

/** One job on the queue — shared with a printing shop's desk (PP7b), which passes its own `base`. */
export function Job({ t, row, base = '/admin/bookshop/print' }) {
    const [ask, setAsk] = useState(null);
    const [text, setText] = useState('');
    const post = (step, data = {}) => router.post(`${base}/jobs/${row.id}/${step}`, data, { preserveScroll: true, onSuccess: () => { setAsk(null); setText(''); } });

    return (
        <li className="rounded-lg border bg-white p-3" data-testid={`queue-job-${row.id}`}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium" dir="auto">{row.name || fill(t.queue_job_number, { id: row.id })}</p>
                <span className={`rounded px-2 py-0.5 text-xs ${badge[row.status] ?? 'bg-gray-100'}`} data-testid={`queue-status-${row.id}`}>{t[`job_status_${row.status}`] ?? row.status}</span>
            </div>
            <dl className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-sm sm:grid-cols-4">
                <div><dt className="text-xs text-gray-500">{t.queue_order}</dt><dd>{row.order}</dd></div>
                <div><dt className="text-xs text-gray-500">{t.queue_customer}</dt><dd dir="auto">{row.customer}{row.phone && <a href={`tel:${row.phone}`} className="block text-blue-700 underline">{row.phone}</a>}</dd></div>
                <div><dt className="text-xs text-gray-500">{t.queue_delivery}</dt><dd dir="auto">{row.delivery}</dd></div>
                <div><dt className="text-xs text-gray-500">{t.queue_due}</dt><dd data-testid={`queue-due-${row.id}`}>{row.due ?? '—'}</dd></div>
                <div><dt className="text-xs text-gray-500">{t.queue_sheets}</dt><dd>{row.sheets}</dd></div>
                <div><dt className="text-xs text-gray-500">{t.queue_paper}</dt><dd dir="auto">{row.paper} · {row.colour ? t.colour : t.bw_print}</dd></div>
                <div><dt className="text-xs text-gray-500">{t.queue_finishing}</dt><dd dir="auto">{row.finishing.length ? row.finishing.join(', ') : '—'}</dd></div>
                <div><dt className="text-xs text-gray-500">{t.queue_total}</dt><dd>{row.total.toFixed(2)}</dd></div>
            </dl>
            {row.reprints > 0 && <p className="mt-1 text-xs text-gray-600">{fill(t.queue_reprints, { count: row.reprints })}</p>}
            <div className="mt-3 flex flex-wrap gap-2">
                {row.file && <a href={`${base}/jobs/${row.id}/file`} className="btn-secondary text-sm" data-testid={`queue-file-${row.id}`}>{t.queue_print_file}</a>}
                {row.status === 'ordered' && <button type="button" className="btn-primary text-sm" onClick={() => post('start')} data-testid={`queue-start-${row.id}`}>{t.queue_start}</button>}
                {row.status === 'printing' && <button type="button" className="btn-primary text-sm" onClick={() => post('finish')} data-testid={`queue-finish-${row.id}`}>{t.queue_finish}</button>}
                {['printing', 'finished'].includes(row.status) && <button type="button" className="btn-secondary text-sm" onClick={() => setAsk('reprint')} data-testid={`queue-reprint-${row.id}`}>{t.queue_reprint}</button>}
                {['ordered', 'printing', 'finished'].includes(row.status) && <button type="button" className="text-sm text-red-700 underline" onClick={() => setAsk('problem')} data-testid={`queue-problem-${row.id}`}>{t.queue_problem}</button>}
            </div>
            {ask && (
                <form className="mt-2 flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); post(ask, ask === 'reprint' ? { reason: text } : { note: text }); }}>
                    <label className="min-w-0 flex-1 text-sm">{ask === 'reprint' ? t.queue_reprint_reason : t.queue_problem_note}
                        <input className="form-input w-full" maxLength={500} value={text} onChange={(e) => setText(e.target.value)} required data-testid={`queue-text-${row.id}`} />
                    </label>
                    <button type="submit" className="btn-primary text-sm" data-testid={`queue-send-${row.id}`}>{ask === 'reprint' ? t.queue_reprint : t.queue_problem}</button>
                    <button type="button" className="text-sm underline" onClick={() => setAsk(null)}>{t.cancel_edit}</button>
                </form>
            )}
        </li>
    );
}

export default function PrintQueue({ t = {}, rows = [], all = false }) {
    const { errors } = usePage().props;

    return (
        <AppShell title={t.queue_title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-3 text-sm text-gray-600">{t.queue_intro} <Link href="/admin/bookshop/print" className="text-blue-700 underline">{t.title} →</Link></p>
            <div className="mb-3 flex flex-wrap items-center gap-3">
                <Link href={all ? '/admin/bookshop/print/queue' : '/admin/bookshop/print/queue?all=1'} className="text-sm text-blue-700 underline" data-testid="queue-toggle-all">{all ? t.queue_show_open : t.queue_show_all}</Link>
                <a href={`/admin/bookshop/print/queue/export${all ? '?all=1' : ''}`} className="btn-secondary text-sm" data-testid="queue-export">{t.export_csv}</a>
            </div>
            {rows.length === 0 ? <p className="text-sm text-gray-600" data-testid="queue-empty">{t.queue_empty}</p> : (
                <ul className="grid gap-3">{rows.map((row) => <Job key={row.id} t={t} row={row} />)}</ul>
            )}
        </AppShell>
    );
}
