import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

// One section of the checklist. Its title and items are the operator's own
// record (docs/OPERATOR_CHECKLIST.md) and stay as written, as the feature
// walkthrough's do; a refused tick is said under its item.
function Section({ section, checked, refusals }) {
    const toggle = (key) =>
        refusals.actOn(`item:${key}`, () => router.post(`/admin/operations/${key}/toggle`, {}, { preserveScroll: true }));

    return (
        <div className="mb-6 rounded-lg border bg-white">
            <h2 className="border-b bg-[#F3EBE0] px-4 py-2 text-sm font-semibold">{section.title}</h2>
            <ul>
                {section.items.map((item) => {
                    const state = checked[item.key];
                    return (
                        <li key={item.key} className="flex items-start gap-3 border-t px-4 py-2 first:border-t-0">
                            <input
                                type="checkbox"
                                checked={Boolean(state)}
                                onChange={() => toggle(item.key)}
                                aria-label={item.label}
                                className="mt-1 h-4 w-4 rounded border-gray-300"
                            />
                            <div className="text-sm">
                                <p className={state ? 'text-gray-400 line-through' : ''}>{item.label}</p>
                                {state && (
                                    <p className="text-xs text-gray-500">
                                        {state.by ? `${state.by} · ` : ''}
                                        {state.at}
                                    </p>
                                )}
                                <FormErrors errors={refusals.errorsFor(`item:${item.key}`)} className="mt-1" />
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

/**
 * The operator close-out checklist. The page's words are the `admin` book's
 * (slice SY1, STATUS §5qu); a refused tick is said under its item — it was
 * said nowhere.
 */
export default function Operations({ sections, checked, done, total, t = {} }) {
    const refusals = useRowRefusals();

    return (
        <AppShell title={t.ops_title || 'Operations checklist'}>
            <div className="mx-auto max-w-3xl px-4 py-6">
                <div className="mb-6 flex flex-wrap items-center gap-4">
                    <div>
                        <h1 className="text-xl font-bold">{t.ops_heading || 'Operator close-out checklist'}</h1>
                        <p className="text-sm text-gray-500">
                            {t.ops_intro || 'Shared across operators — a tick records who and when. The evidence of record stays in STATUS.md.'}
                        </p>
                    </div>
                    <div className="ms-auto flex items-center gap-3">
                        <span className="text-sm font-medium tabular-nums">
                            {(t.ops_done || ':done of :total done').replace(':done', done).replace(':total', total)}
                        </span>
                        <a href="/admin/operations/export" className="rounded border px-3 py-1 text-sm hover:bg-gray-50">
                            {t.ft_export || 'Export CSV'}
                        </a>
                        <a href="/admin/operations/features" className="rounded border px-3 py-1 text-sm hover:bg-gray-50">
                            {t.ops_features || 'Feature walkthrough'}
                        </a>
                    </div>
                </div>
                <FormErrors errors={refusals.unplaced} className="mb-4" />
                <div className="mb-6 h-2 overflow-hidden rounded bg-gray-200">
                    <div
                        className="h-full rounded bg-emerald-600 transition-all"
                        style={{ width: total ? `${(100 * done) / total}%` : 0 }}
                    />
                </div>
                {sections.map((section) => (
                    <Section key={section.key} section={section} checked={checked} refusals={refusals} />
                ))}
            </div>
        </AppShell>
    );
}
