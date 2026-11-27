/**
 * Is Settings an index of each product's own page? On a phone, in Dhivehi.
 * (Admin audit 2026-11-27, ST1, STATUS §5ut.)
 *
 * At 390 × 844 under /dv the super admin opens /admin/settings: cards for
 * the Institute's products first, the School's apart, and no form on the
 * page. Each card opens its own page (200, right to left, nothing
 * sideways), and the system, Library and Bookstore pages carry the way
 * back, which returns to the index. Under /en the same, left to right.
 *
 *   node scripts/smoke/settings-index.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
try {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="identifier"]', STAFF);
    await page.fill('input[name="password"]', PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });

    await page.goto(`${BASE}/dv/admin/settings`, { waitUntil: 'networkidle' });
    const hrefs = await page.locator('[data-testid^="settings-card-"]').evaluateAll((els) => els.map((a) => a.getAttribute('href')));
    check('/dv Settings is an index: the Institute\'s cards first, the School\'s apart', await page.locator('[data-phone-main][data-testid="settings-institute"] [data-testid^="settings-card-"]').count() >= 4 && await page.locator('[data-testid="settings-school"] [data-testid^="settings-card-"]').count() === 2, hrefs.join(' '));
    check('/dv nothing is edited on it: no form, no input', await page.locator('main form, main input, main select, main textarea').count() === 0);
    check('/dv it reads right to left and does not scroll sideways at 390 px', await page.evaluate(() => document.documentElement.dir) === 'rtl' && await sideways(page) <= 1, `${await sideways(page)} px`);
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/st1-settings-index-dv.png`, fullPage: true });

    const faults = [];
    for (const href of hrefs) {
        const response = await page.goto(`${BASE}/dv${href}`, { waitUntil: 'networkidle' });
        if (response.status() !== 200) faults.push(`${href} answered ${response.status()}`);
        if (await sideways(page) > 1) faults.push(`${href} scrolls sideways`);
    }
    check('/dv every card opens its own page, none sideways', hrefs.length >= 6 && faults.length === 0, faults.join(' | '));

    const back = [];
    for (const href of ['/admin/settings/system', '/admin/library/settings', '/admin/bookshop/settings']) {
        await page.goto(`${BASE}/dv${href}`, { waitUntil: 'networkidle' });
        const crumb = page.locator('[data-testid="settings-crumb"] a');
        if (await crumb.count() !== 1) { back.push(`${href} has no way back`); continue; }
        await Promise.all([page.waitForURL(/\/admin\/settings$/), crumb.click()]);
        if (await page.locator('[data-testid="settings-institute"]').count() !== 1) back.push(`${href} did not return to the index`);
    }
    check('/dv the system, Library and Bookstore pages lead back to the index', back.length === 0, back.join(' | '));

    await page.goto(`${BASE}/en/admin/settings`, { waitUntil: 'networkidle' });
    check('/en the index reads left to right, nothing sideways', await page.evaluate(() => document.documentElement.dir) === 'ltr' && await page.locator('[data-testid^="settings-card-"]').count() === hrefs.length && await sideways(page) <= 1);
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
