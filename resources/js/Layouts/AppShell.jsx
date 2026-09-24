import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * The shell every Inertia screen renders inside.
 *
 * Navigation comes from the server (`nav` shared prop, built by
 * `App\Support\Navigation\BuildNavigationAction`): a short primary bar for the
 * signed-in person's roles, and the *More* menu — every other screen, in nine
 * labelled groups, with anything the person could only be refused left out.
 * The shell decides nothing about who sees what; it renders what it is given.
 * Until 2026-09-24 it was 109 links in one wrapping strip, for everybody
 * (docs/APPSHELL_NAV_IA.md, KNOWN_ISSUES P3 #11).
 */
export default function AppShell({ title, children }) {
    const { url, props } = usePage();
    const { locale, locales = ['en', 'dv', 'ar'], locale_urls = {}, rtl, auth, flash, i18n, nav = { primary: [], groups: [] } } = props;
    const user = auth?.user;
    const t = i18n?.learn || {};
    const n = i18n?.nav || {};
    const [open, setOpen] = useState(false);

    // A menu left open across a page change is a menu the person has to close
    // twice. Close it whenever the URL moves, and on Escape.
    useEffect(() => setOpen(false), [url]);
    useEffect(() => {
        if (!open) return undefined;
        const onKey = (event) => event.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    const path = url.replace(/^\/(en|dv|ar)(?=\/|$)/, '').split('?')[0] || '/';
    const isCurrent = (href) => path === href || path.startsWith(`${href}/`);

    return (
        <div dir={rtl ? 'rtl' : 'ltr'} className="min-h-screen bg-[#F9F4EE] text-gray-900">
            <header className="relative border-b border-[#E6D9C8] bg-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-6 py-3">
                    <div className="flex items-center gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-[#7C2D37]">Akuru</p>
                            <h1 className="text-xl font-semibold">{title}</h1>
                        </div>
                    </div>
                    <nav aria-label={n.primary_nav || 'Primary'} className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        {nav.primary.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={isCurrent(item.href) ? 'page' : undefined}
                                className={isCurrent(item.href)
                                    ? 'border-b-2 border-[#7C2D37] pb-0.5 font-semibold text-[#7C2D37]'
                                    : 'text-[#7C2D37] hover:underline'}
                            >
                                {item.label}
                            </Link>
                        ))}
                        {nav.groups.length > 0 && (
                            <button
                                type="button"
                                aria-expanded={open}
                                aria-controls="app-shell-more"
                                onClick={() => setOpen((value) => !value)}
                                className={`rounded px-2 py-1 font-medium text-[#7C2D37] hover:bg-[#F3EBE0] ${open ? 'bg-[#F3EBE0]' : ''}`}
                            >
                                {n.more || 'More'} {open ? '▴' : '▾'}
                            </button>
                        )}
                        {/* E22a: notifications were invisible for months. A
                            count in the chrome is what makes them exist. */}
                        {user && (
                            <Link
                                href="/portal/notifications"
                                className="flex items-center gap-1 text-[#7C2D37] hover:underline"
                            >
                                Alerts
                                {auth?.unread_notifications > 0 && (
                                    <span className="rounded-full bg-[#7C2D37] px-1.5 py-0.5 text-xs font-bold text-white">
                                        {auth.unread_notifications}
                                    </span>
                                )}
                            </Link>
                        )}
                        {/* E7: a person with two identities lands on one of them.
                            Given as a bordered pill rather than a link in the
                            menu, because a link in there is not findable. */}
                        {auth?.alternate && (
                            <Link
                                href={auth.alternate.href}
                                className="rounded-full border border-[#7C2D37] px-3 py-1 font-medium text-[#7C2D37] hover:bg-[#F9F4EE]"
                            >
                                {auth.alternate.label}
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
                                className="rounded-full border border-[#7C2D37] px-3 py-1 font-medium text-[#7C2D37] hover:bg-[#F9F4EE]"
                                onClick={() => router.post(`/account/switch/${account.id}`)}
                                title={`Switch to ${account.name} (${account.roles})`}
                            >
                                Switch to {account.name}
                            </button>
                        ))}
                        {user && (
                            <span className="flex items-center gap-2">
                                <Link href="/account/linked" className="text-gray-500 hover:underline">
                                    {user.name}
                                </Link>
                                <button
                                    type="button"
                                    className="text-[#7C2D37] hover:underline"
                                    onClick={() => router.post('/logout')}
                                >
                                    {t.logout || 'Log out'}
                                </button>
                            </span>
                        )}
                        <span className="flex items-center gap-1 rounded bg-[#F3EBE0] px-2 py-0.5 text-xs uppercase">
                            {locales.map((code) => (
                                <a
                                    key={code}
                                    href={locale_urls[code] || `/${code}`}
                                    className={code === locale ? 'font-semibold text-[#7C2D37]' : 'text-gray-600 hover:underline'}
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
                            className="absolute inset-x-0 top-full z-20 border-b border-[#E6D9C8] bg-white shadow-lg"
                        >
                            <div className="mx-auto grid max-w-6xl grid-cols-2 gap-x-8 gap-y-6 px-6 py-6 text-sm sm:grid-cols-3 lg:grid-cols-5">
                                {nav.groups.map((group) => (
                                    <section key={group.key}>
                                        <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{group.label}</h2>
                                        <ul className="space-y-1">
                                            {group.items.map((item) => (
                                                <li key={item.href}>
                                                    <Link
                                                        href={item.href}
                                                        aria-current={isCurrent(item.href) ? 'page' : undefined}
                                                        className={isCurrent(item.href) ? 'font-semibold text-[#7C2D37]' : 'text-[#7C2D37] hover:underline'}
                                                    >
                                                        {item.label}
                                                    </Link>
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
            <main className="mx-auto max-w-6xl px-6 py-6">
                {flash?.success && (
                    <div className="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
