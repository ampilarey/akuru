/**
 * Can a reader buy a Library bundle, and can the office make one? On a
 * phone, in Dhivehi. (LIBRARY_PLAN §19, BACKLOG B4, B4d, STATUS §5un.)
 *
 * The walk makes a bundle of two published paid works at less than they cost
 * apart, and a reader with wallet credit. At 390 × 844 under /dv the offers
 * page lists the bundle; its page shows the old total struck and what it
 * saves; the reader pays from the wallet and the page says every work is
 * theirs. The office's /dv/admin/library/bundles lists it with one sale, its
 * table stacked. Nothing scrolls sideways. The bundle is removed after.
 * Local server only.
 *
 *   node scripts/smoke/library-bundles.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const READER = 'smoke-b4d@example.mv';
const SLUG = 'smoke-b4d-bundle';

if (!LOCAL) {
    console.log('library-bundles makes its bundle with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();

// A reader of the walk's own, cleared of what an earlier run bought, with credit; and the bundle.
const restore = () => tinker(`$u = DB::table('users')->where('email', '${READER}')->value('id'); if ($u) { $p = DB::table('library_purchases')->where('user_id', $u)->pluck('id'); DB::table('writer_earnings')->whereIn('library_purchase_id', $p)->delete(); DB::table('library_access_grants')->where('user_id', $u)->delete(); DB::table('library_purchases')->where('user_id', $u)->delete(); } DB::table('library_bundles')->where('slug', 'like', '${SLUG}%')->delete(); echo 'ok';`);
restore();
const readerId = tinker(`$u = App\\Domains\\Identity\\Models\\User::firstOrCreate(['email' => '${READER}'], ['name' => 'Hawwa Bundle', 'password' => Hash::make('${PASSWORD}'), 'email_verified_at' => now()]); app(App\\Domains\\Commerce\\Actions\\CreditWalletAction::class)->execute($u->id, 500, 'admin', null, 'library-bundles walk top-up'); echo $u->id;`);
const made = tinker(`$ids = DB::table('library_items')->where('status', 'published')->where('access_type', 'paid')->where('price', '>', 0)->orderBy('id')->limit(2)->pluck('id')->all(); $sum = DB::table('library_items')->whereIn('id', $ids)->sum('price'); $b = app(App\\Domains\\Library\\Actions\\SaveLibraryBundleAction::class)->execute(['title' => 'Smoke B4D bundle', 'price' => round($sum * 0.75, 2), 'item_ids' => $ids, 'is_active' => true], null, 1); $b->forceFill(['slug' => '${SLUG}'])->save(); echo $b->price.'|'.$sum;`);
const [price, listTotal] = made.split('|').map(Number);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const signIn = async (who) => {
    const page = await (await browser.newContext(phone)).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });

    return page;
};

try {
    check('the walk made a bundle below its works\' total', price > 0 && price < listTotal && Number(readerId) > 0, made);
    const reader = await signIn(READER);
    await reader.goto(`${BASE}/dv/library/promotions`, { waitUntil: 'networkidle' });
    check('/dv the offers page lists the bundle', await reader.locator(`[data-testid="offer-bundles"] [data-bundle="${SLUG}"]`).count() === 1);
    await reader.locator(`[data-bundle="${SLUG}"]`).first().click();
    await reader.waitForLoadState('networkidle');
    check('/dv the bundle\'s page reads right to left, its two works listed', await reader.evaluate(() => document.documentElement.dir) === 'rtl' && await reader.locator('[data-testid="bundle-items"] [data-item]').count() === 2);
    check('it shows the total struck and what it saves', (await reader.locator('[data-testid="bundle-price"]').innerText()).includes(listTotal.toFixed(2)) && await reader.locator('[data-testid="bundle-saving"]').count() === 1);
    check('/dv the buy box is the first thing on the phone, nothing sideways', await reader.locator('[data-phone-main][data-testid="bundle-buy"]').count() === 1 && await sideways(reader) <= 1, `${await sideways(reader)} px`);
    await Promise.all([reader.waitForNavigation({ waitUntil: 'networkidle' }), reader.locator('[data-testid="bundle-buy-wallet"]').click()]);
    check('paid from the wallet: every work in it is the reader\'s', await reader.locator('[data-testid="bundle-owned"]').count() === 1 && await reader.locator('[data-testid="bundle-items"] [data-owned="yes"]').count() === 2);
    const state = tinker(`echo DB::table('library_purchases')->where('user_id', ${readerId})->where('status', 'paid')->whereNotNull('library_bundle_id')->sum('amount') . '|' . DB::table('library_access_grants')->where('user_id', ${readerId})->count();`);
    check('two purchases making the bundle\'s price, two works granted', Number(state.split('|')[0]).toFixed(2) === price.toFixed(2) && state.endsWith('|2'), state);
    if (SHOTS) await reader.screenshot({ path: `${SHOTS}/b4d-bundle-dv.png`, fullPage: true });

    const office = await signIn(STAFF);
    await office.goto(`${BASE}/dv/admin/library/bundles`, { waitUntil: 'networkidle' });
    const row = office.locator(`[data-testid="bundle-${SLUG}"]`);
    check('/dv the office lists the bundle, on sale, with its one sale', await row.count() === 1 && (await row.getAttribute('data-on-sale')) === 'yes' && (await row.innerText()).includes('1'));
    check('/dv the bundles come before the form, and the table stacks', await office.locator('[data-phone-main][data-testid="bundles"] table.table-stack').count() === 1 && await office.locator('[data-testid="bundle-form"]').count() === 1);
    check('/dv the office\'s page does not scroll sideways at 390 px', await sideways(office) <= 1, `${await sideways(office)} px`);
    await office.goto(`${BASE}/en/admin/library/bundles`, { waitUntil: 'networkidle' });
    check('/en the office\'s page reads left to right, nothing sideways', await office.evaluate(() => document.documentElement.dir) === 'ltr' && await sideways(office) <= 1);
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
    restore();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
