import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * Feature testing (System → Feature testing): every main feature, by who
 * uses it. A tester marks each one working, broken or blocked with a
 * comment; every mark is kept and shown as the feature's history, so what
 * was found, by whom and when stays readable later (the owner, 2026-09-28).
 *
 * The sections fold (STATUS §5no): 154 items drew at once — 1,349 DOM
 * nodes, 16,400px on a phone, nineteen screens of scrolling
 * (docs/ADMIN_PANEL.md §7 P5, M6). Each section is now a heading with
 * its count; it opens on a tap, and a filter opens the sections it finds
 * something in. Nothing about an item changed.
 */
const BADGE = {
    works: 'bg-green-100 text-green-800 border-green-200',
    broken: 'bg-red-100 text-red-800 border-red-200',
    blocked: 'bg-amber-100 text-amber-900 border-amber-200',
    untested: 'bg-gray-100 text-gray-600 border-gray-200',
};
const MARK = { works: '✓', broken: '✗', blocked: '⏸', untested: '•' };

function Badge({ status, t }) {
    return (
        <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ${BADGE[status]}`}>
            {MARK[status]} {t[`ft_${status}`] || status}
        </span>
    );
}

function Item({ item, result, history, t }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ status: result?.status || 'works', comment: '' });
    const status = result?.status || 'untested';
    const isPath = item.where.startsWith('/');
    const save = (event) => {
        event.preventDefault();
        form.post(`/admin/operations/features/${item.key}`, { preserveScroll: true, onSuccess: () => form.reset('comment') });
    };

    return (
        <li className="border-t px-4 py-3 first:border-t-0" data-testid={`feature-${item.key}`} data-status={status}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium">{item.label}</p>
                    <p className="text-xs text-gray-500">
                        {isPath ? <a href={item.where} target="_blank" rel="noreferrer" className="text-[#7C2D37] underline">{item.where}</a> : <span className="font-mono">{item.where}</span>}
                    </p>
                </div>
                <button type="button" onClick={() => setOpen((v) => !v)} aria-expanded={open} className="flex min-h-[2.75rem] shrink-0 items-center sm:min-h-0" data-testid={`feature-open-${item.key}`}>
                    <Badge status={status} t={t} />
                </button>
            </div>
            {result && (
                <p className="mt-1 text-xs text-gray-600">
                    {result.comment && <span className="block whitespace-pre-line text-gray-800">{result.comment}</span>}
                    {result.by ? (t.ft_by || 'by :name').replace(':name', result.by) : ''} · {result.at}
                </p>
            )}
            {open && (
                <div className="mt-3 rounded border bg-[#FDFBF8] p-3">
                    <form onSubmit={save} data-testid={`feature-form-${item.key}`}>
                        <FormErrors errors={form.errors} className="mb-2" />
                        <div className="mb-2 flex flex-wrap gap-2" role="radiogroup">
                            {['works', 'broken', 'blocked'].map((s) => (
                                <label key={s} className={`cursor-pointer rounded-full border px-3 py-1 text-sm ${form.data.status === s ? BADGE[s] + ' font-semibold' : 'bg-white'}`}>
                                    <input type="radio" name={`status-${item.key}`} value={s} checked={form.data.status === s} onChange={() => form.setData('status', s)} className="sr-only" data-testid={`feature-status-${item.key}-${s}`} />
                                    {MARK[s]} {t[`ft_${s}`] || s}
                                </label>
                            ))}
                        </div>
                        <label className="block text-xs font-medium text-gray-700">
                            {t.ft_comment}
                            <textarea rows={3} maxLength={2000} value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} className="mt-1 w-full rounded border-gray-300 text-base sm:text-sm" data-testid={`feature-comment-${item.key}`} />
                            <span className="font-normal text-gray-500">{t.ft_comment_hint}</span>
                        </label>
                        <button type="submit" disabled={form.processing} className="btn-primary mt-2" data-testid="feature-save">{t.ft_save || 'Save'}</button>
                    </form>
                    {history.length > 0 && (
                        <div className="mt-3" data-testid={`feature-history-${item.key}`}>
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{t.ft_history} ({history.length})</p>
                            <ol className="space-y-2 text-xs">
                                {history.map((entry, index) => (
                                    <li key={index} className="rounded border bg-white p-2">
                                        <Badge status={entry.status} t={t} /> <span className="text-gray-500">{entry.by ? `${entry.by} · ` : ''}{entry.at}</span>
                                        {entry.comment && <p className="mt-1 whitespace-pre-line text-gray-800">{entry.comment}</p>}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}
                </div>
            )}
        </li>
    );
}

export default function Features({ sections = [], results = {}, history = {}, counts = {}, total = 0, t = {} }) {
    const [filter, setFilter] = useState('all');
    const [opened, setOpened] = useState(() => new Set());
    const [allOpen, setAllOpen] = useState(false);
    const tested = total - (counts.untested ?? total);
    const shown = (item) => filter === 'all' || (results[item.key]?.status || 'untested') === filter;
    const statusOf = (item) => results[item.key]?.status || 'untested';
    const toggle = (key) => setOpened((prev) => {
        const next = new Set(prev);
        if (next.has(key)) next.delete(key); else next.add(key);

        return next;
    });
    // A section is open when the tester opened it, when every section is
    // open, or when a filter other than "all" finds something in it.
    const isOpen = (section, items) => allOpen || opened.has(section.key) || (filter !== 'all' && items.length > 0);

    return (
        <AppShell title={t.ft_title || 'Feature testing'}>
            <p className="mb-4 max-w-3xl text-sm text-gray-600">{t.ft_intro}</p>
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium tabular-nums" data-testid="feature-progress">
                    {(t.ft_progress || ':done of :total tested').replace(':done', tested).replace(':total', total)}
                </span>
                <a href="/admin/operations/features/export" className="chip-link" data-testid="export-csv">{t.ft_export}</a>
                <a href="/admin/operations" className="chip-link">{t.ft_close_out}</a>
                <button type="button" className="chip-link" onClick={() => { setAllOpen((v) => !v); setOpened(new Set()); }} aria-pressed={allOpen} data-testid="feature-open-all">
                    {allOpen ? (t.ft_close_all || 'Close all') : (t.ft_open_all || 'Open all')}
                </button>
            </div>
            <div className="mb-3 flex h-2 overflow-hidden rounded bg-gray-200" aria-hidden="true">
                <div className="bg-green-600" style={{ width: `${total ? (100 * (counts.works || 0)) / total : 0}%` }} />
                <div className="bg-red-500" style={{ width: `${total ? (100 * (counts.broken || 0)) / total : 0}%` }} />
                <div className="bg-amber-400" style={{ width: `${total ? (100 * (counts.blocked || 0)) / total : 0}%` }} />
            </div>
            <div className="mb-5 flex flex-wrap gap-2" data-testid="feature-filters">
                {['all', 'untested', 'broken', 'blocked', 'works'].map((f) => (
                    <button key={f} type="button" onClick={() => setFilter(f)} aria-pressed={filter === f}
                        className={`inline-flex min-h-[2.75rem] items-center rounded-full border px-3 py-1 text-sm sm:min-h-0 ${filter === f ? 'bg-[#7C2D37] text-white' : 'bg-white text-gray-700'}`} data-testid={`filter-${f}`}>
                        {t[`ft_${f}`] || f} {f === 'all' ? `(${total})` : `(${counts[f] ?? 0})`}
                    </button>
                ))}
            </div>
            {sections.map((section) => {
                const items = section.items.filter(shown);
                if (items.length === 0) {
                    return null;
                }
                const open = isOpen(section, items);
                const done = section.items.filter((item) => statusOf(item) !== 'untested').length;
                const broken = section.items.filter((item) => statusOf(item) === 'broken').length;

                return (
                    <section key={section.key} className="mb-3 overflow-hidden rounded-lg border bg-white" data-testid={`feature-section-${section.key}`} data-open={open ? 'true' : 'false'}>
                        <h2 className="bg-[#F3EBE0] text-sm font-semibold">
                            <button type="button" onClick={() => toggle(section.key)} aria-expanded={open} aria-controls={`feature-list-${section.key}`} className="flex min-h-[2.75rem] w-full items-center justify-between gap-3 px-4 py-2 text-start" data-testid={`feature-section-toggle-${section.key}`}>
                                <span className="min-w-0">{section.title}</span>
                                <span className="flex shrink-0 items-center gap-2 text-xs font-normal text-gray-600">
                                    <span className="tabular-nums">{(t.ft_progress || ':done of :total tested').replace(':done', done).replace(':total', section.items.length)}</span>
                                    {broken > 0 && <span className={`rounded-full border px-2 py-0.5 ${BADGE.broken}`}>{MARK.broken} {broken}</span>}
                                    <span aria-hidden="true">{open ? '▴' : '▾'}</span>
                                </span>
                            </button>
                        </h2>
                        {open && (
                            <ul id={`feature-list-${section.key}`}>
                                {items.map((item) => (
                                    <Item key={item.key} item={item} result={results[item.key]} history={history[item.key] || []} t={t} />
                                ))}
                            </ul>
                        )}
                    </section>
                );
            })}
        </AppShell>
    );
}
