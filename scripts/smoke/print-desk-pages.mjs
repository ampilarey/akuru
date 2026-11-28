/**
 * Is the print desk four short pages with one row of tabs? On a phone, in
 * Dhivehi. (Admin audit 2026-11-27, ST3, STATUS §5uv.)
 *
 * At 390 × 844 under /dv the super admin opens /admin/bookshop/print: the
 * desk's Settings alone, under seven tabs. Papers & finishing, Layouts and
 * Products & shops each open their own sections and none of another's; the
 * queue, the school quotes and the templates carry the same tabs. Every
 * page reads right to left, scrolls nothing sideways and stacks its tables.
 * Under /en the same, left to right.
 *
 *   node scripts/smoke/print-desk-pages.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
// Each tab: a marker its page must carry, and one of another page's it must not.
const TABS = {
    queue: { own: ['queue-toggle-all'], not: 'print-papers' },
    quotes: { own: [], not: 'print-papers' },
    templates: { own: ['templates-new'], not: 'print-papers' },
    papers: { own: ['print-papers', 'print-finishing'], not: 'print-save-settings' },
    layouts: { own: ['print-layout-form', 'print-subject-set-form'], not: 'print-papers' },
    products: { own: ['print-products', 'print-shops'], not: 'print-papers' },
    settings: { own: ['print-save-settings'], not: 'print-papers' },
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

    await page.goto(`${BASE}/dv/admin/bookshop/print`, { waitUntil: 'networkidle' });
    const tabs = await page.locator('[data-testid="print-desk-tabs"] a').evaluateAll((as) => as.map((a) => [a.dataset.testid.replace('print-tab-', ''), a.getAttribute('aria-current'), Math.round(a.getBoundingClientRect().height), a.textContent.trim()]));
    check('/dv the desk has seven tabs, Settings the one filled in, each thumb-sized and named', tabs.map((t) => t[0]).join(',') === Object.keys(TABS).join(',') && tabs[6][1] === 'page' && tabs.every((t) => t[2] >= 32 && t[3] !== ''), JSON.stringify(tabs));
    check('/dv the desk\'s own page is its settings alone', await page.locator('[data-phone-main][data-testid="print-desk-page-settings"] [data-testid="print-save-settings"]').count() === 1 && await page.locator('[data-testid="print-papers"], [data-testid="print-layout-form"], [data-testid="print-products"]').count() === 0);

    const faults = [];
    for (const [tab, want] of Object.entries(TABS)) {
        await page.goto(`${BASE}/dv/admin/bookshop/print`, { waitUntil: 'networkidle' });
        await Promise.all([page.waitForLoadState('networkidle'), page.locator(`[data-testid="print-tab-${tab}"]`).click()]);
        await page.waitForFunction((t) => document.querySelector('[data-testid="print-desk-tabs"] [aria-current="page"]')?.dataset.testid === `print-tab-${t}`, tab, { timeout: 15000 }).catch(() => faults.push(`${tab} is not the tab filled in`));
        const seen = await page.evaluate(({ own, not }) => ({
            missing: own.filter((id) => !document.querySelector(`[data-testid="${id}"]`)),
            foreign: Boolean(document.querySelector(`[data-testid="${not}"]`)),
            dir: document.documentElement.dir,
            sideways: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            unstacked: [...document.querySelectorAll('main table')].filter((t) => !t.classList.contains('table-stack') && !t.hasAttribute('data-phone-scroll')).length,
        }), want);
        if (seen.missing.length) faults.push(`${tab} lacks ${seen.missing.join(', ')}`);
        if (seen.foreign) faults.push(`${tab} carries ${want.not}`);
        if (seen.dir !== 'rtl') faults.push(`${tab} reads ${seen.dir}`);
        if (seen.sideways > 1) faults.push(`${tab} scrolls ${seen.sideways} px sideways`);
        if (seen.unstacked) faults.push(`${tab} has ${seen.unstacked} table(s) not stacked`);
        if (SHOTS) await page.screenshot({ path: `${SHOTS}/st3-print-${tab}-dv.png` });
    }
    check('/dv each tab opens its own page with the same tabs: its sections, none of another\'s, right to left, nothing sideways', faults.length === 0, faults.join(' | '));

    await page.goto(`${BASE}/en/admin/bookshop/print/desk/papers`, { waitUntil: 'networkidle' });
    check('/en a page opens by its own address, left to right, nothing sideways', await page.evaluate(() => document.documentElement.dir) === 'ltr' && await page.locator('[data-testid="print-desk-page-papers"]').count() === 1 && await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1);
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
