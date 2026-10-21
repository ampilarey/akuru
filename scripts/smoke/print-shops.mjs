/**
 * Can the office approve a shop to print, and stop it? On a phone, in
 * Dhivehi. (PRINT_PRODUCTS_PLAN PP7, PD10, STATUS §5uh–§5uj.)
 *
 * At 390 × 844 under /dv the office opens the print desk: *Shops that print*
 * lists Fitrah, not printing. Approve to print: Fitrah prints, and has its own
 * copy of each print product on sale. Stop printing: they come off sale.
 * Nothing scrolls sideways. Everything is put back. Local server only.
 *
 *   node scripts/smoke/print-shops.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
if (!LOCAL) {
    console.log('print-shops reads the database with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const was = tinker(`echo (int) DB::table('vendors')->where('slug', 'fitrah')->value('print_enabled');`);
const onSale = () => Number(tinker(`echo DB::table('products')->where('slug', 'like', '%--fitrah')->where('status', 'active')->count();`));

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
try {
    tinker(`DB::table('vendors')->where('slug', 'fitrah')->update(['print_enabled' => false]); DB::table('products')->where('slug', 'like', '%--fitrah')->update(['status' => 'draft']); echo 'ok';`);
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true })).newPage();
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="identifier"]', STAFF);
    await page.fill('input[name="password"]', PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'));
    await page.goto(`${BASE}/dv/admin/bookshop/print`, { waitUntil: 'networkidle' });
    const row = page.locator('[data-testid="print-shop-fitrah"]');
    check('/dv the desk lists Fitrah under Shops that print, not printing', (await row.getAttribute('data-print')) === 'no');
    check('/dv the desk does not scroll sideways at 390 px', await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1);
    await row.locator('[data-testid="print-shop-fitrah-toggle"]').click();
    await page.waitForFunction(() => document.querySelector('[data-testid="print-shop-fitrah"]')?.dataset.print === 'yes', null, { timeout: 10000 }).catch(() => {});
    check('approved: Fitrah prints', (await row.getAttribute('data-print')) === 'yes');
    check('and its own four print products are on sale', onSale() === 4, String(onSale()));
    await row.locator('[data-testid="print-shop-fitrah-toggle"]').click();
    await page.waitForFunction(() => document.querySelector('[data-testid="print-shop-fitrah"]')?.dataset.print === 'no', null, { timeout: 10000 }).catch(() => {});
    check('stopped: Fitrah no longer prints, its products off sale', (await row.getAttribute('data-print')) === 'no' && onSale() === 0);
} finally {
    await browser.close();
    tinker(`DB::table('vendors')->where('slug', 'fitrah')->update(['print_enabled' => ${was === '1' ? 'true' : 'false'}]); echo 'ok';`);
}
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
