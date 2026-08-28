import { router } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import AppShell from '../../Layouts/AppShell';

function Row({ group, item, suggestAvailable }) {
    const [draft, setDraft] = useState(item.override ?? '');
    const [saving, setSaving] = useState(false);
    const [suggesting, setSuggesting] = useState(false);
    const dirty = draft !== (item.override ?? '');

    const save = () => {
        setSaving(true);
        router.post(
            '/admin/translations/save',
            { group, key: item.key, value: draft },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const suggest = async () => {
        setSuggesting(true);
        try {
            const { data } = await axios.post('/admin/translations/suggest', { group, key: item.key });
            if (data.suggestion) setDraft(data.suggestion);
        } finally {
            setSuggesting(false);
        }
    };

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <p className="font-mono text-xs text-gray-500">{item.key}</p>
                <p className="text-sm">{item.en}</p>
            </td>
            <td className="px-3 py-2 text-sm" dir="rtl">
                <span className={item.suspect ? 'rounded bg-amber-50 px-1 text-amber-800' : ''}>
                    {item.file_dv || <span className="text-gray-400">—</span>}
                </span>
            </td>
            <td className="px-3 py-2">
                <div className="flex items-start gap-2">
                    <textarea
                        dir="rtl"
                        rows={1}
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        placeholder={item.file_dv || ''}
                        className="w-full rounded border px-2 py-1 text-sm"
                    />
                    {suggestAvailable && (
                        <button
                            onClick={suggest}
                            disabled={suggesting}
                            title="Prefill a machine draft — you still review and save"
                            className="rounded border px-2 py-1 text-xs disabled:opacity-40"
                        >
                            Suggest
                        </button>
                    )}
                    <button
                        onClick={save}
                        disabled={!dirty || saving}
                        className="rounded bg-emerald-700 px-2 py-1 text-xs font-medium text-white disabled:opacity-40"
                    >
                        {item.override && draft === '' ? 'Clear' : 'Save'}
                    </button>
                </div>
                {item.override && !dirty && (
                    <p className="mt-1 text-xs text-emerald-700">Override active — clearing restores the file value.</p>
                )}
            </td>
        </tr>
    );
}

export default function Translations({ groups, override_count, total, suggest_available }) {
    const [query, setQuery] = useState('');
    const [suspectOnly, setSuspectOnly] = useState(false);
    const [activeGroup, setActiveGroup] = useState(groups[0]?.group ?? 'common');

    const visible = useMemo(() => {
        const g = groups.find((x) => x.group === activeGroup);
        if (!g) return [];
        const q = query.trim().toLowerCase();
        return g.items.filter((item) => {
            if (suspectOnly && !item.suspect) return false;
            if (!q) return true;
            return (
                item.key.toLowerCase().includes(q) ||
                item.en.toLowerCase().includes(q) ||
                (item.file_dv || '').includes(query.trim()) ||
                (item.override || '').includes(query.trim())
            );
        });
    }, [groups, activeGroup, query, suspectOnly]);

    return (
        <AppShell title="Dhivehi translations">
            <div className="mx-auto max-w-5xl px-4 py-6">
                <div className="mb-4 flex flex-wrap items-center gap-4">
                    <div>
                        <h1 className="text-xl font-bold">Dhivehi translations</h1>
                        <p className="text-sm text-gray-500">
                            Corrections saved here go live immediately and win over the shipped file
                            strings. Clearing a correction restores the file value.
                        </p>
                    </div>
                    <div className="ms-auto flex items-center gap-3">
                        <span className="text-sm tabular-nums">{override_count} corrections · {total} strings</span>
                        <a href="/admin/translations/export" className="rounded border px-3 py-1 text-sm hover:bg-gray-50">
                            CSV
                        </a>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    {groups.map((g) => (
                        <button
                            key={g.group}
                            onClick={() => setActiveGroup(g.group)}
                            className={`rounded-full border px-3 py-1 text-sm ${
                                g.group === activeGroup ? 'border-emerald-700 bg-emerald-700 text-white' : 'hover:bg-gray-50'
                            }`}
                        >
                            {g.group} ({g.items.length})
                        </button>
                    ))}
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search key, English, or Dhivehi…"
                        className="ms-auto w-64 rounded border px-3 py-1 text-sm"
                    />
                    <label className="flex items-center gap-1 text-sm">
                        <input
                            type="checkbox"
                            checked={suspectOnly}
                            onChange={(e) => setSuspectOnly(e.target.checked)}
                        />
                        Suspect only
                    </label>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0]">
                            <tr>
                                <th className="px-3 py-2 text-start">English</th>
                                <th className="px-3 py-2 text-start">File Dhivehi</th>
                                <th className="px-3 py-2 text-start">Correction</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((item) => (
                                <Row
                                    key={`${activeGroup}.${item.key}`}
                                    group={activeGroup}
                                    item={item}
                                    suggestAvailable={Boolean(suggest_available)}
                                />
                            ))}
                        </tbody>
                    </table>
                    {visible.length === 0 && (
                        <p className="px-4 py-6 text-center text-sm text-gray-500">No strings match.</p>
                    )}
                </div>
            </div>
        </AppShell>
    );
}
