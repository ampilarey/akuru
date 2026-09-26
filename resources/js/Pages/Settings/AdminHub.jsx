import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The admin panel's front door at /admin (docs/ADMIN_PANEL.md §1): the
 * panel in four parts — Admissions, Website & content, Shops & money,
 * System — each a row of cards, one per section this person may open,
 * and on a card the screens inside that section, so the whole panel is
 * one page to read. A Blade screen opens with a full page load; an
 * Inertia one with a visit.
 */
function Open({ hard, href, className, children, ...rest }) {
    return hard
        ? <a href={href} className={className} {...rest}>{children}</a>
        : <Link href={href} className={className} {...rest}>{children}</Link>;
}

export default function AdminHub({ t, parts }) {
    return (
        <AppShell title={t.hub_title}>
            <p className="mb-2 text-sm text-gray-600">{t.hub_intro}</p>
            {/* The other half: the dashboard is the numbers, this page is the
                doors. Said at the top, with the way back, because the two
                were confused for each other (the owner, 2026-09-26). */}
            <p className="mb-4 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                <a href="/dashboard" className="rounded border border-[#7C2D37] px-3 py-1.5 text-sm font-semibold text-[#7C2D37] hover:bg-[#F3EBE0]" data-testid="hub-dashboard">← {t.hub_dashboard}</a>
                <span>{t.hub_dashboard_hint}</span>
            </p>
            {/* The four parts, for a phone: one tap to the part. */}
            <nav aria-label={t.hub_parts} className="mb-6 flex flex-wrap gap-2 text-sm" data-testid="hub-parts">
                {parts.map((part) => (
                    <a key={part.key} href={`#${part.key}`} className="rounded-full border border-[#E6D9C8] bg-white px-3 py-1 text-[#7C2D37] hover:border-[#7C2D37]">
                        {part.label} <span className="text-gray-500">{part.sections.length}</span>
                    </a>
                ))}
            </nav>
            {parts.map((part) => (
                <section key={part.key} id={part.key} className="mb-8 scroll-mt-4" data-testid={`part-${part.key}`}>
                    <h2 className="mb-3 border-b border-[#E6D9C8] pb-1 text-base font-semibold uppercase tracking-wide text-gray-700">{part.label}</h2>
                    <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {part.sections.map((section) => (
                            <li key={section.key} className="flex h-full flex-col rounded-lg border border-[#E6D9C8] bg-white p-4" data-testid={`section-${section.key}`}>
                                <Open hard={section.hard} href={section.href} className="text-base font-semibold text-[#7C2D37] hover:underline" data-testid={`open-${section.key}`}>
                                    {section.label}
                                </Open>
                                <p className="mt-1 text-sm text-gray-600">{section.description}</p>
                                {section.children.length > 0 && (
                                    <ul className="mt-3 flex flex-wrap gap-1.5" aria-label={section.label}>
                                        {section.children.map((child) => (
                                            <li key={child.key}>
                                                <Open hard={child.hard} href={child.href} className="inline-block rounded-full bg-[#F3EBE0] px-2.5 py-0.5 text-xs font-medium text-[#7C2D37] hover:bg-[#E6D9C8]" data-testid={`child-${child.key}`}>
                                                    {child.label}
                                                </Open>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </AppShell>
    );
}
