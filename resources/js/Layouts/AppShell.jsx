import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * The shell every Inertia screen renders inside.
 *
 * Navigation comes from the server (`nav` shared prop, built by
 * `App\Support\Navigation\BuildNavigationAction` for the person's active
 * workspace): a short primary bar and the *More* menu — every other screen
 * of that workspace, in labelled groups, with anything the person could only
 * be refused left out. A person who holds several workspaces (the Institute,
 * the School, a family, a shop) switches between them from the header, and
 * the bar and the menu change with it (STATUS §5id). The shell decides
 * nothing about who sees what; it renders what it is given.
 */
export default function AppShell({ title, children }) {
    const { url, props } = usePage();
    const { locale, locales = ['en', 'dv', 'ar'], locale_urls = {}, rtl, auth, flash, i18n, nav = { primary: [], groups: [] } } = props;
    const user = auth?.user;
    const t = i18n?.learn || {};
    const n = i18n?.nav || {};
    const [open, setOpen] = useState(false);
    const [switching, setSwitching] = useState(false);
    const workspaces = auth?.workspaces ?? [];
    const activeWorkspace = workspaces.find((workspace) => workspace.key === auth?.workspace);

    // A menu left open across a page change is a menu the person has to close
    // twice. Close it whenever the URL moves, and on Escape.
    useEffect(() => { setOpen(false); setSwitching(false); }, [url]);
    useEffect(() => {
        if (!open && !switching) return undefined;
        const onKey = (event) => { if (event.key === 'Escape') { setOpen(false); setSwitching(false); } };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, switching]);

    // Hrefs from the server carry the locale prefix (`/en/admin`); the map's
    // do not. Compare both without it.
    const unlocalised = (href) => (href || '').replace(/^\/(en|dv|ar)(?=\/|$)/, '') || '/';
    const path = unlocalised(url.split('?')[0]);
    const isCurrent = (href) => path === unlocalised(href) || path.startsWith(`${unlocalised(href)}/`);

    // `hard`: a Blade screen, opened with a full page load — an Inertia visit
    // would get a non-Inertia response and show it in a modal.
    const Item = ({ item, className, ...rest }) => (item.hard
        ? <a href={item.href} className={className} data-nav-hard {...rest}>{item.label}</a>
        : <Link href={item.href} aria-current={isCurrent(item.href) ? 'page' : undefined} className={className} {...rest}>{item.label}</Link>);

    return (
        <div dir={rtl ? 'rtl' : 'ltr'} className="min-h-screen bg-[#F9F4EE] text-gray-900">
            {/* Keyboard users land on the content, not on the menu (admin-panel layout audit, STATUS §5ht). */}
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:text-[#7C2D37]">
                {n.skip_to_content || 'Skip to content'}
            </a>
            {/* The same brand bar as the Blade shell — wine gradient, the logo,
                "Akuru Institute", white links — so an administrator moving between
                the two shells sees one application (STATUS §5ib). Sticky from sm:
                only — on a phone the bar wraps to several rows. */}
            <header className="relative z-30 bg-gradient-to-br from-[#3D1219] to-[#7C2D37] text-white shadow-md sm:sticky sm:top-0">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-2 sm:px-6">
                    <div className="flex items-center gap-3">
                        <a href="/dashboard" className="flex shrink-0 items-center gap-2.5 no-underline" data-testid="shell-home">
                            <img src="/images/logos/akuru-logo-on-dark.svg?v=3" alt="Akuru Institute" className="h-8 w-auto object-contain" />
                            <span className="text-[.95rem] font-bold tracking-wide text-white">Akuru Institute</span>
                        </a>
                        {/* The workspace switcher: shown only to a person who holds more
                            than one. Switching posts the choice, so the server remembers
                            it and sends them to that workspace's home. */}
                        {workspaces.length > 1 && (
                            <div className="relative">
                                <button
                                    type="button"
                                    aria-expanded={switching}
                                    aria-controls="app-shell-workspaces"
                                    data-testid="workspace-switcher"
                                    title={n.switch_workspace || 'Switch workspace'}
                                    onClick={() => setSwitching((value) => !value)}
                                    className={`rounded-full border border-white/40 px-3 py-1 text-sm font-medium text-white hover:bg-white/10 ${switching ? 'bg-white/20' : ''}`}
                                >
                                    {activeWorkspace?.label} {switching ? '▴' : '▾'}
                                </button>
                                {switching && (
                                    <>
                                        <button type="button" aria-label={n.close || 'Close'} onClick={() => setSwitching(false)} className="fixed inset-0 z-10 cursor-default bg-transparent" />
                                        <div id="app-shell-workspaces" role="menu" aria-label={n.workspaces || 'Workspaces'} className="absolute start-0 top-full z-20 mt-1 min-w-[12rem] rounded-lg border border-[#E6D9C8] bg-white p-1 text-sm text-gray-900 shadow-lg">
                                            <span className="block px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{n.workspaces || 'Workspaces'}</span>
                                            {workspaces.map((workspace) => (
                                                <button
                                                    key={workspace.key}
                                                    type="button"
                                                    role="menuitem"
                                                    data-testid={`workspace-${workspace.key}`}
                                                    aria-current={workspace.key === auth?.workspace ? 'true' : undefined}
                                                    onClick={() => { setSwitching(false); router.post(`/workspace/${workspace.key}`); }}
                                                    className={`block w-full rounded px-3 py-1.5 text-start hover:bg-[#F3EBE0] ${workspace.key === auth?.workspace ? 'font-semibold text-[#7C2D37]' : 'text-gray-700'}`}
                                                >
                                                    {workspace.label}
                                                </button>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                    <nav aria-label={n.primary_nav || 'Primary'} className="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
                        {/* On a phone the primary links are one row that scrolls sideways;
                            More, Alerts, the account and the language switcher stay in view
                            beneath it (the mobile sweep, STATUS §5hu). */}
                        <div className="order-last flex w-full flex-nowrap items-center gap-x-1 overflow-x-auto whitespace-nowrap sm:order-none sm:w-auto sm:flex-wrap sm:gap-y-1 sm:overflow-visible sm:whitespace-normal">
                        {nav.primary.map((item) => (
                            <Item
                                key={item.href}
                                item={item}
                                className={isCurrent(item.href)
                                    ? 'rounded-md bg-white/20 px-3 py-1.5 font-medium text-white'
                                    : 'rounded-md px-3 py-1.5 font-medium text-white/80 hover:bg-white/10 hover:text-white'}
                            />
                        ))}
                        </div>
                        {nav.groups.length > 0 && (
                            <button
                                type="button"
                                aria-expanded={open}
                                aria-controls="app-shell-more"
                                onClick={() => setOpen((value) => !value)}
                                className={`rounded-md px-3 py-1.5 font-medium text-white/80 hover:bg-white/10 hover:text-white ${open ? 'bg-white/20 text-white' : ''}`}
                            >
                                {n.more || 'More'} {open ? '▴' : '▾'}
                            </button>
                        )}
                        {/* E22a: notifications were invisible for months. A
                            count in the chrome is what makes them exist. */}
                        {user && (
                            <Link
                                href="/portal/notifications"
                                className="flex items-center gap-1 rounded-md px-3 py-1.5 font-medium text-white/80 hover:bg-white/10 hover:text-white"
                            >
                                {n.alerts || 'Alerts'}
                                {auth?.unread_notifications > 0 && (
                                    <span className="rounded-full bg-[#D4A017] px-1.5 py-0.5 text-xs font-bold text-[#3D1219]">
                                        {auth.unread_notifications}
                                    </span>
                                )}
                            </Link>
                        )}
                        {/* E7: the switch itself, not a link to a page that
                            offers it — the plan's acceptance is "two taps", and
                            a settings page in between makes it four. Rendered
                            only when a proved link exists, so it is invisible
                            to the great majority who have one account. */}
                        {(auth?.linked_accounts ?? []).map((account) => (
                            <button
                                key={account.id}
                                type="button"
                                className="rounded-full border border-white/40 px-3 py-1 font-medium text-white hover:bg-white/10"
                                onClick={() => router.post(`/account/switch/${account.id}`)}
                                title={`Switch to ${account.name} (${account.roles})`}
                            >
                                Switch to {account.name}
                            </button>
                        ))}
                        {user && (
                            <span className="flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/25 text-xs font-bold text-white" aria-hidden="true">{(user.name || '?').slice(0, 1).toUpperCase()}</span>
                                <Link href="/account/linked" className="max-w-[10rem] truncate text-white hover:underline">
                                    {user.name}
                                </Link>
                                <button
                                    type="button"
                                    className="text-white/80 hover:text-white hover:underline"
                                    onClick={() => router.post('/logout')}
                                >
                                    {t.logout || 'Log out'}
                                </button>
                            </span>
                        )}
                        <span className="flex items-center gap-1 rounded bg-white/15 px-2 py-0.5 text-xs uppercase">
                            {locales.map((code) => (
                                <a
                                    key={code}
                                    href={locale_urls[code] || `/${code}`}
                                    className={code === locale ? 'font-semibold text-white' : 'text-white/70 hover:text-white hover:underline'}
                                    hrefLang={code}
                                >
                                    {t[`locale_${code}`] || code}
                                </a>
                            ))}
                        </span>
                    </nav>
                </div>
                {open && (
                    <>
                        {/* A click anywhere else closes the menu. */}
                        <button
                            type="button"
                            aria-label={n.close || 'Close'}
                            onClick={() => setOpen(false)}
                            className="fixed inset-0 z-10 cursor-default bg-transparent"
                        />
                        <div
                            id="app-shell-more"
                            role="region"
                            aria-label={n.all_screens || 'All screens'}
                            className="absolute inset-x-0 top-full z-20 max-h-[80vh] overflow-y-auto border-b border-[#E6D9C8] bg-white text-gray-900 shadow-lg"
                        >
                            <div className="mx-auto grid max-w-6xl grid-cols-2 gap-x-8 gap-y-6 px-6 py-6 text-sm sm:grid-cols-3 lg:grid-cols-5">
                                {nav.groups.map((group) => (
                                    <section key={group.key} data-nav-section={group.key}>
                                        <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{group.label}</h2>
                                        <ul className="space-y-1">
                                            {group.items.map((item) => (
                                                <li key={item.href}>
                                                    <Item
                                                        item={item}
                                                        className={!item.hard && isCurrent(item.href) ? 'font-semibold text-[#7C2D37]' : 'text-[#7C2D37] hover:underline'}
                                                    />
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                ))}
                            </div>
                        </div>
                    </>
                )}
            </header>
            <main id="main" className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <h1 className="mb-4 text-xl font-semibold text-gray-900">{title}</h1>
                {flash?.success && (
                    <div role="status" className="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div role="alert" className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
