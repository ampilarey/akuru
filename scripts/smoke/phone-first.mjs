/**
 * Is the website phone-first? (CLAUDE.md "Phone-first", docs/MOBILE_FIRST.md,
 * STATUS §5sv.)
 *
 * Every public page, in English and Dhivehi, on a 390 × 844 phone:
 *   1. nothing runs wider than the screen — a page that scrolls sideways is
 *      named with the element that pushes it out;
 *   2. the thing the page is for starts on the first screen: a page marks it
 *      `data-phone-main` (the shelf, the course list, the results), and its
 *      top must sit above the bottom bar, not below a screen of filters.
 *
 * A page without the marker is checked for (1) only. Add the marker when you
 * build or change a listing page; the walk then holds it there.
 *
 * Then the app, signed in as a student, a parent and a teacher (MF3): the
 * pages with a "new" form and a list of records, whose form folds under one
 * button on a phone so the records start on the first screen.
 *
 * Read-only: runs against staging as well as a local server.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PARENT, SMOKE_TEACHER, SMOKE_ADMIN,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = (process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
// The first screen, less the bottom bar and the cookie bar a first visit sees.
const FIRST_SCREEN = 844 - 140;

const PAGES = [
    '', 'about', 'achievements', 'admissions', 'apply', 'articles', 'careers', 'contact', 'courses', 'events',
    'free-items', 'gallery', 'gift-cards', 'lending', 'library', 'library/blogs', 'library/promotions', 'news',
    'prayer-times', 'privacy', 'refunds', 'research', 'search?q=arabic', 'services', 'shop', 'shop/cart',
    'shop/compare', 'shop/deals', 'shop/track', 'shop/used', 'terms', 'login',
];

const APP = {
    [process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv']: ['portal/absence-notes', 'portal/events'],
    [process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv']: ['portal/absence-notes', 'portal/events', 'academics/requests'],
    [process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv']: ['academics/plans', 'academics/materials', 'academics/behavior', 'substitutions/requests'],
    // The old Blade screens, translated in OB1–OB3.
    [process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv']: ['analytics', 'analytics/reports'],
};
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

const visit = async (page, locale, path) => {
    const url = `${BASE}/${locale}/${path}`;
    try {
        const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        if (!response || response.status() >= 400) {
            check(`/${locale}/${path} answers`, false, `HTTP ${response?.status()}`);
            return;
        }
        const seen = await page.evaluate(() => {
            // The screen's own width: a phone zooms a page that is too wide out,
            // so innerWidth grows with it and a check against it never fails
            // (the analytics charts, 2026-10-20).
            const width = document.documentElement.clientWidth;
            let culprit = null;
            if (document.documentElement.scrollWidth > width + 1) {
                for (const el of document.querySelectorAll('body *')) {
                    const box = el.getBoundingClientRect();
                    if (box.width > 0 && box.right > width + 1 && getComputedStyle(el).position !== 'fixed') {
                        culprit = `${el.tagName.toLowerCase()}.${String(el.className).slice(0, 60)}`;
                        break;
                    }
                }
            }
            const main = document.querySelector('[data-phone-main]');
            return { culprit, main: main ? Math.round(main.getBoundingClientRect().top + window.scrollY) : null };
        });
        check(`/${locale}/${path} fits the phone`, seen.culprit === null, seen.culprit ?? '');
        if (seen.main !== null) {
            check(`/${locale}/${path} shows what it is for on the first screen`, seen.main <= FIRST_SCREEN, `starts at ${seen.main}px`);
        }
    } catch (e) {
        check(`/${locale}/${path} loads`, false, String(e).slice(0, 160));
    }
};

for (const locale of ['en', 'dv']) {
    for (const path of PAGES) {
        const page = await context.newPage();
        try {
            await visit(page, locale, path);
        } finally {
            await page.close();
        }
    }
}

for (const [who, paths] of Object.entries(APP)) {
    const signedIn = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const page = await signedIn.newPage();
    await page.goto(`${BASE}/en/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    for (const locale of ['en', 'dv']) {
        for (const path of paths) {
            await visit(page, locale, path);
        }
    }
    await signedIn.close();
}
await browser.close();

let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    if (!ok) console.log(`FAIL ${step}${detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
