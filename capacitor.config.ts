import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Phase 5 (SPEC §50): the mobile app IS the responsive Inertia PWA,
 * wrapped. The shell loads the hosted site so every deploy reaches the
 * app instantly — no separate mobile codebase, no duplicate screens.
 * Set CAPACITOR_SERVER_URL to the environment being wrapped
 * (e.g. https://test.akuru.edu.mv while rehearsing).
 */
// The app opens on `/dashboard`, not the marketing home (docs/SIGN_IN_PLAN.md
// ID3, finding F9): a signed-in person goes straight to their own workspace's
// home, and anyone else to the sign-in, which returns them there.
const serverBase = (process.env.CAPACITOR_SERVER_URL || 'https://akuru.edu.mv').replace(/\/+$/, '');

const config: CapacitorConfig = {
    appId: 'mv.edu.akuru.app',
    appName: 'Akuru',
    webDir: 'public',
    server: {
        url: `${serverBase}/dashboard`,
        cleartext: false,
    },
    android: {
        allowMixedContent: false,
    },
};

export default config;
