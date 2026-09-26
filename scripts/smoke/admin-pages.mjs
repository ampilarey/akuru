/**
 * Every admin page, desktop and phone (the owner, 2026-09-26: "check each and
 * every admin page, desktop and mobile — the layouts, all the tabs are there
 * like home, back").
 *
 * As a super admin, every admin landing page plus the detail, create and edit
 * pages discovered from each index, at 1400 × 950 and at 390 × 844. On each:
 *   - it loads (200) with a heading;
 *   - the navigation is there — the Blade bar with Dashboard, Enrollments and
 *     More (the hamburger on a phone), or the Inertia shell with its primary
 *     bar, More and Alerts;
 *   - a way home: a link to /dashboard;
 *   - on a page that is not an index, a way back: a link to its section's index
 *     in the page content;
 *   - nothing wider than the viewport, nothing cut off inside a hidden-overflow
 *     ancestor, and the menu button inside the viewport.
 * Every page is listed with what it lacks; the checks at the end count them.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/admin-pages.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN (a super admin), SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Landing pages, each with the index it belongs to (null: it is an index).
const LANDINGS = [
    ['/en/dashboard', null], ['/en/portal/overview', null],
    ['/en/admin/users', null], ['/en/admin/users/otp-abuse', '/en/admin/users'], ['/en/admin/settings', null],
    ['/en/admin/enrollments', null], ['/en/admin/enrollments/payments', '/en/admin/enrollments'],
    ['/en/admin/instructors', null], ['/en/admin/instructors/create', '/en/admin/instructors'],
    ['/en/admin/public-site/pages', null], ['/en/admin/public-site/pages/create', '/en/admin/public-site/pages'], ['/en/admin/public-site/pages/1', '/en/admin/public-site/pages'],
    ['/en/admin/public-site/courses', null], ['/en/admin/public-site/courses/create', '/en/admin/public-site/courses'], ['/en/admin/public-site/courses/deleted', '/en/admin/public-site/courses'],
    ['/en/admin/public-site/research', null], ['/en/admin/public-site/research/create', '/en/admin/public-site/research'],
    ['/en/admin/public-site/daily-content', null], ['/en/admin/public-site/daily-content/create', '/en/admin/public-site/daily-content'], ['/en/admin/public-site/daily-content/queue', '/en/admin/public-site/daily-content'],
    ['/en/admin/public-site/daily-subscriptions', null], ['/en/admin/public-site/leads', null], ['/en/admin/public-site/funnel', null],
    ['/en/admin/prayer-times/islands', null], ['/en/admin/prayer-times/groups', '/en/admin/prayer-times/islands'], ['/en/admin/prayer-times/groups/create', '/en/admin/prayer-times/groups'],
    ['/en/admin/prayer-times/broadcasts', '/en/admin/prayer-times/islands'], ['/en/admin/prayer-times/broadcasts/create', '/en/admin/prayer-times/broadcasts'], ['/en/admin/prayer-times/import', '/en/admin/prayer-times/islands'],
    ['/en/admin/operations', null], ['/en/admin/operations/features', '/en/admin/operations'], ['/en/admin/translations', null],
    ['/en/admin/commerce', null], ['/en/admin/library', null], ['/en/admin/library/reading-alerts', '/en/admin/library'],
    ['/en/admin/pronunciation', null], ['/en/admin/bookshop', null],
];

// Detail pages found on an index: [index, link pattern, the index they belong to].
const DISCOVER = [
    ['/en/admin/enrollments', /\/admin\/enrollments\/\d+$/, '/en/admin/enrollments'],
    ['/en/admin/instructors', /\/admin\/instructors\/\d+\/edit$/, '/en/admin/instructors'],
    ['/en/admin/public-site/pages', /\/admin\/public-site\/pages\/[^/]+\/edit$/, '/en/admin/public-site/pages'],
    ['/en/admin/public-site/pages', /\/admin\/public-site\/pages\/[^/]+$/, '/en/admin/public-site/pages'],
    ['/en/admin/public-site/courses', /\/admin\/public-site\/courses\/[^/]+\/edit$/, '/en/admin/public-site/courses'],
    ['/en/admin/public-site/research', /\/admin\/public-site\/research\/\d+\/edit$/, '/en/admin/public-site/research'],
    ['/en/admin/public-site/daily-content', /\/admin\/public-site\/daily-content\/\d+\/edit$/, '/en/admin/public-site/daily-content'],
    ['/en/admin/prayer-times/groups', /\/admin\/prayer-times\/groups\/\d+\/edit$/, '/en/admin/prayer-times/groups'],
    ['/en/admin/prayer-times/broadcasts', /\/admin\/prayer-times\/broadcasts\/\d+\/edit$/, '/en/admin/prayer-times/broadcasts'],
    ['/en/admin/bookshop', /\/admin\/bookshop\/invoices\/\d+$/, '/en/admin/bookshop'],
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

async function signIn(viewport, mobile) {
    const context = await browser.newContext({ viewport, isMobile: mobile, hasTouch: mobile });
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

    return page;
}

// What the page offers, measured in the browser.
const inspect = (parentIndex) => (page) => page.evaluate((parentIndex) => {
    const vw = window.innerWidth;
    const strip = (h) => (h || '').replace(/^https?:\/\/[^/]+/, '').replace(/^\/(en|dv|ar)(?=\/|$)/, '');
    const links = Array.from(document.querySelectorAll('a[href]'));
    const mainLinks = Array.from(document.querySelectorAll('main a[href], [role="main"] a[href], .container a[href]'));
    const blade = !!document.querySelector('button[aria-controls="nav-mobile-menu"]');
    const inertia = !!document.querySelector('button[aria-controls="app-shell-more"]');
    const navText = (document.querySelector('nav')?.textContent || '').replace(/\s+/g, ' ');
    const heading = document.querySelector('main h1, main h2, h1, h2')?.textContent?.trim().replace(/\s+/g, ' ').slice(0, 50) || '';
    const home = links.some((a) => ['/dashboard', '/'].includes(strip(a.getAttribute('href')).replace(/\/$/, '') || '/'));
    const back = parentIndex === null ? true : mainLinks.some((a) => strip(a.getAttribute('href')) === parentIndex.replace(/^\/en/, ''));
    const navOk = blade
        ? /Dashboard|Today/.test(navText) && /More/.test(navText)
        : inertia ? /More/.test(navText) && /Alerts|އެލާޓް|التنبيهات/.test(navText) : false;
    const menu = document.querySelector('button[aria-controls="nav-mobile-menu"], button[aria-controls="app-shell-more"], button[aria-controls="nav-more-menu"]');
    const menus = Array.from(document.querySelectorAll('button[aria-controls="nav-mobile-menu"], button[aria-controls="app-shell-more"], button[aria-controls="nav-more-menu"]'));
    const menuVisible = menus.some((m) => { const r = m.getBoundingClientRect(); return r.width > 0 && r.right <= vw + 1 && r.left >= -1; });
    const scrolls = (el) => { const o = getComputedStyle(el).overflowX; return (o === 'auto' || o === 'scroll') && el.scrollWidth > el.clientWidth + 1; };
    const clipped = [];
    for (const el of document.querySelectorAll('body *')) {
        if (['HTML', 'BODY', 'SCRIPT', 'STYLE'].includes(el.tagName)) continue;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.right <= vw + 1) continue;
        const p = el.parentElement;
        if (p && p.tagName !== 'BODY' && p.getBoundingClientRect().right >= r.right - 1) continue;
        let a = p; let reachable = false;
        while (a && a !== document.body) { if (scrolls(a)) { reachable = true; break; } a = a.parentElement; }
        if (!reachable) clipped.push(`${el.tagName.toLowerCase()} "${(el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 24)}"→+${Math.round(r.right - vw)}px`);
        if (clipped.length >= 3) break;
    }

    return { shell: blade ? 'blade' : inertia ? 'inertia' : 'none', heading, home, back, navOk, menuVisible, overflow: document.documentElement.scrollWidth - vw, clipped, hasMenu: !!menu };
}, parentIndex);

const desktop = await signIn({ width: 1400, height: 950 }, false);

// Discover the detail pages from the indexes, once.
const pages = [...LANDINGS];
const empty = [];
for (const [index, pattern, parent] of DISCOVER) {
    await desktop.goto(`${BASE}${index}`, { waitUntil: 'networkidle' });
    const hrefs = await desktop.locator('a[href]').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
    const found = hrefs.map((h) => (h || '').replace(/^https?:\/\/[^/]+/, '')).find((h) => pattern.test(h) && !/\/(export|create)$/.test(h));
    if (found) pages.push([found, parent]); else empty.push(`${index.replace('/en/admin', '')} (${pattern.source.replace(/\\\//g, '/').slice(0, 40)})`);
}
if (empty.length) console.log(`no rows to discover a detail page from: ${empty.join(', ')}\n`);
check(`${pages.length} admin pages to check (${LANDINGS.length} landings + ${pages.length - LANDINGS.length} detail pages found on the indexes)`, pages.length > LANDINGS.length, pages.slice(LANDINGS.length).map(([p]) => p.replace('/en/admin', '')).join(', '));

const phone = await signIn({ width: 390, height: 844 }, true);

const lacking = { desktop: [], phone: [] };
for (const [device, page] of [['desktop', desktop], ['phone', phone]]) {
    for (const [path, parent] of pages) {
        const response = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
        const short = path.replace('/en/admin', '').replace('/en', '');
        if (!response || response.status() !== 200) { lacking[device].push(`${short}: HTTP ${response?.status()}`); continue; }
        const m = await inspect(parent)(page);
        const lacks = [];
        if (!m.heading) lacks.push('no heading');
        if (m.shell === 'none') lacks.push('no navigation');
        if (!m.navOk) lacks.push('nav missing its entries');
        if (!m.home) lacks.push('no home link');
        if (!m.back) lacks.push(`no back link to ${parent?.replace('/en/admin', '')}`);
        if (m.hasMenu && !m.menuVisible) lacks.push('menu button off-screen');
        if (m.overflow > 1) lacks.push(`page +${m.overflow}px wide`);
        if (m.clipped.length) lacks.push(`cut off: ${m.clipped.join(', ')}`);
        if (lacks.length) lacking[device].push(`${short}: ${lacks.join('; ')}`);
    }
}

for (const device of ['desktop', 'phone']) {
    check(`every admin page on ${device} loads with a heading, its navigation, a home link, a back link where it needs one, and nothing cut off`, lacking[device].length === 0, lacking[device].length ? `\n      ${lacking[device].join('\n      ')}` : '');
}

await finish();
