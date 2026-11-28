import { Link } from '@inertiajs/react';

/**
 * ST5 (admin audit 2026-11-27, STATUS §5uy): an office's way to its own
 * settings pages — one button each, the same on every office. Shows
 * nothing to someone who may open none of them.
 */
export default function OfficeSettingsLinks({ links = [], className = 'mt-8 border-t pt-4' }) {
    if (links.length === 0) return null;

    return (
        <nav className={className} data-testid="office-settings-links">
            <ul className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                {links.map((link) => (
                    <li key={link.key}>
                        <Link href={link.href} className="btn-secondary inline-flex min-h-[2.75rem] w-full items-center justify-center sm:w-auto" data-testid={`office-settings-${link.key}`}>
                            <span aria-hidden="true" className="me-2">⚙</span>
                            {link.title}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
