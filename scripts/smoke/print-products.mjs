/**
 * Can the office put a second print product on sale, and does a customer
 * find it? On a phone, in Dhivehi and English. (PRINT_PRODUCTS_PLAN PP2,
 * STATUS §5tj.)
 *
 * With the print desk on (set with tinker, as print-design does), at
 * 390 × 844 under /dv the system admin finds the four print products on the
 * desk, is refused *Stickers* (no layout of its own) in Dhivehi, adds a
 * cards layout and puts *Cards* on sale. A customer then sees Cards on
 * `/shop/print` and opens its door; under /en the same. The office takes
 * Cards off sale and the customer's door is gone. Nothing scrolls sideways.
 * The desk and the planted rows are put back. Local server only.
 *
 *   node scripts/smoke/print-products.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_CUSTOMER, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const CUSTOMER = process.env.SMOKE_CUSTOMER ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const MARK = 'SMOKE PP2';

if (!LOCAL) {
    console.log('print-products changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale, vars = '[]') => tinker(`echo __('${key}', ${vars}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo 'ok';`);
const saved = tinker(`echo DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value') ?? 'none';`);
const restore = () => tinker(`DB::table('print_layouts')->where('name', 'like', '${MARK}%')->delete(); DB::table('products')->whereIn('slug', ['print-cards', 'print-stickers', 'print-photos'])->update(['status' => 'draft']); ${saved === 'none' ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${saved}']);`} Cache::flush(); echo 'ok';`);
restore();
tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); Cache::flush(); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const office = await (await browser.newContext(phone)).newPage();
const customer = await (await browser.newContext(phone)).newPage();
for (const page of [office, customer]) {
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
}
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const shot = async (page, name) => { if (SHOTS) await page.screenshot({ path: `${SHOTS}/${name}.png` }); };
const signIn = async (page, who) => {
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    return !new URL(page.url()).pathname.endsWith('/login');
};

check('the system admin signs in', await signIn(office, STAFF));
check('the customer signs in', await signIn(customer, CUSTOMER));

await office.goto(`${BASE}/dv/admin/bookshop/print`, { waitUntil: 'networkidle' });
check('/dv the desk lists the four print products', await office.locator('[data-testid^="print-product-"][data-on-sale]').count() === 4);
await Promise.all([office.waitForLoadState('networkidle'), office.locator('[data-testid="print-product-stickers-toggle"]').click()]);
await office.waitForTimeout(400);
const refusal = say('print.error_product_no_layout', 'dv', `['product' => 'ސްޓިކަރު']`);
check('/dv Stickers is refused without a layout of its own, in Dhivehi', flat(await office.locator('body').textContent()).includes(refusal), refusal);

const form = office.locator('[data-testid="print-layout-form"]');
await office.locator('[data-testid="print-layout-name"]').fill(`${MARK} Business cards`);
await office.locator('[data-testid="print-layout-product"]').selectOption('cards');
for (const [n, v] of [[0, 2], [1, 5], [2, 85], [3, 55], [4, 10], [5, 15]]) await form.locator('input[type="number"]').nth(n).fill(String(v));
await Promise.all([office.waitForLoadState('networkidle'), form.locator('button[type="submit"]').first().click()]);
await office.waitForTimeout(400);
await Promise.all([office.waitForLoadState('networkidle'), office.locator('[data-testid="print-product-cards-toggle"]').click()]);
await office.waitForTimeout(400);
check('/dv with a cards layout, Cards goes on sale', await office.locator('[data-testid="print-product-cards"]').getAttribute('data-on-sale') === 'yes');
check('/dv the desk does not scroll sideways at 390 px', await sideways(office) <= 1, `${await sideways(office)} px`);
await office.locator('[data-testid="print-products"]').scrollIntoViewIfNeeded();
await shot(office, 'pp2-desk-dv');

await customer.goto(`${BASE}/dv/shop/print`, { waitUntil: 'networkidle' });
const cardsCard = customer.locator('[data-testid="print-index-cards"]');
check('/dv the customer finds Cards on the print page, in Dhivehi', await cardsCard.count() === 1 && flat(await cardsCard.locator('h2').textContent()) === 'ކާޑު');
check('/dv and not Stickers, which is not on sale', await customer.locator('[data-testid="print-index-stickers"]').count() === 0);
const main = await customer.evaluate(() => document.querySelector('[data-phone-main]')?.getBoundingClientRect().top ?? 9999);
check('/dv the products start on the first phone screen', main < 844, `${Math.round(main)} px`);
check('/dv the print page does not scroll sideways at 390 px', await sideways(customer) <= 1, `${await sideways(customer)} px`);
await shot(customer, 'pp2-index-dv');
await Promise.all([customer.waitForURL(/\/shop\/print\/cards$/), cardsCard.locator('a').click()]);
check('/dv the Cards door opens under its own name', (await customer.title()).includes('ކާޑު') || flat(await customer.locator('h1').first().textContent()).includes('ކާޑު'), await customer.title());

await customer.goto(`${BASE}/en/shop/print`, { waitUntil: 'networkidle' });
check('/en Cards is listed in English', flat(await customer.locator('[data-testid="print-index-cards"] h2').textContent()) === 'Cards');
await shot(customer, 'pp2-index-en');

await Promise.all([office.waitForLoadState('networkidle'), office.locator('[data-testid="print-product-cards-toggle"]').click()]);
await office.waitForTimeout(400);
const gone = await customer.goto(`${BASE}/en/shop/print/cards`, { waitUntil: 'networkidle' });
check('taken off sale, the Cards door is gone for the customer', gone.status() === 404, String(gone.status()));

await browser.close();
restore();

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
