import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Phase 5 (SPEC §50): the mobile app IS the responsive Inertia PWA,
 * wrapped. The shell loads the hosted site so every deploy reaches the
 * app instantly — no separate mobile codebase, no duplicate screens.
 * Set CAPACITOR_SERVER_URL to the environment being wrapped
 * (e.g. https://test.akuru.edu.mv while rehearsing).
 */
const config: CapacitorConfig = {
    appId: 'mv.edu.akuru.app',
    appName: 'Akuru',
    webDir: 'public',
    server: {
        url: process.env.CAPACITOR_SERVER_URL || 'https://akuru.edu.mv',
        cleartext: false,
    },
    android: {
        allowMixedContent: false,
    },
};

export default config;
