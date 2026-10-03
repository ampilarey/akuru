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
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;

const PAGES = [
    // The landings that send an administrator into the panel (the owner's phone screenshot, 2026-09-26).
    '/en/admin', '/en/school', '/en/dashboard', '/en/dashboard/numbers', '/en/portal/overview',
    '/en/admin/users', '/en/admin/users/otp-abuse', '/en/admin/settings',
    '/en/admin/enrollments', '/en/admin/enrollments/payments',
    '/en/admin/instructors', '/en/admin/instructors/create',
    '/en/admin/public-site/pages', '/en/admin/public-site/pages/create',
    '/en/admin/public-site/courses', '/en/admin/public-site/courses/create', '/en/admin/public-site/courses/deleted',
    '/en/admin/public-site/news', '/en/admin/public-site/news/create', '/en/admin/public-site/news/categories',
    '/en/admin/public-site/daily-content', '/en/admin/public-site/daily-content/create', '/en/admin/public-site/daily-content/queue',
    '/en/admin/public-site/daily-subscriptions', '/en/admin/public-site/leads', '/en/admin/public-site/funnel',
    '/en/admin/prayer-times/islands', '/en/admin/prayer-times/groups', '/en/admin/prayer-times/groups/create',
    '/en/admin/prayer-times/broadcasts', '/en/admin/prayer-times/broadcasts/create', '/en/admin/prayer-times/import',
    '/en/admin/operations', '/en/admin/operations/features', '/en/admin/translations',
    '/en/admin/commerce', '/en/admin/library', '/en/admin/library/reading-alerts',
    // The screens that arrived after the 2026-09-28 sweep and were not in it
    // (ADMIN_PANEL.md M1, 2026-10-02): the reviewers page measured 79 px wider
    // than the phone for four days and nothing said so.
    '/en/admin/library/insights', '/en/admin/library/promotions', '/en/admin/library/reviewers', '/en/admin/library/settings',
    '/en/admin/lending',
    '/en/admin/pronunciation', '/en/admin/bookshop',
    '/en/admin/bookshop/akuru', '/en/admin/bookshop/campaigns', '/en/admin/bookshop/complaints', '/en/admin/bookshop/credit', '/en/admin/bookshop/customers',
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
// A console error is a broken screen the layout checks cannot see: a React
// render error is swallowed by the shell and the page is simply blank — the
// sweep saw one as "no menu button" (STATUS §5ns). The browser's own 404 for
// a missing favicon is not one.
page.on('console', (message) => {
    if (message.type() === 'error' && !/favicon/i.test(message.text())) {
        problems.push(`console on ${page.url().replace(BASE, '')}: ${message.text().slice(0, 140)}`);
    }
});
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
    // The phone's width, not `innerWidth`: under mobile emulation (as on a
    // real phone) a document wider than the screen widens the *layout
    // viewport* to match, so `innerWidth` grows with the overflow and
    // `scrollWidth - innerWidth` reads 0 for exactly the page that is broken.
    // That is how a 608 px header on a 390 px phone passed this walk while
    // Safari zoomed the whole page out to fit it (STATUS §5jq).
    const vw = Math.min(window.innerWidth, window.screen.width);
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

// The Institute's tab bar (ADMIN_PANEL.md §7 M7, STATUS §5nu): on every
// screen of the workspace, six tabs of thumb size (Home, Website, Shops,
// Settings, System, Alerts — ADMIN_PANEL.md §8), inside the phone, with
// the page padded so its last line is not under it.
const tabBar = () => page.evaluate(() => {
    const vw = Math.min(window.innerWidth, window.screen.width);
    const vh = Math.min(window.innerHeight, window.screen.height);
    const bar = document.querySelector('[data-testid="shell-tabs"]');
    if (!bar) return { present: false };
    const b = bar.getBoundingClientRect();
    const tabs = [...bar.querySelectorAll('a, button')].map((el) => {
        const r = el.getBoundingClientRect();
        return { name: el.getAttribute('data-testid'), w: Math.round(r.width), h: Math.round(r.height), text: (el.textContent || '').trim() };
    });
    const main = document.querySelector('main');
    const pad = main ? parseFloat(getComputedStyle(main).paddingBottom) : 0;
    return { present: true, inside: b.left >= -1 && b.right <= vw + 1 && Math.abs(b.bottom - vh) <= 1, height: Math.round(b.height), tabs, padded: pad >= b.height };
});

const overflowing = [];
const swipes = [];
const noMenu = [];
const noBar = [];
const smallTabs = [];
const unpadded = [];
for (const path of PAGES) {
    const response = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) {
        check(path, false, `HTTP ${response?.status()}`);
        continue;
    }
    const bar = await tabBar();
    if (!bar.present || !bar.inside || bar.tabs.length !== 6) noBar.push(`${path.replace('/en/admin', '')}${bar.present ? ` (${bar.tabs.length} tabs${bar.inside ? '' : ', outside the phone'})` : ''}`);
    else {
        for (const tab of bar.tabs) if (tab.w < 44 || tab.h < 44 || !tab.text) smallTabs.push(`${path.replace('/en/admin', '')} ${tab.name} ${tab.w}×${tab.h}`);
        if (!bar.padded) unpadded.push(path.replace('/en/admin', ''));
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

check('the Institute\'s tab bar — Home, Website, Shops, System, Alerts — is at the foot of every one', noBar.length === 0, noBar.join(', '));
check('every tab is thumb-sized and named', smallTabs.length === 0, smallTabs.slice(0, 6).join(', '));
check('and the page is padded so its last line is not under the bar', unpadded.length === 0, unpadded.join(', '));

// A tab opens its sheet; a screen in the sheet opens; the tab then reads as current.
await page.goto(`${BASE}/en/admin`, { waitUntil: 'networkidle' });
const homeCurrent = await page.getAttribute('[data-testid="tab-home"]', 'aria-current');
check('on the hub the Home tab is the current one', homeCurrent === 'page', `aria-current=${homeCurrent}`);
await page.click('[data-testid="tab-panel_system"]');
await page.waitForSelector('[data-testid="shell-tab-sheet"]', { timeout: 5000 }).catch(() => {});
const sheet = await page.evaluate(() => {
    const el = document.querySelector('[data-testid="shell-tab-sheet"]');
    if (!el) return null;
    const vh = Math.min(window.innerHeight, window.screen.height);
    const r = el.getBoundingClientRect();
    const bar = document.querySelector('[data-testid="shell-tabs"]').getBoundingClientRect();
    return { title: document.getElementById('shell-tab-sheet-title')?.textContent, links: [...el.querySelectorAll('a')].map((a) => a.getAttribute('href')), aboveBar: Math.abs(r.bottom - bar.top) <= 1, inside: r.bottom <= vh + 1 };
});
check('System opens a sheet of its screens, sitting on the bar', !!sheet && sheet.title === 'System' && sheet.aboveBar && sheet.inside && sheet.links.some((h) => h.endsWith('/admin/users')) && sheet.links.some((h) => h.endsWith('/admin/operations')), sheet ? `${sheet.title}: ${sheet.links.join(', ')}` : 'no sheet');
await Promise.all([page.waitForURL(/\/admin\/users$/, { timeout: 15000 }).catch(() => {}), page.click('[data-testid="shell-tab-sheet"] a[href$="/admin/users"]')]);
await page.waitForLoadState('networkidle');
const afterSheet = await page.evaluate(() => ({
    sheet: !!document.querySelector('[data-testid="shell-tab-sheet"]'),
    current: document.querySelector('[data-testid="tab-panel_system"]')?.getAttribute('aria-current'),
    url: location.pathname,
}));
check('Manage users opens from the sheet, the sheet closes and System reads as current', afterSheet.url.endsWith('/admin/users') && !afterSheet.sheet && afterSheet.current === 'true', JSON.stringify(afterSheet));
await Promise.all([page.waitForURL(/\/portal\/notifications/, { timeout: 15000 }).catch(() => {}), page.click('[data-testid="tab-alerts"]')]);
check('Alerts opens the notifications', page.url().includes('/portal/notifications'), page.url());

// The Settings tab (ADMIN_PANEL.md §8): every product's settings in one
// sheet, and the Bookstore's — a section of its office page — opens scrolled
// to that section.
await page.click('[data-testid="tab-panel_settings"]');
await page.waitForSelector('[data-testid="shell-tab-sheet"]', { timeout: 5000 }).catch(() => {});
const settingsSheet = await page.evaluate(() => {
    const el = document.querySelector('[data-testid="shell-tab-sheet"]');
    return el ? { title: document.getElementById('shell-tab-sheet-title')?.textContent, links: [...el.querySelectorAll('a')].map((a) => a.getAttribute('href')) } : null;
});
const settingsWanted = ['/admin/settings', '/admin/library/settings', '/admin/bookshop#settings', '/admin/translations'];
check('Settings opens a sheet with the system, Library, Bookstore and translation settings', !!settingsSheet && settingsSheet.title === 'Settings' && settingsWanted.every((w) => settingsSheet.links.some((h) => h.endsWith(w))), settingsSheet ? settingsSheet.links.join(', ') : 'no sheet');
await Promise.all([page.waitForURL(/\/admin\/bookshop/, { timeout: 20000 }).catch(() => {}), page.click('[data-testid="shell-tab-sheet"] a[href$="/admin/bookshop#settings"]')]);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(600);
const bookshopSettings = await page.evaluate(() => {
    const el = document.getElementById('settings');
    if (!el) return { present: false };
    const r = el.getBoundingClientRect();
    const vh = Math.min(window.innerHeight, window.screen.height);
    return { present: true, top: Math.round(r.top), inView: r.top >= -2 && r.top < vh, heading: document.getElementById('office-settings-title')?.textContent, hash: location.hash };
});
check('Bookstore settings opens the office scrolled to its Settings section', bookshopSettings.present && bookshopSettings.inView && bookshopSettings.heading === 'Bookstore settings', JSON.stringify(bookshopSettings));
check('and no screen raised a page, console or server error', problems.length === 0, problems.slice(0, 3).join(' | '));

await finish();
