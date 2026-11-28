/**
 * Is the Bookstore office six short pages, not one long one? On a phone, in
 * Dhivehi. (Admin audit 2026-11-27, ST2, STATUS §5uu.)
 *
 * At 390 × 844 under /dv the super admin opens /admin/bookshop: six tabs,
 * Today filled in, and only Today's sections on the page. Each tab opens its
 * own page with its own sections and none of another's, reads right to
 * left, scrolls nothing sideways, stacks its tables, and is far shorter
 * than the 10,600 px the single page was. Under /en the same, left to right.
 *
 *   node scripts/smoke/office-pages.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
// What each page must carry, and one thing of another page's it must not.
const PAGES = {
    today: { own: ['listings', 'office-orders'], not: 'vendor-table' },
    shops: { own: ['invite-vendor', 'vendor-table'], not: 'office-orders' },
    storefront: { own: ['office-home', 'category-list'], not: 'vendor-table' },
    checks: { own: ['office-reviews', 'office-questions'], not: 'office-orders' },
    money: { own: ['office-money', 'office-refunds'], not: 'vendor-table' },
    reports: { own: ['office-insights', 'office-low-stock'], not: 'office-orders' },
};
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
try {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="identifier"]', STAFF);
    await page.fill('input[name="password"]', PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });

    await page.goto(`${BASE}/dv/admin/bookshop`, { waitUntil: 'networkidle' });
    const tabs = await page.locator('[data-testid="office-tabs"] a').evaluateAll((as) => as.map((a) => [a.dataset.testid.replace('office-tab-', ''), a.getAttribute('aria-current'), Math.round(a.getBoundingClientRect().height)]));
    check('/dv the office has six tabs, Today the one filled in, each thumb-sized', tabs.map((t) => t[0]).join(',') === Object.keys(PAGES).join(',') && tabs[0][1] === 'page' && tabs.every((t) => t[2] >= 32), JSON.stringify(tabs));

    const faults = [];
    let tallest = 0;
    for (const [tab, want] of Object.entries(PAGES)) {
        await page.locator(`[data-testid="office-tab-${tab}"]`).click();
        await page.waitForSelector(`[data-phone-main][data-testid="office-page-${tab}"]`, { timeout: 15000 }).catch(() => faults.push(`${tab} did not open`));
        await page.waitForLoadState('networkidle');
        const seen = await page.evaluate(({ own, not }) => ({
            missing: own.filter((id) => !document.querySelector(`[data-testid="${id}"]`)),
            foreign: Boolean(document.querySelector(`[data-testid="${not}"]`)),
            dir: document.documentElement.dir,
            sideways: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            unstacked: [...document.querySelectorAll('main table')].filter((t) => !t.classList.contains('table-stack') && !t.hasAttribute('data-phone-scroll')).length,
            height: document.documentElement.scrollHeight,
            current: document.querySelector('[data-testid="office-tabs"] [aria-current="page"]')?.dataset.testid,
        }), want);
        tallest = Math.max(tallest, seen.height);
        if (seen.missing.length) faults.push(`${tab} lacks ${seen.missing.join(', ')}`);
        if (seen.foreign) faults.push(`${tab} carries ${want.not}`);
        if (seen.dir !== 'rtl') faults.push(`${tab} reads ${seen.dir}`);
        if (seen.sideways > 1) faults.push(`${tab} scrolls ${seen.sideways} px sideways`);
        if (seen.unstacked) faults.push(`${tab} has ${seen.unstacked} table(s) not stacked`);
        if (seen.current !== `office-tab-${tab}`) faults.push(`${tab} is not the tab filled in`);
        if (SHOTS) await page.screenshot({ path: `${SHOTS}/st2-office-${tab}-dv.png` });
    }
    check('/dv each tab opens its own page: its sections, none of another\'s, right to left, nothing sideways, tables stacked', faults.length === 0, faults.join(' | '));
    check('/dv no office page is as long as the single page was (10,600 px)', tallest > 0 && tallest < 10600, `${tallest} px the tallest`);

    await page.goto(`${BASE}/en/admin/bookshop/office/money`, { waitUntil: 'networkidle' });
    check('/en a page opens by its own address, left to right, nothing sideways', await page.evaluate(() => document.documentElement.dir) === 'ltr' && await page.locator('[data-testid="office-page-money"]').count() === 1 && await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1);
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
}
for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
