import { usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * What came back when the server said no.
 *
 * 28 of the 92 Inertia pages that submit a form never referenced `errors` at
 * all, so a refused save was completely silent: no row, no message, and the
 * typed values still sitting in the boxes. From the user's side that is
 * indistinguishable from a save that worked and a table that has not refreshed
 * (STATUS §5cn).
 *
 * The pages that *do* report errors use two house styles — `<Field
 * error={form.errors.x}>` in the Academics screens, an inline
 * `{form.errors.x && <span>}` elsewhere. Both put the message beside the field
 * it belongs to, which is better than this whenever the form has labelled
 * fields to hang them on. This component is for the rest: compact inline grids
 * and toolbars where there is nowhere to put a per-field message, and where the
 * choice was previously between a list like this one and nothing at all.
 *
 * Renders nothing when there is nothing to say, so it is safe to drop into any
 * form unconditionally.
 *
 * `except` names fields an element beside it is already saying, so a form can
 * keep its inline messages and list only the rest (slice CT6b-2b).
 */
export default function FormErrors({ errors, except = [], className = '' }) {
    const messages = Object.entries(errors ?? {}).filter(([field]) => !except.includes(field));

    if (messages.length === 0) {
        return null;
    }

    return (
        <ul className={`list-disc ps-5 text-xs text-red-600 ${className}`.trim()} role="alert">
            {messages.map(([field, message]) => (
                <li key={field}>{message}</li>
            ))}
        </ul>
    );
}

/**
 * The refusals of buttons that post with `router` rather than a form, said
 * beside the row the button was on (slice CT6b-2b).
 *
 * Such a refusal comes back as the page's errors with no word on which row it
 * came from, and no form owns it: the course list's Submit review, the
 * outline's Move up, Publish or block order, a certificate's Revoke. Nothing
 * showed them, so a refused button looked like one that did nothing. A single
 * list at the top would not do either — `preserveScroll` keeps the author
 * where they were, often a screen below it. So the page remembers which row it
 * last acted on and that row says what came back.
 *
 *   const refusals = useRowRefusals(form);
 *   onClick={() => refusals.actOn(`lesson:${id}`, () => router.post(...))}
 *   <FormErrors errors={refusals.errorsFor(`lesson:${id}`)} />
 *
 * Errors a form on the page is already showing — the same field, the same
 * words — are left to that form. `unplaced` is whatever came back before any
 * row was acted on (a page opened straight onto a refusal), for the page to
 * show once near the top. `unplacedAmong(keys)` is that, and also what came
 * back for a row the reload took off the page — an account unlinked
 * elsewhere, whose refusal would otherwise have gone with its row (slice AC1).
 */
export function useRowRefusals(...forms) {
    const pageErrors = usePage().props.errors;
    const [row, setRow] = useState(null);
    const unclaimed = Object.fromEntries(Object.entries(pageErrors ?? {}).filter(
        ([field, message]) => !forms.some((form) => form?.errors?.[field] === message),
    ));

    return {
        actOn: (key, visit) => {
            setRow(key);
            visit();
        },
        errorsFor: (key) => (row === key ? unclaimed : {}),
        unplaced: row === null ? unclaimed : {},
        unplacedAmong: (keys) => (row === null || !keys.includes(row) ? unclaimed : {}),
    };
}
