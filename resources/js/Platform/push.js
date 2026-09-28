/**
 * Push registration for the mobile app (SPEC §50, STATUS §5jr). In the
 * platform layer (SPEC §6.3): this is the one place that knows about the
 * Capacitor plugin and the device's stored token.
 *
 * Runs only inside the Capacitor shell with the PushNotifications plugin
 * installed natively; in a browser every check below is false and nothing
 * happens, so the web bundle carries no plugin code. After sign-in the shell
 * asks for permission, registers with FCM/APNs and posts the token to
 * /account/devices; at sign-out it asks the server to forget that token.
 */
import { clearPreference, readPreference, writePreference } from './storage';

const STORAGE_KEY = 'akuru.push.token';

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function plugin() {
    const cap = typeof window !== 'undefined' ? window.Capacitor : null;
    if (!cap || typeof cap.isNativePlatform !== 'function' || !cap.isNativePlatform()) return null;

    return cap.Plugins?.PushNotifications || null;
}

async function post(path, body) {
    const response = await fetch(path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });

    return response.ok;
}

/** Register this phone once the person is signed in. Safe to call on every page load. */
export async function registerPushDevice(user) {
    const push = plugin();
    if (!push || !user) return;

    try {
        let permission = await push.checkPermissions();
        if (permission.receive === 'prompt' || permission.receive === 'prompt-with-rationale') {
            permission = await push.requestPermissions();
        }
        if (permission.receive !== 'granted') return;

        push.addListener('registration', async ({ value }) => {
            if (!value || readPreference(STORAGE_KEY) === `${user.id}:${value}`) return;
            const ok = await post('/account/devices', {
                token: value,
                platform: window.Capacitor.getPlatform(),
                locale: document.documentElement.lang || 'en',
                device_name: navigator.userAgent.slice(0, 120),
            });
            if (ok) writePreference(STORAGE_KEY, `${user.id}:${value}`);
        });
        push.addListener('pushNotificationActionPerformed', ({ notification }) => {
            const url = notification?.data?.url;
            if (url && url.startsWith('/')) window.location.assign(url);
        });
        await push.register();
    } catch (error) {
        // A phone that refuses is a phone without push; the app keeps working.
        console.warn('push registration skipped', error);
    }
}

/** Before signing out: this phone stops receiving. */
export async function forgetPushDevice() {
    const stored = readPreference(STORAGE_KEY);
    if (!stored) return;
    const token = stored.slice(stored.indexOf(':') + 1);
    clearPreference(STORAGE_KEY);
    await post('/account/devices/forget', { token }).catch(() => {});
}
