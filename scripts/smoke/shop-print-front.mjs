/**
 * Can a customer find printing from the Bookstore? On a phone, in Dhivehi.
 * (STATUS §5us — the owner could not see name cards in the Bookstore.)
 *
 * At 390 × 844 under /dv, with the print desk off, the Bookstore shows no
 * way to printing. The walk switches the desk on and puts Cards on sale:
 * the Bookstore's link row has the print chip, its home a *Printed for
 * you* section with name tags and cards (not stickers, not on sale), and
 * the cards card opens the cards page. Nothing scrolls sideways. The desk
 * is put back. Local server only.
 *
 *   node scripts/smoke/shop-print-front.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const SHOTS = process.env.SMOKE_SHOTS ?? null;
if (!LOCAL) {
    console.log('shop-print-front switches the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const was = JSON.parse(tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'cards' => DB::table('products')->where('slug', 'print-cards')->value('status')]);`));
const desk = (on, cards) => tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '${on ? '1' : '0'}', 'updated_at' => now(), 'created_at' => now()]); DB::table('products')->where('slug', 'print-cards')->update(['status' => '${cards}']); Cache::flush(); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
try {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

    desk(false, 'draft');
    await page.goto(`${BASE}/dv/shop`, { waitUntil: 'networkidle' });
    check('/dv with the print desk off, the Bookstore shows no way to printing', await page.locator('[data-testid="shop-link-print"], [data-testid="shop-print"]').count() === 0);

    desk(true, 'active');
    await page.goto(`${BASE}/dv/shop`, { waitUntil: 'networkidle' });
    check('/dv with it on, the Bookstore\'s link row has the print chip', await page.locator('[data-testid="shop-link-print"]').count() === 1);
    const section = page.locator('[data-testid="shop-print"]');
    check('/dv its home has a Printed for you section: name tags and cards, not stickers', await section.count() === 1
        && await section.locator('[data-testid="shop-print-name_tags"]').count() === 1
        && await section.locator('[data-testid="shop-print-cards"]').count() === 1
        && await section.locator('[data-testid="shop-print-stickers"]').count() === 0);
    check('/dv nothing scrolls sideways at 390 px', await sideways(page) <= 1, `${await sideways(page)} px`);
    if (SHOTS) { await section.scrollIntoViewIfNeeded(); await page.screenshot({ path: `${SHOTS}/shop-print-front-dv.png` }); }
    await Promise.all([page.waitForURL(/\/shop\/print\/cards/), section.locator('[data-testid="shop-print-cards"]').click()]);
    await page.waitForLoadState('networkidle');
    check('the cards card opens the cards page, in Dhivehi', /\/dv\/shop\/print\/cards/.test(page.url()) && await page.evaluate(() => document.documentElement.dir) === 'rtl', page.url().replace(BASE, ''));
    await page.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
    check('/en the chip and the section are there, left to right, nothing sideways', await page.locator('[data-testid="shop-link-print"]').count() === 1 && await page.locator('[data-testid="shop-print"]').count() === 1 && await page.evaluate(() => document.documentElement.dir) === 'ltr' && await sideways(page) <= 1);
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
    tinker(`${was.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${was.enabled}']);`} DB::table('products')->where('slug', 'print-cards')->update(['status' => '${was.cards ?? 'draft'}']); Cache::flush(); echo 'ok';`);
}
for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
