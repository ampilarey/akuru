import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A workspace's home — `/admin` for the Institute, `/school` for the School
 * (docs/ADMIN_PANEL.md §1, STATUS §5id): today's numbers first, then the
 * workspace in parts, each a row of cards, one per section this person may
 * open, and on a card the screens inside that section, so the whole
 * workspace is one page to read. Related sections share a heading (the
 * bookstore, the platform). On a phone the Institute's inner screens are
 * rows a thumb can hit. A Blade screen opens with a full page load; an
 * Inertia one with a visit.
 */
function Open({ hard, href, className, children, ...rest }) {
    // `prefetch`: on a desk, resting the pointer on a card fetches that
    // screen's data before the click, so the section opens at once
    // (ADMIN_PANEL.md §7 M8). A phone has no hover and is unaffected.
    return hard
        ? <a href={href} className={className} {...rest}>{children}</a>
        : <Link href={href} className={className} prefetch {...rest}>{children}</Link>;
}

// Sections the server grouped (the bookstore, the platform) stay in their
// part's order, under one heading. Screens that are not in a group share
// one grid, so they still sit side by side. A part with no clusters is
// one grid.
function blocksFor(part) {
    const clusters = part.clusters || [];
    if (clusters.length === 0) {
        return [{ key: part.key, label: null, sections: part.sections }];
    }
    const byKey = Object.fromEntries(part.sections.map((section) => [section.key, section]));
    const started = new Set();
    const blocks = [];
    for (const section of part.sections) {
        const cluster = clusters.find((item) => item.sections.includes(section.key));
        if (!cluster) {
            const last = blocks[blocks.length - 1];
            if (last && last.label === null) {
                last.sections.push(section);
            } else {
                blocks.push({ key: section.key, label: null, sections: [section] });
            }
            continue;
        }
        if (started.has(cluster.key)) {
            continue;
        }
        started.add(cluster.key);
        blocks.push({
            key: cluster.key,
            label: cluster.label,
            sections: cluster.sections.map((key) => byKey[key]).filter(Boolean),
        });
    }

    return blocks;
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
                        className={workspace === 'institute'
                            ? 'inline-flex min-h-[2.75rem] items-center rounded-full border border-[#E6D9C8] bg-white px-3 text-[#7C2D37] hover:border-[#7C2D37] sm:min-h-0 sm:py-1'
                            : 'rounded-full border border-[#E6D9C8] bg-white px-3 py-1 text-[#7C2D37] hover:border-[#7C2D37]'}
                    >
                        {part.label} <span className="ms-1 text-gray-500">{part.sections.length}</span>
                    </a>
                ))}
            </nav>
            {parts.map((part) => {
                // The Institute's parts (panel_*) list inner screens as rows a
                // thumb can hit. The School keeps its chips.
                const rows = part.key.startsWith('panel_');
                return (
                    <section key={part.key} id={part.key} className="mb-8 scroll-mt-4 sm:scroll-mt-24" data-testid={`part-${part.key}`}>
                        <h2 className="mb-3 border-b border-[#E6D9C8] pb-1 text-base font-semibold uppercase tracking-wide text-gray-700">{part.label}</h2>
                        {blocksFor(part).map((block, index) => (
                            <div key={block.key} className={index > 0 ? 'mt-5' : undefined}>
                                {block.label && (
                                    <h3 className="mb-2 text-sm font-semibold text-[#3D1219]" data-testid={`cluster-${block.key}`}>{block.label}</h3>
                                )}
                                <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                                    {block.sections.map((section) => (
                                        <li key={section.key} className="flex h-full flex-col rounded-lg border border-[#E6D9C8] bg-white p-4" data-testid={`section-${section.key}`}>
                                            <Open hard={section.hard} href={section.href} className={`font-semibold text-[#7C2D37] hover:underline ${rows ? 'flex min-h-[2.75rem] items-center text-base sm:min-h-0 sm:inline' : 'text-base'}`} data-testid={`open-${section.key}`}>
                                                {section.label}
                                            </Open>
                                            <p className="mt-1 text-sm text-gray-600">{section.description}</p>
                                            {section.children.length > 0 && (
                                                <ul className={rows ? 'mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-1.5' : 'mt-3 flex flex-wrap gap-1.5'} aria-label={section.label}>
                                                    {section.children.map((child) => (
                                                        <li key={child.key} className={rows ? 'sm:contents' : undefined}>
                                                            <Open hard={child.hard} href={child.href} className={rows
                                                                ? 'flex min-h-[2.75rem] items-center rounded-lg bg-[#F3EBE0] px-3 text-sm font-medium text-[#7C2D37] hover:bg-[#E6D9C8] sm:inline-flex sm:min-h-[2rem] sm:rounded-full sm:px-2.5 sm:text-xs'
                                                                : 'inline-block rounded-full bg-[#F3EBE0] px-2.5 py-0.5 text-xs font-medium text-[#7C2D37] hover:bg-[#E6D9C8]'} data-testid={`child-${child.key}`}>
                                                                {child.label}
                                                            </Open>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </section>
                );
            })}
        </AppShell>
    );
}
