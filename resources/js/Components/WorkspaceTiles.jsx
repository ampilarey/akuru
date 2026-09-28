import { Link, usePage } from '@inertiajs/react';

/**
 * A workspace home's tiles (docs/SIGN_IN_PLAN.md ID5): every screen of the
 * workspace's own menu as a tile, the way an EduPage account's home lays out
 * its drawer — Messages first, with the unread count. Built from the shared
 * `nav`, so a tile can never lead anywhere the menu does not, and a screen
 * the person could only be refused is not offered (`BuildNavigationAction`
 * reads that off each route's gate).
 *
 * The page it sits on and the workspace's home are left out; so is anything
 * a home already shows as a tile of its own (`skip`).
 */
const MESSAGES = '/portal/messages';
const tileClass = 'relative flex min-h-[3.5rem] items-center rounded-lg border bg-white px-3 py-3 text-sm font-medium text-[#7C2D37] hover:bg-[#F3EBE0]';

export default function WorkspaceTiles({ skip = [], className = '' }) {
    const { url, props } = usePage();
    const { nav = { primary: [], groups: [] }, auth, i18n } = props;
    const unlocalised = (href) => (href || '').replace(/^\/(en|dv|ar)(?=\/|$)/, '') || '/';
    const here = unlocalised(url.split('?')[0]);
    const home = unlocalised((auth?.workspaces ?? []).find((workspace) => workspace.key === auth?.workspace)?.href);
    const seen = new Set([here, home, ...skip]);
    const tiles = [...(nav.primary || []), ...(nav.groups || []).flatMap((group) => group.items)].filter((item) => {
        const path = unlocalised(item.href);
        if (seen.has(path)) {
            return false;
        }
        seen.add(path);

        return true;
    });
    tiles.sort((a, b) => (unlocalised(b.href) === MESSAGES) - (unlocalised(a.href) === MESSAGES));

    if (tiles.length === 0) {
        return null;
    }

    return (
        <section className={className} aria-label={i18n?.nav?.all_screens || 'All screens'}>
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-testid="workspace-tiles">
                {tiles.map((item) => {
                    const unread = unlocalised(item.href) === MESSAGES ? auth?.unread_messages ?? 0 : 0;
                    const body = (
                        <>
                            <span className="min-w-0 break-words">{item.label}</span>
                            {unread > 0 && (
                                <span className="ms-auto rounded-full bg-[#D4A017] px-2 py-0.5 text-xs font-bold text-[#3D1219]" data-testid="tile-unread">{unread}</span>
                            )}
                        </>
                    );

                    return (
                        <li key={item.href}>
                            {/* A Blade page (the Library, the Bookstore) loads whole. */}
                            {item.hard
                                ? <a href={item.href} className={tileClass}>{body}</a>
                                : <Link href={item.href} className={tileClass}>{body}</Link>}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
