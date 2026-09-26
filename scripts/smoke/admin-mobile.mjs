/**
 * Every admin screen on a phone (the admin-panel layout audit, STATUS §5hu).
 *
 * Loads each admin landing page at 390 × 844 as a super admin and asks the
 * only question a phone user asks first: does the page fit? A screen whose
 * content is wider than the viewport scrolls sideways, hides its right-hand
 * columns and its action buttons, and reads as broken. Each overflowing page
 * is named with the element that pushes it out, so the fix is one wrapper,
 * not a guess. The menu button must be visible on every page.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/admin-mobile.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN (a super admin), SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS (a directory for screenshots).
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;

const PAGES = [
    // The landings that send an administrator into the panel (the owner's phone screenshot, 2026-09-26).
    '/en/dashboard', '/en/portal/overview',
    '/en/admin/users', '/en/admin/users/otp-abuse', '/en/admin/settings',
    '/en/admin/enrollments', '/en/admin/enrollments/payments',
    '/en/admin/instructors', '/en/admin/instructors/create',
    '/en/admin/public-site/pages', '/en/admin/public-site/pages/create',
    '/en/admin/public-site/courses', '/en/admin/public-site/courses/create', '/en/admin/public-site/courses/deleted',
    '/en/admin/public-site/research', '/en/admin/public-site/research/create',
    '/en/admin/public-site/daily-content', '/en/admin/public-site/daily-content/create', '/en/admin/public-site/daily-content/queue',
    '/en/admin/public-site/daily-subscriptions', '/en/admin/public-site/leads', '/en/admin/public-site/funnel',
    '/en/admin/prayer-times/islands', '/en/admin/prayer-times/groups', '/en/admin/prayer-times/groups/create',
    '/en/admin/prayer-times/broadcasts', '/en/admin/prayer-times/broadcasts/create', '/en/admin/prayer-times/import',
    '/en/admin/operations', '/en/admin/operations/features', '/en/admin/translations',
    '/en/admin/commerce', '/en/admin/library', '/en/admin/library/reading-alerts',
    '/en/admin/pronunciation', '/en/admin/bookshop',
];

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

const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
context.setDefaultNavigationTimeout(60000);
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();
page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
page.on('response', (response) => {
    if (response.status() >= 500) {
        problems.push(`HTTP ${response.status()} ${response.url()}`);
    }
});
await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

// Everything that sticks out past the phone's right edge, outermost first, and
// whether a swipe can reach it (an ancestor scrolls sideways) or it is simply
// cut off (no ancestor does: the page-level overflow number cannot see this,
// because an `overflow: hidden` ancestor swallows it).
const measure = () => page.evaluate(() => {
    const vw = window.innerWidth;
    const doc = document.documentElement.scrollWidth;
    const name = (el) => {
        const id = el.getAttribute('data-testid') || el.id || (typeof el.className === 'string' ? el.className.split(' ').filter(Boolean).slice(0, 3).join('.') : '');
        const text = (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30);
        return `${el.tagName.toLowerCase()}${id ? '[' + id.slice(0, 40) + ']' : ''}${text ? ' "' + text + '"' : ''}`;
    };
    const scrolls = (el) => {
        const o = getComputedStyle(el).overflowX;
        return (o === 'auto' || o === 'scroll') && el.scrollWidth > el.clientWidth + 1;
    };
    const clipped = [];
    const swipe = [];
    for (const el of document.querySelectorAll('body *')) {
        if (['HTML', 'BODY', 'SCRIPT', 'STYLE'].includes(el.tagName)) continue;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.right <= vw + 1) continue;
        const p = el.parentElement;
        if (p && p.tagName !== 'BODY' && p.getBoundingClientRect().right >= r.right - 1) continue; // its parent is reported instead
        let a = p; let reachable = false;
        while (a && a !== document.body) { if (scrolls(a)) { reachable = true; break; } a = a.parentElement; }
        (reachable ? swipe : clipped).push(`${name(el)}→+${Math.round(r.right - vw)}px`);
        if (clipped.length + swipe.length >= 6) break;
    }
    const menu = document.querySelector('button[aria-controls="nav-mobile-menu"], button[aria-controls="app-shell-more"]');

    // Visible means inside the phone, not merely rendered: a menu button pushed past the edge of a scrolling bar is not a menu button.
    const mr = menu ? menu.getBoundingClientRect() : null;

    return { overflow: doc - vw, clipped, swipe, menuVisible: mr ? mr.width > 0 && mr.right <= vw + 1 && mr.left >= -1 : false };
});

const overflowing = [];
const swipes = [];
const noMenu = [];
for (const path of PAGES) {
    const response = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) {
        check(path, false, `HTTP ${response?.status()}`);
        continue;
    }
    const m = await measure();
    if (SHOTS) {
        await page.screenshot({ path: `${SHOTS}/${path.replace(/^\/en\/admin\//, '').replace(/\//g, '_') || 'index'}.png`, fullPage: false }).catch(() => {});
    }
    if (m.overflow > 1 || m.clipped.length > 0) overflowing.push(`${path.replace('/en/admin', '')}${m.overflow > 1 ? ' page+' + m.overflow + 'px' : ''} ${m.clipped.join(', ')}`);
    if (m.swipe.length > 0) swipes.push(`${path.replace('/en/admin', '')}: ${m.swipe.join(', ')}`);
    if (!m.menuVisible) noMenu.push(path);
}
check(`${PAGES.length} admin screens load at 390 px`, results.length === 0);
check('every one shows its menu button', noMenu.length === 0, noMenu.join(', '));
check('and nothing on any of them is cut off past the phone\'s edge', overflowing.length === 0, overflowing.join(' | '));
console.log(swipes.length ? `\nreachable by a swipe (wide tables in a scrolling wrapper):\n  ${swipes.join('\n  ')}\n` : '\nno sideways scrolling anywhere\n');

await finish();
