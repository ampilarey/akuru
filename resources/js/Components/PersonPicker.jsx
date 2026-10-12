import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Choose a person by name instead of typing their id (BACKLOG C22, BH1).
 *
 * The page's controller answers `param` with `prop` — a short list of
 * matches — on a partial reload, so each page keeps its own gate. A match is
 * `{ id, name, meta?, indistinguishable? }`; `indistinguishable` marks two
 * pupils with the same name, date of birth and ID card (People's
 * `SearchRosterCandidatesAction`), so whoever chooses checks the number.
 */
export default function PersonPicker({ label, placeholder, param, prop, matches = [], value, onChoose, error, sameName, testId = 'person-picker', describe }) {
    const [query, setQuery] = useState('');
    const [chosen, setChosen] = useState(null);
    const timer = useRef(null);

    // A form reset clears the choice too.
    useEffect(() => {
        if (value === '' || value === null || value === undefined) setChosen(null);
    }, [value]);

    useEffect(() => () => clearTimeout(timer.current), []);

    const search = (text) => {
        setQuery(text);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            const params = Object.fromEntries(new URLSearchParams(window.location.search));
            router.get(window.location.pathname, { ...params, [param]: text }, { only: [prop], preserveState: true, preserveScroll: true, replace: true });
        }, 250);
    };

    const pick = (match) => {
        setChosen(match);
        onChoose(match.id);
    };

    const meta = (match) => (describe ? describe(match) : match.meta);

    return (
        <div className="block text-sm" data-testid={testId}>
            <label className="block">
                <span className="mb-1 block text-gray-600">{label}</span>
                <input className="form-input w-full" type="search" aria-label={label} placeholder={placeholder} value={query} onChange={(e) => search(e.target.value)} data-testid={`${testId}-search`} />
            </label>

            {chosen && `${chosen.id}` === `${value}` && (
                <p className="mt-1 text-xs text-gray-700" data-testid={`${testId}-chosen`}>
                    <span aria-hidden="true">✓ </span>
                    {chosen.name}
                    {meta(chosen) && <span className="ms-2 text-gray-500">{meta(chosen)}</span>}
                </p>
            )}

            {query.trim().length >= 2 && matches.length > 0 && (
                <ul className="mt-1 grid max-h-48 gap-1 overflow-y-auto rounded border bg-white p-1" data-testid={`${testId}-matches`}>
                    {matches.map((match) => (
                        <li key={match.id}>
                            <button
                                type="button"
                                className={`w-full rounded px-2 py-1 text-start hover:bg-gray-50 ${`${value}` === `${match.id}` ? 'bg-blue-50' : ''}`}
                                onClick={() => pick(match)}
                                aria-pressed={`${value}` === `${match.id}`}
                            >
                                {match.name}
                                {meta(match) && <span className="ms-2 text-xs text-gray-500">{meta(match)}</span>}
                                {match.indistinguishable && sameName && (
                                    <span className="ms-2 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">{sameName}</span>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {error && <span className="text-xs text-red-600">{error}</span>}
        </div>
    );
}
