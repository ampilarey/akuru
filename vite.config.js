import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                // React and Inertia change only when a dependency is bumped;
                // kept in their own chunk they stay cached across the deploys
                // that change the pages (docs/ADMIN_PANEL.md §7 P1). The
                // editor (TipTap, ProseMirror) is already loaded lazily by
                // the one component that uses it and Rollup chunks it on its
                // own; naming it here pulled the shared `use-sync-external-store`
                // shim into it and so into every first load.
                manualChunks(id) {
                    if (/node_modules\/(react|react-dom|scheduler|use-sync-external-store|@inertiajs)\//.test(id)) {
                        return 'vendor';
                    }

                    return undefined;
                },
            },
        },
    },
});
