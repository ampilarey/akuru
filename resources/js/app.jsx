import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { registerPushDevice } from './Platform';

// A link with an anchor (`/admin/bookshop#settings`: the Institute's Settings
// part opens the Bookstore office at its settings section, ADMIN_PANEL.md §8).
// Inertia keeps an anchor only when the response's address is the one asked
// for, and every link in the shell is unlocalised and answered by a redirect
// to its `/en/…` address — so the anchor was dropped and the page opened at
// the top. Remember the anchor asked for; once the page has drawn, put it
// back and scroll to it.
let wantedHash = '';
router.on('start', (event) => {
    wantedHash = event.detail.visit.url?.hash || '';
});
router.on('navigate', () => {
    const hash = wantedHash;
    wantedHash = '';
    if (!hash || window.location.hash) {
        return;
    }
    let tries = 0;
    const scroll = () => {
        const target = document.getElementById(hash.slice(1));
        if (target) {
            window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}${hash}`);
            target.scrollIntoView();
        } else if (++tries < 30) {
            requestAnimationFrame(scroll);
        }
    };
    requestAnimationFrame(scroll);
});

// The Blade root writes the app name into <title inertia>; every page's
// title (from AppShell's <Head>) is prefixed to it, so a tab reads
// "System Settings · Akuru Institute" rather than the bare app name.
const appName = (typeof document !== 'undefined' && document.title.trim()) || 'Akuru';

// Every page is its own chunk, fetched the first time it is opened
// (docs/ADMIN_PANEL.md §7 P1, BACKLOG C15 slice 1). With `eager: true` the
// 247 pages of every workspace — the shop designer, the Hifz dashboards,
// the Qur'an player — were one 1.7 MB script that an administrator
// downloaded before the first admin screen drew, and again after every
// deploy. Now the shell and React are the shared part (cached across
// deploys by their own hash) and a visit costs its one page.
const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, pages),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
        // SPEC §50: inside the mobile shell, register this phone for push. A
        // browser has no Capacitor and the call returns at once.
        registerPushDevice(props.initialPage?.props?.auth?.user ?? null);
    },
});
