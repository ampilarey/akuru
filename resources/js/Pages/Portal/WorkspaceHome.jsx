import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A workspace's home — `/admin` for the Institute, `/school` for the School
 * (docs/ADMIN_PANEL.md §1, STATUS §5id): today's numbers first, then the
 * workspace in parts, each a row of cards, one per section this person may
 * open, and on a card the screens inside that section, so the whole
 * workspace is one page to read. A Blade screen opens with a full page load;
 * an Inertia one with a visit.
 */
function Open({ hard, href, className, children, ...rest }) {
    return hard
        ? <a href={href} className={className} {...rest}>{children}</a>
        : <Link href={href} className={className} {...rest}>{children}</Link>;
}

export default function WorkspaceHome({ t, workspace, parts, today = { tiles: [], more: null } }) {
    return (
        <AppShell title={t[`${workspace}_title`]}>
            <p className="mb-4 text-sm text-gray-600">{t[`${workspace}_intro`]}</p>
            {/* Today's numbers first — the dashboards' tiles, so a person has one
                home, not a numbers page and a doors page (STATUS §5ia). Each tile
                opens where its number comes from; the link after them opens the
                full dashboard. */}
            {today.tiles.length > 0 && (
                <section className="mb-8" data-testid="today">
                    <h2 className="mb-3 border-b border-[#E6D9C8] pb-1 text-base font-semibold uppercase tracking-wide text-gray-700">{t.today_title}</h2>
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {today.tiles.map((tile) => {
                            const body = (
                                <>
                                    <span className="block text-2xl font-semibold text-[#7C2D37]">{tile.value}</span>
                                    <span className="mt-1 block text-xs uppercase tracking-wide text-gray-500">{tile.label}</span>
                                </>
                            );
                            const className = 'block h-full rounded-lg border border-[#E6D9C8] bg-white p-3';
                            return (
                                <li key={tile.key} data-testid={`today-${tile.key}`}>
                                    {tile.href
                                        ? <Open hard={tile.hard} href={tile.href} className={`${className} hover:border-[#7C2D37]`}>{body}</Open>
                                        : <div className={className}>{body}</div>}
                                </li>
                            );
                        })}
                    </ul>
                    {today.more && (
                        <p className="mt-3 text-sm">
                            <Open hard={today.more.hard} href={today.more.href} className="font-semibold text-[#7C2D37] hover:underline" data-testid="today-more">{today.more.label} →</Open>
                        </p>
                    )}
                </section>
            )}
            {/* The parts, for a phone: one tap to the part. Scrolled from here, not
                by the fragment: a hash navigation fires a history event that Inertia
                answers by restoring the old scroll position. */}
            <nav aria-label={t.hub_parts} className="mb-6 flex flex-wrap gap-2 text-sm" data-testid="hub-parts">
                {parts.map((part) => (
                    <a
                        key={part.key}
                        href={`#${part.key}`}
                        onClick={(event) => { event.preventDefault(); document.getElementById(part.key)?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }}
                        className="rounded-full border border-[#E6D9C8] bg-white px-3 py-1 text-[#7C2D37] hover:border-[#7C2D37]"
                    >
                        {part.label} <span className="text-gray-500">{part.sections.length}</span>
                    </a>
                ))}
            </nav>
            {parts.map((part) => (
                <section key={part.key} id={part.key} className="mb-8 scroll-mt-4 sm:scroll-mt-24" data-testid={`part-${part.key}`}>
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
