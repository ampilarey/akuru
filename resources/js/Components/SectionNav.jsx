/**
 * A row of chips that jump to the sections of a long page.
 *
 * STATUS §5mp wrote it for the vendor portal: products first, then seven
 * settings sections 2,300px down with nothing to jump by. Sticky under the
 * thumb on phones (the shell's header is sticky only from `sm`), static
 * beside the header on wider screens; each chip at least 32px tall. The
 * Bookstore office — ten tables one under another, 10,600px tall on a
 * phone (docs/ADMIN_PANEL.md §7 P5, M6) — now shares it (STATUS §5no).
 *
 * `items` is a list of `[id, label]`; a section carries that `id` and
 * `scroll-mt-14` so the sticky row does not cover its heading.
 */
export default function SectionNav({ items, t, onJump }) {
    return (
        <nav className="sticky top-0 z-20 -mx-4 mb-4 bg-brandBeige-50/95 px-4 py-2 shadow-sm backdrop-blur sm:static sm:mx-0 sm:bg-transparent sm:px-0 sm:shadow-none" aria-label={t.on_this_page} data-testid="section-nav">
            <ul className="flex flex-wrap gap-2">
                {items.filter(([id, label]) => id && label).map(([id, label]) => (
                    <li key={id} className="min-w-0 max-w-full">
                        <a href={`#${id}`} onClick={() => onJump?.(id)} className="inline-flex min-h-[2rem] max-w-full items-center rounded-full border border-gray-300 bg-white px-3 py-1 text-sm text-gray-800 hover:border-brandMaroon-600 hover:text-brandMaroon-600" data-testid={`jump-${id}`}>{label}</a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
