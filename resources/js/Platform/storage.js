/**
 * Small per-device preferences, behind the platform layer (SPEC §6.4).
 *
 * Every access is guarded: storage can be missing (a native shell), blocked
 * (private browsing) or full, and a preference that cannot be kept must never
 * break the page that wanted it.
 */

export function readPreference(key, fallback = null) {
    try {
        const value = window.localStorage.getItem(key);
        return value === null ? fallback : value;
    } catch {
        return fallback;
    }
}

export function writePreference(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Not kept; the page carries on with what it has.
    }
}
