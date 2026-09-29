/**
 * Do the events and the gallery open from their own lists? (STATUS §5ku.)
 *
 * Both lists built their links with the language where the id belongs
 * (`/en/events/en?5`), so every card led to an error, and the event page's
 * registration form posted to an address that did not exist. A visitor:
 *
 *   1. opens /events, clicks the first event, and lands on its page;
 *   2. registers from that page's form and is told it went through;
 *   3. downloads the event's calendar file from the page's link;
 *   4. opens /gallery, clicks the first album, lands on it, and its
 *      breadcrumb leads back to /gallery;
 *   5. an event that does not exist is a 404 page, not a 500 that prints
 *      the exception.
 *
 * Writes one event registration (email smoke-events-<time>@akuru.test).
 * Needs a published public event and album; a step says "skipped" without.
 *
 *   node scripts/smoke/events.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';

const HERMETIC_ARGS = [
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter,InterestFeedContentSuggestions',
    '--no-first-run',
    '--no-default-browser-check',
];

const browser = await chromium.launch({
    args: HERMETIC_ARGS,
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const path = (url) => new URL(url).pathname.replace(/^\/(en|dv|ar)(?=\/|$)/, '') || '/';

for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => {
        check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200));
        await finish();
    });
}

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

async function visitor(width, height) {
    const context = await browser.newContext({ viewport: { width, height } });
    context.setDefaultNavigationTimeout(60000);
    // Only the site itself: Google Translate, fonts and analytics stay out of the walk.
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${width}px: HTTP ${response.status()} ${response.url()}`);
        }
    });
    return page;
}

// ------------------------------------------------------------ 1. the events list opens an event
const page = await visitor(1280, 900);
await page.goto(`${BASE}/en/events`, { waitUntil: 'networkidle' });
const eventLink = page.locator('a[href*="/events/"]:not([href$="/events"]):not([href*="calendar"])').first();
if ((await eventLink.count()) === 0) {
    check('an event on /events opens its page', true, 'skipped: no published public event');
} else {
    const href = await eventLink.getAttribute('href');
    check('the event\'s link carries the event, not the language', !/\/events\/(en|dv|ar)(\?|$)/.test(href), href);
    await Promise.all([page.waitForURL((url) => /\/events\/[^/]+$/.test(url.pathname)), eventLink.click()]);
    await page.waitForLoadState('networkidle');
    const title = (await page.locator('h1').first().innerText()).trim();
    check('an event on /events opens its page', path(page.url()).startsWith('/events/') && title.length > 0, `${path(page.url())} — ${title}`);

    // ------------------------------------------------------------ 2. register from the page
    const form = page.locator('form[action*="/register"]');
    if ((await form.count()) === 0) {
        check('a visitor registers from the event page', true, 'skipped: this event takes no registrations');
    } else {
        await form.locator('input[name="name"]').fill('SMOKE Events Visitor');
        await form.locator('input[name="email"]').fill(`smoke-events-${Date.now()}@akuru.test`);
        await Promise.all([page.waitForLoadState('networkidle'), form.locator('button[type="submit"]').click()]);
        await page.waitForLoadState('networkidle');
        const said = await page.locator('text=Registration submitted successfully').count();
        check('a visitor registers from the event page', said > 0 && path(page.url()).startsWith('/events/'), path(page.url()));
    }

    // ------------------------------------------------------------ 3. the calendar file
    const ics = await page.locator('a[href*="calendar.ics"]').first().getAttribute('href');
    const file = await page.request.get(new URL(ics, BASE).toString());
    check('the page\'s calendar link downloads the event', file.ok() && (file.headers()['content-type'] ?? '').startsWith('text/calendar') && (await file.text()).includes('BEGIN:VEVENT'), `HTTP ${file.status()}`);
}

// ------------------------------------------------------------ 4. the gallery opens an album
await page.goto(`${BASE}/en/gallery`, { waitUntil: 'networkidle' });
const albumLink = page.locator('a[href*="/gallery/"]').first();
if ((await albumLink.count()) === 0) {
    check('an album on /gallery opens its page', true, 'skipped: no published public album');
} else {
    const href = await albumLink.getAttribute('href');
    check('the album\'s link carries the album, not the language', !/\/gallery\/(en|dv|ar)(\?|$)/.test(href), href);
    await Promise.all([page.waitForURL((url) => /\/gallery\/[^/]+$/.test(url.pathname)), albumLink.click()]);
    await page.waitForLoadState('networkidle');
    check('an album on /gallery opens its page', path(page.url()).startsWith('/gallery/') && (await page.locator('h1').count()) > 0, path(page.url()));
    const back = page.locator('.max-w-6xl a[href$="/gallery"]').first();
    await Promise.all([page.waitForURL((url) => /\/gallery$/.test(url.pathname)), back.click()]);
    check('the album\'s breadcrumb leads back to /gallery', path(page.url()) === '/gallery', path(page.url()));
}

// ------------------------------------------------------------ 5. a missing event
const missing = await page.goto(`${BASE}/en/events/smoke-no-such-event`, { waitUntil: 'domcontentloaded' });
const body = await page.content();
check('a missing event is a 404 page, not a 500 with the exception', missing.status() === 404 && !body.includes('No query results'), `HTTP ${missing.status()}`);
// That 404 is expected; it is not a server problem.

await finish();
