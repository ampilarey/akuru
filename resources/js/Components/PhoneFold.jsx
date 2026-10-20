import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const WIDE = '(min-width: 768px)';

/**
 * docs/MOBILE_FIRST.md rules 3 and 4 (MF3): on a phone a page's "new" form or
 * its filters fold under one button, so the person's own records start on
 * the first screen; from `md` the button goes and the form shows, as it did.
 * The same `filter-fold` classes as the public site (app.css), written
 * phone-first. A form sent back with errors opens again (`forceOpen`), so a
 * refusal is never hidden. Without a `label` the button says the shell's
 * *Add new* (MF7).
 */
export default function PhoneFold({ label, count = 0, forceOpen = false, testId, children }) {
    const shell = usePage().props.i18n?.nav ?? {};
    const text = label || shell.add_new || 'Add new';
    const [open, setOpen] = useState(() => typeof window === 'undefined' || window.matchMedia(WIDE).matches);

    useEffect(() => {
        const wide = window.matchMedia(WIDE);
        const sync = () => wide.matches && setOpen(true);
        wide.addEventListener('change', sync);

        return () => wide.removeEventListener('change', sync);
    }, []);

    useEffect(() => {
        if (forceOpen) {
            setOpen(true);
        }
    }, [forceOpen]);

    return (
        <details className="filter-fold mb-6" open={open} onToggle={(e) => setOpen(e.currentTarget.open)} data-testid={testId}>
            <summary className="filter-fold-toggle" data-testid={testId ? `${testId}-toggle` : undefined}>
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 5v14M5 12h14" />
                </svg>
                {text}
                {count > 0 && <span className="filter-fold-count">{count}</span>}
            </summary>
            <div className="filter-fold-body">{children}</div>
        </details>
    );
}
