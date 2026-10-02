import { Head, Link, router, usePage } from '@inertiajs/react';
import { forgetPushDevice } from '../Platform';
import { useEffect, useState } from 'react';

/** A phone, the same cut as Tailwind's `sm` (640px). The shell hides More there. */
function useNarrow() {
    const query = '(max-width: 639.98px)';
    const [narrow, setNarrow] = useState(() => typeof window !== 'undefined' && window.matchMedia(query).matches);

    useEffect(() => {
        const media = window.matchMedia(query);
        const apply = () => setNarrow(media.matches);
        apply();
        media.addEventListener('change', apply);

        return () => media.removeEventListener('change', apply);
    }, []);

    return narrow;
}

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
    const narrow = useNarrow();
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
    const unread = Number(auth?.unread_notifications) || 0;
    // The bar's links move into the phone menu. The workspace home is already
    // there, so it is not listed a second time.
    const homePath = unlocalised(activeWorkspace?.href || '');
    const barInMenu = nav.primary.filter((item) => unlocalised(item.href) !== homePath);

    // `hard`: a Blade screen, opened with a full page load — an Inertia visit
    // would get a non-Inertia response and show it in a modal.
    const Item = ({ item, className, ...rest }) => (item.hard
        ? <a href={item.href} className={className} data-nav-hard {...rest}>{item.label}</a>
        : <Link href={item.href} aria-current={isCurrent(item.href) ? 'page' : undefined} className={className} {...rest}>{item.label}</Link>);

    return (
        <div dir={rtl ? 'rtl' : 'ltr'} className="min-h-screen bg-[#F9F4EE] text-gray-900">
            {/* The tab reads the page, not the bare app name (STATUS §5jc); app.jsx appends the app name. */}
            <Head title={title} />
            {/* Keyboard users land on the content, not on the menu (admin-panel layout audit, STATUS §5ht). */}
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:text-[#7C2D37]">
                {n.skip_to_content || 'Skip to content'}
            </a>
            {/* The same brand bar as the Blade shell — wine gradient, the logo,
                "Akuru Institute", white links — so an administrator moving between
                the two shells sees one application (STATUS §5ib). Sticky from sm:.
                On a phone it is one row: the brand and the initial. */}
            <header className="relative z-30 bg-gradient-to-br from-[#3D1219] to-[#7C2D37] text-white shadow-md sm:sticky sm:top-0">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-x-3 px-4 py-1.5 sm:flex-wrap sm:gap-x-6 sm:gap-y-2 sm:px-6 sm:py-2">
                    <div className="flex min-w-0 items-center gap-3">
                        <a href="/dashboard" className="flex shrink-0 items-center gap-2.5 no-underline" data-testid="shell-home">
                            <img src="/images/logos/akuru-logo-on-dark.svg?v=5" alt="Akuru Institute" className="h-8 w-auto object-contain" />
                            <span className="text-[.95rem] font-bold tracking-wide text-white">Akuru Institute</span>
                        </a>
                        {/* The workspace switcher: shown only to a person who holds more
                            than one. Switching posts the choice, so the server remembers
                            it and sends them to that workspace's home. */}
                        {workspaces.length > 1 && (
                            <div className="relative hidden sm:block">
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
                    {/* On a phone this nav is only the initial, on the same row as the
                        brand. The bar, Alerts and the workspace pill are desk-only
                        (STATUS §5ne); a nowrap strip of them used to widen the header
                        past the phone (STATUS §5jq). */}
                    <nav aria-label={n.primary_nav || 'Primary'} className="flex min-w-0 items-center gap-x-1 gap-y-1 text-sm sm:w-auto sm:flex-wrap">
                        {/* The bar links stay on a desk. On a phone they are in the
                            initial's menu, so this row is not drawn (STATUS §5ne). */}
                        <div className="order-last hidden w-full min-w-0 flex-nowrap items-center gap-x-1 overflow-x-auto whitespace-nowrap sm:order-none sm:flex sm:w-auto sm:flex-wrap sm:gap-y-1 sm:overflow-visible sm:whitespace-normal">
                        {nav.primary.map((item) => (
                            <Item
                                key={item.href}
                                item={item}
                                className={isCurrent(item.href)
                                    ? 'rounded-md bg-white/20 px-3 py-2 font-medium text-white sm:py-1.5'
                                    : 'rounded-md px-3 py-2 font-medium text-white/80 hover:bg-white/10 hover:text-white sm:py-1.5'}
                            />
                        ))}
                        </div>
                        {/* More is the desk control for the screens that are not in the
                            bar. On a phone the initial (below) opens that same list, so a
                            second More button is not drawn (the owner, 2026-10-02). A
                            signed-out phone still gets More when there is a list. */}
                        {(nav.groups.length > 0 || user) && !(narrow && user) && (
                            <button
                                type="button"
                                aria-expanded={open}
                                aria-controls="app-shell-more"
                                data-testid="shell-more"
                                onClick={() => setOpen((value) => !value)}
                                className={`rounded-md px-3 py-2 font-medium text-white/80 hover:bg-white/10 hover:text-white sm:py-1.5 ${open ? 'bg-white/20 text-white' : ''} ${nav.groups.length === 0 ? 'sm:hidden' : ''}`}
                            >
                                {n.more || 'More'} {open ? '▴' : '▾'}
                            </button>
                        )}
                        {/* E22a: notifications were invisible for months. A
                            count in the chrome is what makes them exist. */}
                        {user && (
                            <Link
                                href="/portal/notifications"
                                className="hidden items-center gap-1 rounded-md px-3 py-2 font-medium text-white/80 hover:bg-white/10 hover:text-white sm:flex sm:py-1.5"
                            >
                                {n.alerts || 'Alerts'}
                                {unread > 0 && (
                                    <span className="rounded-full bg-[#D4A017] px-1.5 py-0.5 text-xs font-bold text-[#3D1219]">
                                        {unread}
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
                                className="hidden rounded-full border border-white/40 px-3 py-1 font-medium text-white hover:bg-white/10 sm:inline"
                                onClick={() => router.post(`/account/switch/${account.id}`)}
                                title={`Switch to ${account.name} (${account.roles})`}
                            >
                                Switch to {account.name}
                            </button>
                        ))}
                        {/* The account pill and the language switch: in the bar from sm:,
                            in this panel on a phone. The phone header is one row — the
                            brand and this initial — so the page starts sooner (STATUS
                            §5ne). The initial stays so a shared phone still shows who is
                            signed in, and it opens the list More opens on a desk. */}
                        {user && (
                            <button
                                type="button"
                                aria-label={unread > 0 ? `${n.account || 'Account'} (${unread})` : (n.account || 'Account')}
                                aria-expanded={narrow ? open : undefined}
                                aria-controls={narrow ? 'app-shell-more' : undefined}
                                title={user.name}
                                data-testid="shell-avatar"
                                onClick={() => setOpen((value) => !value)}
                                className={`relative ms-auto flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white sm:hidden ${open ? 'bg-white/40 ring-2 ring-white' : 'bg-white/25'}`}
                            >
                                {(user.name || '?').slice(0, 1).toUpperCase()}
                                {unread > 0 && (
                                    <span data-testid="avatar-alerts" className="absolute -top-1 -end-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#D4A017] px-1 text-[10px] font-bold leading-none text-[#3D1219]">
                                        {unread > 99 ? '99+' : unread}
                                    </span>
                                )}
                            </button>
                        )}
                        {user && (
                            <span className="hidden items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1 sm:flex">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/25 text-xs font-bold text-white" aria-hidden="true">{(user.name || '?').slice(0, 1).toUpperCase()}</span>
                                <Link href="/account/linked" className="max-w-[10rem] truncate text-white hover:underline">
                                    {user.name}
                                </Link>
                                <button
                                    type="button"
                                    className="text-white/80 hover:text-white hover:underline"
                                    onClick={() => forgetPushDevice().finally(() => router.post('/logout'))}
                                >
                                    {t.logout || 'Log out'}
                                </button>
                            </span>
                        )}
                        <span className="hidden items-center gap-1 rounded bg-white/15 px-2 py-0.5 text-xs uppercase sm:flex">
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
                            {/* The phone's account and language rows (see the bar above) — first,
                                so they are in reach without scrolling a long list of groups. */}
                            <div className="border-b border-[#E6D9C8] bg-[#FDFBF8] px-6 py-4 text-sm sm:hidden" data-testid="shell-account">
                                {user && (
                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                                        <Link href="/account/linked" className="flex min-w-0 items-center gap-2 text-gray-900 hover:underline">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#7C2D37] text-xs font-bold text-white" aria-hidden="true">{(user.name || '?').slice(0, 1).toUpperCase()}</span>
                                            <span className="truncate font-medium">{user.name}</span>
                                        </Link>
                                        <button
                                            type="button"
                                            className="rounded-md border border-[#7C2D37] px-3 py-2 font-medium text-[#7C2D37]"
                                            onClick={() => forgetPushDevice().finally(() => router.post('/logout'))}
                                        >
                                            {t.logout || 'Log out'}
                                        </button>
                                    </div>
                                )}
                                {/* Your accounts (SIGN_IN_PLAN ID4): every workspace this person
                                    holds, and every other login they have proved they own (E7),
                                    as EduPage's drawer lists them — the current one marked, one
                                    tap to switch. The header's pill stays for a desktop. */}
                                {(workspaces.length > 1 || (auth?.linked_accounts ?? []).length > 0) && (
                                    <div className="mb-3" data-testid="shell-accounts">
                                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">{n.your_accounts || 'Your accounts'}</span>
                                        <ul className="divide-y divide-[#E6D9C8] rounded-lg border border-[#E6D9C8] bg-white">
                                            {workspaces.map((workspace) => {
                                                const current = workspace.key === auth?.workspace;
                                                return (
                                                    <li key={workspace.key}>
                                                        <button
                                                            type="button"
                                                            data-testid={`account-${workspace.key}`}
                                                            aria-current={current ? 'true' : undefined}
                                                            disabled={current}
                                                            onClick={() => { setOpen(false); router.post(`/workspace/${workspace.key}`); }}
                                                            className={`flex w-full items-center justify-between gap-3 px-3 py-2.5 text-start ${current ? 'font-semibold text-[#7C2D37]' : 'text-gray-800'}`}
                                                        >
                                                            <span className="min-w-0">
                                                                <span className="block truncate">{user?.name}</span>
                                                                <span className="block text-xs font-normal text-gray-500">{workspace.label}</span>
                                                            </span>
                                                            {current && <span aria-hidden="true">✓</span>}
                                                        </button>
                                                    </li>
                                                );
                                            })}
                                            {(auth?.linked_accounts ?? []).map((account) => (
                                                <li key={`linked-${account.id}`}>
                                                    <button
                                                        type="button"
                                                        data-testid={`linked-account-${account.id}`}
                                                        onClick={() => router.post(`/account/switch/${account.id}`)}
                                                        className="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-start text-gray-800"
                                                    >
                                                        <span className="min-w-0">
                                                            <span className="block truncate">{account.name}</span>
                                                            <span className="block text-xs text-gray-500">{account.roles}</span>
                                                        </span>
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                {user && (
                                    <Link href="/portal/notifications" data-testid="shell-alerts" className="mb-3 flex min-h-[2rem] items-center justify-between gap-3 rounded-lg border border-[#E6D9C8] bg-white px-3 py-2 text-gray-900">
                                        <span className="font-medium">{n.alerts || 'Alerts'}</span>
                                        {unread > 0 && <span className="rounded-full bg-[#D4A017] px-1.5 py-0.5 text-xs font-bold text-[#3D1219]">{unread}</span>}
                                    </Link>
                                )}
                                {barInMenu.length > 0 && (
                                    <ul className="mb-3 divide-y divide-[#E6D9C8] rounded-lg border border-[#E6D9C8] bg-white" data-testid="shell-bar-links">
                                        {barInMenu.map((item) => (
                                            <li key={item.href}>
                                                <Item item={item} className={`block px-3 py-2.5 ${!item.hard && isCurrent(item.href) ? 'font-semibold text-[#7C2D37]' : 'text-gray-800'}`} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">{n.language || 'Language'}</span>
                                    {locales.map((code) => (
                                        <a
                                            key={code}
                                            href={locale_urls[code] || `/${code}`}
                                            className={`rounded-full px-3 py-1.5 ${code === locale ? 'bg-[#7C2D37] font-semibold text-white' : 'border border-[#E6D9C8] bg-white text-gray-700'}`}
                                            hrefLang={code}
                                        >
                                            {t[`locale_${code}`] || code}
                                        </a>
                                    ))}
                                </div>
                            </div>
                            {/* Home is the workspace's home — the shop for a vendor, the
                                School office, the family portal — never one page for
                                everybody (docs/SIGN_IN_PLAN.md F2). A plain link, as the
                                wordmark is: a home may still be a Blade page. */}
                            {activeWorkspace && (
                                <div className="mx-auto max-w-6xl px-6 pt-4 text-sm">
                                    <a href={activeWorkspace.href} data-testid="workspace-home" className="inline-flex items-center gap-2 py-1 font-semibold text-[#7C2D37] hover:underline">
                                        <span aria-hidden="true">🏠</span>
                                        {n.workspace_home || 'Home'}
                                        <span className="font-normal text-gray-500">· {activeWorkspace.label}</span>
                                    </a>
                                </div>
                            )}
                            {nav.groups.length > 0 && (
                                <div className="mx-auto grid max-w-6xl grid-cols-2 gap-x-8 gap-y-6 px-6 py-6 text-sm sm:grid-cols-3 lg:grid-cols-5">
                                    {nav.groups.map((group) => (
                                        <section key={group.key} data-nav-section={group.key}>
                                            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{group.label}</h2>
                                            <ul className="space-y-1">
                                                {group.items.map((item) => (
                                                    <li key={item.href}>
                                                        <Item
                                                            item={item}
                                                            className={`block py-1 sm:py-0 ${!item.hard && isCurrent(item.href) ? 'font-semibold text-[#7C2D37]' : 'text-[#7C2D37] hover:underline'}`}
                                                        />
                                                    </li>
                                                ))}
                                            </ul>
                                        </section>
                                    ))}
                                </div>
                            )}
                        </div>
                    </>
                )}
            </header>
            <main id="main" className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <h1 className="mb-4 text-xl font-semibold text-gray-900">{title}</h1>
                {/* The one place a flash is printed (STATUS §5mu): pages do not print it again. */}
                {flash?.success && (
                    <div role="status" className="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800" data-testid="flash-success">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div role="alert" className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800" data-testid="flash-error">
                        {flash.error}
                    </div>
                )}
                {/* A person who has only signed in with a one-time code is asked to
                    choose a password on their workspace home, whichever workspace
                    it is (SIGN_IN_PLAN ID3) — once, there, not on every screen. */}
                {auth?.must_set_password && activeWorkspace && path === unlocalised(activeWorkspace.href) && (
                    <div role="status" className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4" data-testid="set-password-notice">
                        <p className="font-medium text-amber-900">{n.set_password_title || 'Set a password for easier sign-in'}</p>
                        <p className="mt-1 text-sm text-amber-800">{n.set_password_body}</p>
                        <Link href="/account/set-password" className="mt-2 inline-block text-sm font-semibold text-amber-900 underline">{n.set_password_link || 'Set a password'}</Link>
                    </div>
                )}
                {flash?.info && (
                    <div role="status" className="mb-4 rounded border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-900" data-testid="flash-info">
                        {flash.info}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
