/**
 * Does the public website read right to left in Dhivehi and Arabic?
 * (STATUS §5lp — until then every /dv and /ar page was laid out left to
 * right, the header, the tiles, the footer and the bottom bar alike.)
 *
 * For the home page and five others, at desk and phone width:
 *   1. /dv and /ar pages say dir="rtl"; /en still says ltr;
 *   2. the logo sits on the right in Dhivehi and Arabic, on the left in
 *      English;
 *   3. nothing runs wider than the screen;
 *   4. on a phone the bottom bar is there, across the whole width.
 *
 * Read-only.
 *
 *   node scripts/smoke/public-rtl.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM, SMOKE_SHOTS (a folder for screenshots).
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PAGES = ['/', '/courses', '/shop', '/library', '/news', '/contact'];
const SIZES = { desk: { width: 1280, height: 900 }, phone: { width: 390, height: 844 } };

const browser = await chromium.launch({
    args: ['--no-first-run', '--no-default-browser-check', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

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

for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => {
        check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200));
        await finish();
    });
}

async function open(size) {
    const context = await browser.newContext({ viewport: SIZES[size], ...(size === 'phone' ? { isMobile: true, hasTouch: true } : {}) });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`HTTP ${response.status()} ${response.url()}`);
        }
    });

    return page;
}

// Where things are, as the reader sees them.
const measure = (page) => page.evaluate(() => {
    const logo = document.querySelector('header a[aria-label] img, header a[aria-label] svg, nav a[aria-label] img, nav a[aria-label] svg');
    const box = logo?.getBoundingClientRect();
    // The site's bar, or on the Bookstore its own tabs (STATUS §5lt).
    const bar = document.querySelector('[data-testid="bottom-bar"]') ?? document.querySelector('[data-testid="shop-bottom-bar"]');
    const barBox = bar && getComputedStyle(bar).display !== 'none' ? bar.getBoundingClientRect() : null;

    return {
        dir: document.documentElement.getAttribute('dir'),
        logoCentre: box ? box.left + box.width / 2 : null,
        width: window.innerWidth,
        overflow: document.documentElement.scrollWidth - window.innerWidth,
        bar: barBox ? { left: Math.round(barBox.left), right: Math.round(barBox.right) } : null,
    };
});

for (const size of Object.keys(SIZES)) {
    const page = await open(size);
    for (const path of PAGES) {
        for (const locale of ['dv', 'ar', 'en']) {
            await page.goto(`${BASE}/${locale}${path === '/' ? '' : path}`, { waitUntil: 'networkidle' });
            const m = await measure(page);
            const rtl = locale !== 'en';
            const side = m.logoCentre === null ? 'none' : (m.logoCentre > m.width / 2 ? 'right' : 'left');
            const label = `${size} ${locale}${path}`;
            check(`${label}: dir is ${rtl ? 'rtl' : 'ltr'}, the logo on the ${rtl ? 'right' : 'left'}`, m.dir === (rtl ? 'rtl' : 'ltr') && side === (rtl ? 'right' : 'left'), `dir=${m.dir}, logo ${side}`);
            check(`${label}: nothing wider than the screen`, m.overflow <= 1, `${m.overflow}px over`);
            if (size === 'phone') {
                check(`${label}: the bottom bar spans the phone`, m.bar !== null && m.bar.left <= 0 && m.bar.right >= m.width - 1, JSON.stringify(m.bar));
            }
            if (process.env.SMOKE_SHOTS && path === '/' && locale !== 'ar') {
                await page.screenshot({ path: `${process.env.SMOKE_SHOTS}/public-rtl-${size}-${locale}.png`, fullPage: false });
            }
        }
    }
    await page.context().close();
}

await finish();
