import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { registerPushDevice } from './Platform';

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
