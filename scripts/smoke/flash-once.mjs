/**
 * Does each Bookstore page say its thanks once? (slice FL1, STATUS §5rg.)
 *
 * The public layout says a page's flash, so a page that printed it too said
 * it twice. A planted learner signs in under /dv and:
 *   - adds the smoke seed's tracing book to the cart: the cart thanks once,
 *     in Dhivehi, in the layout's flash;
 *   - saves it to the wishlist from its page, and takes it off again: each
 *     said once on the product's page.
 *
 * The planted learner, their cart and their wishlist are removed first and
 * last. Local server only.
 *
 *   node scripts/smoke/flash-once.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const LEARNER = 'smoke-fl1@akuru.test';
const PASSWORD = 'smoke-fl1-pass';
const PRODUCT = 'smoke-arabic-letters-tracing-book';

if (!LOCAL) {
    console.log('flash-once plants a learner with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();
const path = (page) => new URL(page.url()).pathname;

const M = '\\App\\Domains\\Bookshop\\Models';
const cleanUp = () => tinker(`$user = DB::table('users')->where('email', '${LEARNER}')->value('id') ?? 0; $carts = ${M}\\Cart::query()->where('user_id', $user)->pluck('id'); ${M}\\CartItem::query()->whereIn('cart_id', $carts)->delete(); ${M}\\Cart::query()->whereIn('id', $carts)->delete(); ${M}\\WishlistItem::query()->where('user_id', $user)->delete(); DB::table('users')->where('id', $user)->delete(); echo 'ok';`);

cleanUp();
tinker(`\\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'SMOKE FL1', 'email' => '${LEARNER}', 'password' => Hash::make('${PASSWORD}'), 'force_password_change' => false]); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

async function submit(locator) {
    await Promise.all([page.waitForLoadState('networkidle'), locator.click()]);
    await page.waitForLoadState('networkidle');
}
const times = async (sentence) => flat(await page.locator('body').textContent()).split(flat(sentence)).length - 1;
const flash = async () => flat(await page.locator('[data-testid="flash-success"]').first().textContent({ timeout: 3000 }).catch(() => ''));

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(LEARNER);
await page.locator('input[name="password"]').fill(PASSWORD);
await submit(page.locator('form[action$="/login"] button[type="submit"]').first());
check('the planted learner signs in', !path(page).endsWith('/login'), path(page));

// ------------------------------------------------ the cart
await page.goto(`${BASE}/dv/shop/products/${PRODUCT}`, { waitUntil: 'networkidle' });
await submit(page.locator('[data-testid="add-to-cart"]').first());
const added = await flash();
check('the cart thanks the learner in Dhivehi, in the layout\'s flash', /[ހ-޿]/.test(added) && path(page).endsWith('/shop/cart'), `${added.slice(0, 100)} on ${path(page)}`);
check('the cart says it once', added !== '' && await times(added) === 1, `${await times(added)}×`);

// ------------------------------------------------ the wishlist, from the product's page
await page.goto(`${BASE}/dv/shop/products/${PRODUCT}`, { waitUntil: 'networkidle' });
await submit(page.locator('[data-testid="wishlist-toggle"]').first());
const saved = say('shop.wishlist_added_flash');
check('saved to the wishlist: said once, in Dhivehi', await times(saved) === 1 && await flash() === flat(saved), `${await times(saved)}× on ${path(page)}`);
await submit(page.locator('[data-testid="wishlist-toggle"]').first());
const removed = say('shop.wishlist_removed_flash');
check('taken off the wishlist: said once, in Dhivehi', await times(removed) === 1 && await flash() === flat(removed), `${await times(removed)}× on ${path(page)}`);

await browser.close();
cleanUp();

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
