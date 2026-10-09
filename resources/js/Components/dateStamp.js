/**
 * A moment as digits — `2026-10-09 14:24` — which reads the same in every
 * language (BACKLOG C21, slice PT1b). `toLocaleString()` spoke the browser's
 * own language, so a Dhivehi or Arabic page said "2:24 PM": a browser has no
 * Dhivehi to give. The time is the reader's local time, as it was.
 */
export function dateStamp(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
