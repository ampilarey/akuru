/**
 * Can a printing shop keep its own print desk? On a phone, in Dhivehi.
 * (PRINT_PRODUCTS_PLAN PP7b, PD10b, PD10e, STATUS §5ui.)
 *
 * At 390 × 844 under /dv the seeded shop's owner opens /vendor/print before
 * the office approves it: told it does not print yet, no Print desk link
 * among the shop's pages. They add a paper at their own price; it is
 * Fitrah's, not Akuru's. The office approves the shop: the link shows on the
 * shop's pages and its home, the notice goes, and the queue is the shop's
 * own (empty). In English too. Nothing scrolls sideways; every table stacks. Everything is
 * put back. Local server only.
 *
 *   node scripts/smoke/vendor-print.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const PAPER = 'Smoke PP7b gloss';
if (!LOCAL) {
    console.log('vendor-print reads the database with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const was = tinker(`echo (int) DB::table('vendors')->where('slug', 'fitrah')->value('print_enabled');`);
const restore = () => tinker(`DB::table('print_paper_options')->where('name', '${PAPER}')->delete(); DB::table('vendors')->where('slug', 'fitrah')->update(['print_enabled' => ${was === '1' ? 'true' : 'false'}]); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const seen = (page) => page.evaluate(() => ({
    sideways: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    unstacked: [...document.querySelectorAll('main table')].filter((t) => !t.classList.contains('table-stack')).length,
    dir: document.documentElement.getAttribute('dir'),
}));
try {
    restore();
    tinker(`DB::table('vendors')->where('slug', 'fitrah')->update(['print_enabled' => false]); echo 'ok';`);
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="identifier"]', VENDOR);
    await page.fill('input[name="password"]', PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });
    // The shop's pages ask for the Vendor Agreement first, as vendor-mobile does.
    await page.goto(`${BASE}/dv/vendor`, { waitUntil: 'networkidle' });
    if (await page.locator('[data-testid="accept-agreement"]').count()) {
        await page.check('[data-testid="accept-agreement"]');
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('[data-testid="agreement-form"] button[type=submit]')]);
    }

    await page.goto(`${BASE}/dv/vendor/print`, { waitUntil: 'networkidle' });
    let s = await seen(page);
    check('/dv the shop\'s print desk opens, right to left', s.dir === 'rtl' && await page.locator('[data-testid="vendor-print-heading"]').count() === 1);
    check('not approved: it says so, and its pages offer no Print desk link', await page.locator('[data-testid="vendor-print-not-approved"]').count() === 1 && await page.locator('[data-testid="vendor-subnav"] [data-testid="open-print"]').count() === 0);
    check('its queue comes first on the phone', await page.locator('[data-phone-main][data-testid="vendor-print-queue"]').count() === 1);
    check('/dv nothing scrolls sideways at 390 px, every table stacks', s.sideways <= 1 && s.unstacked === 0, JSON.stringify(s));

    const form = page.locator('[data-testid="print-paper-form"]');
    await form.locator('[data-testid="print-paper-name"]').fill(PAPER);
    await form.locator('[data-testid="print-paper-price-bw"]').fill('1.25');
    await form.locator('[data-testid="print-paper-price-colour"]').fill('2.75');
    await form.locator('[data-testid="print-paper-active"]').check();
    await form.locator('[data-testid="print-paper-save"]').click();
    await page.waitForFunction((name) => document.querySelector('[data-testid="print-papers"]')?.innerText.includes(name), PAPER, { timeout: 10000 }).catch(() => {});
    const owner = tinker(`echo DB::table('print_paper_options')->where('name', '${PAPER}')->value('vendor_id') == DB::table('vendors')->where('slug', 'fitrah')->value('id') ? 'fitrah' : 'other';`);
    check('a paper added at the shop\'s own price is listed, and is Fitrah\'s', (await page.locator('[data-testid="print-papers"]').innerText()).includes(PAPER) && owner === 'fitrah', owner);

    tinker(`app(App\\Domains\\Bookshop\\Actions\\Print\\ApproveVendorPrintAction::class)->execute(App\\Domains\\Bookshop\\Models\\Vendor::where('slug', 'fitrah')->first(), true); echo 'ok';`);
    await page.goto(`${BASE}/dv/vendor/print`, { waitUntil: 'networkidle' });
    check('approved: the notice goes, the Print desk is among the shop\'s pages', await page.locator('[data-testid="vendor-print-not-approved"]').count() === 0 && await page.locator('[data-testid="vendor-subnav"] [data-testid="open-print"][aria-current="page"]').count() === 1);
    check('its queue is its own: nothing ordered from it yet', await page.locator('[data-testid="queue-empty"]').count() === 1);
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/pp7b-vendor-print-dv.png`, fullPage: true });
    await page.goto(`${BASE}/dv/vendor`, { waitUntil: 'networkidle' });
    check('/dv the shop\'s home offers the print desk', await page.locator('[data-testid="shop-sections"] [data-testid="open-print"]').count() === 1);
    s = await seen(page);
    check('/dv the shop\'s home does not scroll sideways at 390 px', s.sideways <= 1, `${s.sideways} px`);
    await page.goto(`${BASE}/en/vendor/print`, { waitUntil: 'networkidle' });
    s = await seen(page);
    check('/en the desk reads left to right and does not scroll sideways at 390 px', s.dir === 'ltr' && s.sideways <= 1 && s.unstacked === 0, JSON.stringify(s));
} finally {
    await browser.close();
    restore();
    tinker(`app(App\\Domains\\Bookshop\\Actions\\Print\\ApproveVendorPrintAction::class)->execute(App\\Domains\\Bookshop\\Models\\Vendor::where('slug', 'fitrah')->first(), ${was === '1' ? 'true' : 'false'}); echo 'ok';`);
}
for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
