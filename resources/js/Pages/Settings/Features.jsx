import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function Section({ section, checked }) {
    const toggle = (key) =>
        router.post(`/admin/operations/${key}/toggle`, {}, { preserveScroll: true });

    return (
        <div className="mb-6 rounded-lg border bg-white">
            <h2 className="border-b bg-[#F3EBE0] px-4 py-2 text-sm font-semibold">{section.title}</h2>
            <ul>
                {section.items.map((item) => {
                    const state = checked[item.key];
                    const isPath = item.where.startsWith('/');
                    return (
                        <li key={item.key} className="flex items-start gap-3 border-t px-4 py-2 first:border-t-0">
                            <input
                                type="checkbox"
                                checked={Boolean(state)}
                                onChange={() => toggle(item.key)}
                                className="mt-1 h-4 w-4 rounded border-gray-300"
                            />
                            <div className="text-sm">
                                <p className={state ? 'text-gray-400 line-through' : ''}>{item.label}</p>
                                <p className="text-xs text-gray-500">
                                    {isPath && !item.where.includes('{') ? (
                                        <a href={item.where} className="text-emerald-700 hover:underline">
                                            {item.where}
                                        </a>
                                    ) : (
                                        <span className="font-mono">{item.where}</span>
                                    )}
                                    {state && (
                                        <>
                                            {' · '}
                                            {state.by ? `${state.by} · ` : ''}
                                            {state.at}
                                        </>
                                    )}
                                </p>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export default function Features({ sections, checked, done, total }) {
    return (
        <AppShell title="Feature walkthrough">
            <div className="mx-auto max-w-3xl px-4 py-6">
                <div className="mb-6 flex flex-wrap items-center gap-4">
                    <div>
                        <h1 className="text-xl font-bold">Platform feature walkthrough</h1>
                        <p className="text-sm text-gray-500">
                            Everything built, by area — what to try and where. A tick means a person
                            walked it in a browser and it worked. Shared across operators.
                        </p>
                    </div>
                    <div className="ms-auto flex items-center gap-3">
                        <span className="text-sm font-medium tabular-nums">
                            {done} / {total} done
                        </span>
                        <a
                            href="/admin/operations/features/export"
                            className="rounded border px-3 py-1 text-sm hover:bg-gray-50"
                        >
                            CSV
                        </a>
                        <a
                            href="/admin/operations"
                            className="rounded border px-3 py-1 text-sm hover:bg-gray-50"
                        >
                            Close-out checklist
                        </a>
                    </div>
                </div>
                <div className="mb-6 h-2 overflow-hidden rounded bg-gray-200">
                    <div
                        className="h-full rounded bg-emerald-600 transition-all"
                        style={{ width: total ? `${(100 * done) / total}%` : 0 }}
                    />
                </div>
                {sections.map((section) => (
                    <Section key={section.key} section={section} checked={checked} />
                ))}
            </div>
        </AppShell>
    );
}
