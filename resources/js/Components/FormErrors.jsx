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
 */
export default function FormErrors({ errors, className = '' }) {
    const messages = Object.entries(errors ?? {});

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
