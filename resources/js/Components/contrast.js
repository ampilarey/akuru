/**
 * Colour readability in the storefront designer (STATUS §5mz) — the same
 * arithmetic as `App\Domains\Bookshop\Support\Contrast` and the same pairs as
 * `Theme::PAIRS`, so what the designer says as the vendor picks is what the
 * server will say when they publish.
 *
 * The owner, 2026-10-02: "when the vendor changes the colour it's not
 * changing, but when he changes the theme it is". A preset always reads; a
 * hand-picked background usually left the text on it unreadable, the draft
 * saved, Publish was disabled, and the live page never changed.
 */
export const TEXT = 4.5;
export const LARGE = 3.0;
const DARK = '#15151A';
const LIGHT = '#FFFFFF';

/** The pairs that must read: [foreground, background, floor]. */
export const PAIRS = [
    ['text', 'page_bg', TEXT],
    ['text', 'card_bg', TEXT],
    ['on_primary', 'primary', TEXT],
    ['on_accent', 'accent', TEXT],
    ['accent', 'page_bg', LARGE],
];

/** Which text slot sits on which background, for picking it automatically. */
const TEXT_ON = { primary: ['on_primary'], accent: ['on_accent'], page_bg: ['text'], card_bg: ['text'] };

export function normalize(hex) {
    const m = /^#?([0-9a-f]{6})$/i.exec(String(hex ?? '').trim());
    return m ? `#${m[1].toUpperCase()}` : null;
}

function luminance(hex) {
    const h = normalize(hex) ?? '#000000';
    const [r, g, b] = [1, 3, 5].map((i) => {
        const c = parseInt(h.slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function ratio(a, b) {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return Math.round(((hi + 0.05) / (lo + 0.05)) * 100) / 100;
}

/** Mixed toward a colour by `amount` (0–1). */
function mix(hex, toward, amount) {
    const h = normalize(hex) ?? '#000000';
    const t = normalize(toward);
    return `#${[1, 3, 5].map((i) => {
        const c = parseInt(h.slice(i, i + 2), 16);
        const d = parseInt(t.slice(i, i + 2), 16);
        return Math.round(c + (d - c) * amount).toString(16).padStart(2, '0');
    }).join('').toUpperCase()}`;
}

/** The pairs that fail, with their ratio and floor — what the server's Theme::problems says. */
export function problems(colors) {
    const out = [];
    for (const [fg, bg, needed] of PAIRS) {
        if (!normalize(colors[fg]) || !normalize(colors[bg])) continue;
        const r = ratio(colors[fg], colors[bg]);
        if (r < needed) out.push({ pair: `${fg}/${bg}`, ratio: r, needed, scheme: 'light' });
    }
    return out;
}

/**
 * The current colour if it reads on every background; else white or dark,
 * whichever reads on all of them; else (a dark page under white cards, say)
 * whichever reads on the first — the background the vendor just changed.
 */
function readableText(current, backgrounds) {
    const readsOn = (c, bgs) => bgs.every((bg) => ratio(c, bg) >= TEXT);
    if (normalize(current) && readsOn(current, backgrounds)) return current;
    for (const c of [LIGHT, DARK]) if (readsOn(c, backgrounds)) return c;
    return ratio(LIGHT, backgrounds[0]) >= ratio(DARK, backgrounds[0]) ? LIGHT : DARK;
}

/**
 * After a background slot changes, the text on it is switched to white or
 * dark if it would no longer read. Returns the new colours and the slots it
 * changed (so the designer can say so).
 */
export function withReadableText(colors, changedSlot) {
    const next = { ...colors };
    const changed = [];
    for (const fg of TEXT_ON[changedSlot] ?? []) {
        // The background just changed comes first: it wins when page and card disagree.
        const backgrounds = fg === 'text' ? [next[changedSlot], changedSlot === 'page_bg' ? next.card_bg : next.page_bg] : [next[changedSlot]];
        if (backgrounds.some((bg) => !normalize(bg))) continue;
        const picked = readableText(next[fg], backgrounds);
        if (picked !== next[fg]) {
            next[fg] = picked;
            changed.push(fg);
        }
    }
    return { colors: next, changed };
}

/**
 * Every failing pair made readable in one go: the text slots become white or
 * dark; an accent too faint (or too dark) for the page is shaded toward
 * contrast a step at a time, keeping its hue, until it reads.
 */
export function makeReadable(colors) {
    let next = { ...colors };
    // A dark page under light cards (or the reverse): no one text colour reads on both, so the
    // cards come to the page's side — the same choice the derived dark scheme makes.
    if (normalize(next.page_bg) && normalize(next.card_bg) && (luminance(next.page_bg) > 0.18) !== (luminance(next.card_bg) > 0.18)) {
        next.card_bg = luminance(next.page_bg) > 0.18 ? LIGHT : '#22222A';
    }
    for (const slot of ['primary', 'accent', 'page_bg']) next = withReadableText(next, slot).colors;
    if (normalize(next.accent) && normalize(next.page_bg) && ratio(next.accent, next.page_bg) < LARGE) {
        const toward = luminance(next.page_bg) > 0.18 ? '#000000' : '#FFFFFF';
        let accent = next.accent;
        for (let i = 0; i < 20 && ratio(accent, next.page_bg) < LARGE; i++) accent = mix(accent, toward, 0.08);
        next = withReadableText({ ...next, accent }, 'accent').colors;
    }
    return next;
}
