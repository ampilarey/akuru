/**
 * Can a customer pre-order a book that is not out yet, and hear when it is?
 * (COMMERCE_PARITY_PLAN P8d.)
 *
 *   1. The shop's prayer mat gets a release date ten days out (set here
 *      through tinker; the shop's own product form carries the same field,
 *      which the walk checks is there) and nothing on the shelf.
 *   2. The product page says *Pre-order — ships from …* and still offers the
 *      cart; the cart says the same; the student buys it, paid in full.
 *   3. The order says it is a pre-order and when it ships; the shop's order
 *      list says not to send it before then.
 *   4. On the release date (brought forward through tinker) the daily
 *      command tells the student once.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/preorder.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_VENDOR, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM. Local only (tinker).
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const MAT = 'smoke-kids-prayer-mat';
const tinker = (php) => execSync(`cd ${process.cwd()} && php artisan tinker --execute="${php}"`, { encoding: 'utf8' }).trim().split('\n').pop();
const release = new Date(Date.now() + 5 * 3600000 + 10 * 86400000).toISOString().slice(0, 10);

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

async function signIn(email) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${email}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${email}: HTTP ${response.status()} ${response.url()}`);
        }
    });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');

    return page;
}

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
// Inertia posts are XHR: wait for what the step expects, not for the network.
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const money = (value) => Number(String(value ?? '').replace(/[^0-9.]/g, ''));
// A Blade form post is a real navigation. "networkidle" straight after the
// click can resolve before it starts, and the walk's next goto then cuts the
// post off, so wait for the navigation itself.
const submit = async (page, selector) => {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click(selector)]);
};


// ------------------------------------------------------------ 1. a pre-order

tinker(`\\Illuminate\\Support\\Facades\\DB::table('products')->where('slug','${MAT}')->update(['preorder_release_on' => '${release}', 'stock' => 0]); echo 'ok';`);
const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
// A fresh database: the owner has not met the Vendor Agreement yet (vendor.mjs walks that).
if (await vendor.locator('[data-testid="agreement-form"]').count()) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.locator('[data-testid="agreement-form"] button[type=submit]').click();
    await settle(vendor, '[data-testid="product-list"]');
}
await vendor.click(`[data-testid="edit-${MAT}"]`).catch(() => {});
await settle(vendor, '[data-testid="product-editor"] [data-testid="product-preorder"]');
check('the shop\'s product form carries the pre-order date', (await vendor.locator('[data-testid="product-editor"] [data-testid="product-preorder"]').inputValue().catch(() => '')) === release);

// ------------------------------------------------------------ 2. the customer pre-orders

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/shop/products/${MAT}`, { waitUntil: 'networkidle' });
const stock = (await student.locator('[data-stock="preorder"]').first().innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the product says pre-order and when it ships, and still offers the cart', stock.includes(release) && (await student.locator('[data-testid="add-to-cart"]').count()) === 1, stock);
await student.fill('[data-testid="quantity"]', '1');
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/cart`, { waitUntil: 'networkidle' });
check('the cart says it ships from that date', (await student.locator('[data-testid="cart-preorder"]').innerText().catch(() => '')).includes(release));
await student.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
if ((await student.locator('[data-testid="saved-address"]').count()) > 0) {
    await student.locator('[data-testid="saved-address"]').first().check();
} else {
    await student.fill('[data-testid="recipient-name"]', 'Smoke Customer');
    await student.fill('[data-testid="phone"]', '7700000');
    await student.fill('[data-testid="atoll"]', 'K');
    await student.fill('[data-testid="island"]', 'Malé');
    await student.fill('[data-testid="street"]', 'M. Smoke Villa');
}
await student.locator('[data-testid="delivery-fitrah"] input[data-delivery-kind="collect_vendor"]').first().check();
await student.check('[data-testid="pay-wallet"]');
await submit(student, '[data-testid="place-order"]');
const number = (await student.locator('[data-testid="checkout-orders"] [data-order]').first().getAttribute('data-order').catch(() => '')) ?? '';
check('the student pre-orders it, paid in full', number !== '' && (await student.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid', number);

// ------------------------------------------------------------ 3. the order says so, and the shop

await student.goto(`${BASE}/en/my-orders/${number}`, { waitUntil: 'networkidle' });
check('the order says it is a pre-order and when it ships', (await student.locator('[data-testid="order-preorder"]').innerText().catch(() => '')).includes(release));
await vendor.goto(`${BASE}/en/vendor/orders?q=${number}`, { waitUntil: 'networkidle' });
const card = vendor.locator(`[data-testid="vendor-order-${number}"]`);
if ((await card.locator('button[aria-expanded]').first().getAttribute('aria-expanded').catch(() => 'false')) !== 'true') {
    await card.locator('button[aria-expanded]').first().click().catch(() => {});
}
await settle(vendor, `[data-testid="vendor-preorder-${number}"]`);
check('the shop\'s order says not to send it before then', (await vendor.locator(`[data-testid="vendor-preorder-${number}"]`).innerText().catch(() => '')).includes(release));

// ------------------------------------------------------------ 4. release day

tinker(`\\Illuminate\\Support\\Facades\\DB::table('orders')->where('number','${number}')->update(['ships_from' => now('Indian/Maldives')->toDateString()]); echo 'ok';`);
execSync(`cd ${process.cwd()} && php artisan bookshop:release-preorders && php artisan bookshop:release-preorders`, { encoding: 'utf8' });
await student.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
const notices = (await student.innerText('body')).replace(/\s+/g, ' ');
const told = notices.split(`Your pre-order ${number} is released`).length - 1;
check('on the release date the student is told, once', told === 1, `${told} notices`);

await finish();
