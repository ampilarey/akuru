import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { registerPushDevice } from './Platform';

// The Blade root writes the app name into <title inertia>; every page's
// title (from AppShell's <Head>) is prefixed to it, so a tab reads
// "System Settings · Akuru Institute" rather than the bare app name.
const appName = (typeof document !== 'undefined' && document.title.trim()) || 'Akuru';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
        // SPEC §50: inside the mobile shell, register this phone for push. A
        // browser has no Capacitor and the call returns at once.
        registerPushDevice(props.initialPage?.props?.auth?.user ?? null);
    },
});
