import axios from 'axios';
import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '../../Layouts/AppShell';

const LANGUAGES = {
    dv: { label: 'Dhivehi', native: 'ދިވެހި' },
    ar: { label: 'Arabic', native: 'العربية' },
};

function Row({ group, item, locale, suggestAvailable }) {
    const [draft, setDraft] = useState(item.override ?? '');
    const [saving, setSaving] = useState(false);
    const [suggesting, setSuggesting] = useState(false);
    const dirty = draft !== (item.override ?? '');

    const save = () => {
        setSaving(true);
        router.post(
            '/admin/translations/save',
            { group, key: item.key, value: draft, locale },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const suggest = async () => {
        setSuggesting(true);
        try {
            const { data } = await axios.post('/admin/translations/suggest', { group, key: item.key, locale });
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
                    {item.file_value || <span className="text-gray-400">—</span>}
                </span>
            </td>
            <td className="px-3 py-2">
                <div className="flex items-start gap-2">
                    {/* `text-base` below `sm`: a 14px control makes iOS zoom the page on focus (ADMIN_PANEL.md §7 M4). */}
                    <textarea
                        dir="rtl"
                        rows={1}
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        placeholder={item.file_value || ''}
                        aria-label={`Correction for ${item.key}`}
                        className="w-full rounded border px-2 py-1 text-base sm:text-sm"
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

/**
 * The translation editor (System → Translations). Since STATUS §5no the
 * group chips, the search and the suspect filter are the URL
 * (`?group=&q=&suspect=`) and the server answers with 25 rows of the
 * active group: the whole catalog (743 rows, 132 KB) used to travel on
 * every visit and the active group's 185 rows drew at once, each a
 * textarea — 1,949 DOM nodes, 11,400px on a phone (ADMIN_PANEL.md §7 P5).
 * The CSV still carries everything.
 */
export default function Translations({ groups = [], items = [], active_group, pagination = null, filters = {}, override_count, total, locale, locales = ['dv'], suggest_available }) {
    const [query, setQuery] = useState(filters.q ?? '');
    const language = LANGUAGES[locale] ?? { label: locale, native: locale };
    const first = useRef(true);

    const visit = (changes) => {
        const params = { locale, group: active_group, q: filters.q || undefined, suspect: filters.suspect ? 1 : undefined, ...changes };
        Object.keys(params).forEach((k) => (params[k] === undefined || params[k] === '' || params[k] === false) && delete params[k]);
        router.get('/admin/translations', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Typing searches after a pause, so the server is asked once per thought and not per keystroke.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return undefined;
        }
        const handle = window.setTimeout(() => {
            if (query.trim() !== (filters.q ?? '')) {
                visit({ q: query.trim() || undefined });
            }
        }, 400);

        return () => window.clearTimeout(handle);
    }, [query]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <AppShell title={`${language.label} translations`}>
            <div className="mx-auto max-w-5xl py-2 sm:px-4 sm:py-6">
                <div className="mb-4 flex flex-wrap items-center gap-4">
                    <div>
                        <p className="text-sm text-gray-500">
                            Corrections saved here go live immediately and win over the shipped file
                            strings. Clearing a correction restores the file value.
                        </p>
                    </div>
                    <div className="ms-auto flex items-center gap-3">
                        <span className="text-sm tabular-nums">{override_count} corrections · {total} strings</span>
                        <a
                            href={`/admin/translations/export?locale=${locale}`}
                            className="rounded border px-3 py-1 text-sm hover:bg-gray-50"
                        >
                            CSV
                        </a>
                    </div>
                </div>

                {/* Each language keeps its own corrections, so switching is a
                    full page load rather than a client-side filter. */}
                <div className="mb-4 flex items-center gap-2">
                    <span className="text-sm text-gray-500">Language</span>
                    {locales.map((code) => (
                        <a
                            key={code}
                            href={`/admin/translations?locale=${code}`}
                            className={`inline-flex min-h-[2rem] items-center rounded-full border px-3 py-1 text-sm ${
                                code === locale ? 'border-emerald-700 bg-emerald-700 text-white' : 'hover:bg-gray-50'
                            }`}
                        >
                            {(LANGUAGES[code] ?? { label: code }).label}
                            <span className="ms-1 opacity-70">{(LANGUAGES[code] ?? {}).native}</span>
                        </a>
                    ))}
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    {groups.map((g) => (
                        <Link
                            key={g.group}
                            href={`/admin/translations?locale=${locale}&group=${g.group}${filters.q ? `&q=${encodeURIComponent(filters.q)}` : ''}${filters.suspect ? '&suspect=1' : ''}`}
                            preserveScroll
                            className={`inline-flex min-h-[2rem] items-center rounded-full border px-3 py-1 text-sm ${
                                g.group === active_group ? 'border-emerald-700 bg-emerald-700 text-white' : 'hover:bg-gray-50'
                            }`}
                            data-testid={`group-${g.group}`}
                        >
                            {g.group} ({g.count}){g.suspect > 0 && <span className="ms-1 opacity-70" title="suspect">· {g.suspect}</span>}
                        </Link>
                    ))}
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={`Search key, English, or ${language.label}…`}
                        aria-label={`Search key, English, or ${language.label}`}
                        className="w-full rounded border px-3 py-1 text-base sm:ms-auto sm:w-64 sm:text-sm"
                        data-testid="translations-search"
                    />
                    <label className="flex min-h-[2rem] items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="h-5 w-5"
                            checked={Boolean(filters.suspect)}
                            onChange={(e) => visit({ suspect: e.target.checked ? 1 : undefined })}
                            data-testid="suspect-only"
                        />
                        Suspect only
                    </label>
                </div>

                <p className="mb-2 text-sm text-gray-600" data-testid="translations-showing">
                    {pagination ? `${items.length} of ${pagination.total} strings in ${active_group}` : ''}
                </p>

                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0]">
                            <tr>
                                <th className="px-3 py-2 text-start">English</th>
                                <th className="px-3 py-2 text-start">File {language.label}</th>
                                <th className="px-3 py-2 text-start">Correction</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <Row
                                    key={`${active_group}.${item.key}`}
                                    group={active_group}
                                    item={item}
                                    locale={locale}
                                    suggestAvailable={Boolean(suggest_available)}
                                />
                            ))}
                        </tbody>
                    </table>
                    {items.length === 0 && (
                        <p className="px-4 py-6 text-center text-sm text-gray-500">No strings match.</p>
                    )}
                </div>

                {pagination && pagination.last_page > 1 && (
                    <nav className="mt-4 flex items-center gap-3 text-sm" aria-label="Pages" data-testid="translations-pagination">
                        {pagination.prev ? <Link href={pagination.prev} preserveScroll className="btn-secondary min-h-[2.75rem] sm:min-h-0">‹ Previous</Link> : <span className="btn-secondary opacity-50">‹ Previous</span>}
                        <span className="text-gray-600">Page {pagination.current_page} of {pagination.last_page}</span>
                        {pagination.next ? <Link href={pagination.next} preserveScroll className="btn-secondary min-h-[2.75rem] sm:min-h-0">Next ›</Link> : <span className="btn-secondary opacity-50">Next ›</span>}
                    </nav>
                )}
            </div>
        </AppShell>
    );
}
