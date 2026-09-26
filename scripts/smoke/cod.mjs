/**
 * Can a customer pay a shop in cash on delivery, and does the money come
 * out right? (BOOKSHOP_PLAN slice B9b: cash on delivery, decision 7.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` leaves Fitrah not taking cash, the
 * office's switch at its default (on), and the student with no orders.
 *
 * Fitrah's owner:
 *   1. turns on cash on delivery, up to MVR 1000;
 * The student:
 *   2. buys the Wooden Alphabet Puzzle (MVR 240 + MVR 30 courier) paying
 *      cash on delivery — placed at once, told to have MVR 270.00 ready;
 * Fitrah's owner:
 *   3. finds it as cash on delivery, prepares and dispatches it, cannot mark
 *      it delivered without ticking that the cash was received, then does;
 *   4. sees the earning with MVR 270.00 cash taken and MVR −24.00 net (the
 *      10% commission owed to Akuru);
 * The office:
 *   5. turns cash on delivery off, and the checkout no longer offers it.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/cod.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PUZZLE = 'smoke-wooden-alphabet-puzzle';

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
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 }, acceptDownloads: true });
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
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const count = (page, selector) => page.locator(selector).count();
const inner = async (page, selector) => (await page.locator(selector).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
const csvOf = async (page, href) => {
    const response = await page.request.get(href.startsWith('http') ? href : `${BASE}${href}`);
    return { status: response.status(), text: await response.text() };
};

const submit = async (page, selector) => {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click(selector)]);
    await page.waitForTimeout(200);
};

// ------------------------------------------------------------ 1. the shop takes cash

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="cod-enabled"]');
}
await vendor.check('[data-testid="cod-enabled"]');
await vendor.fill('[data-testid="cod-max"]', '1000');
await vendor.click('[data-testid="save-settings"]');
await settle(vendor, '[data-testid="flash-success"]');
await vendor.reload({ waitUntil: 'networkidle' });
check('Fitrah takes cash on delivery, up to MVR 1000', (await vendor.locator('[data-testid="cod-enabled"]').isChecked()) && (await vendor.locator('[data-testid="cod-max"]').inputValue()) === '1000.00');

// ------------------------------------------------------------ 2. the customer pays cash

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/shop/products/${PUZZLE}`, { waitUntil: 'networkidle' });
await student.fill('[data-testid="quantity"]', '1');
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
check('the checkout offers cash on delivery', (await count(student, '[data-testid="pay-cash_on_delivery"]')) === 1);
const courier = student.locator('input[name="delivery[fitrah]"][data-delivery-kind="courier_male"]');
if ((await courier.count()) > 0) {
    await courier.first().check();
}
if ((await count(student, '[data-testid="recipient-name"]')) > 0 && (await student.locator('[data-testid="recipient-name"]').isVisible())) {
    await student.fill('[data-testid="recipient-name"]', 'Smoke Customer');
    await student.fill('[data-testid="phone"]', '7700000');
    await student.fill('[data-testid="atoll"]', 'K');
    await student.fill('[data-testid="island"]', 'Malé');
    await student.fill('[data-testid="street"]', 'M. Smoke Villa');
}
await student.check('[data-testid="pay-cash_on_delivery"]');
await submit(student, '[data-testid="place-order"]');
check('placed at once, the customer told to have MVR 270.00 ready', (await student.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'cash_on_delivery'
    && (await inner(student, '[data-testid="cod-placed"]')).includes('270.00'), `${student.url().replace(BASE, '')} ${(await inner(student, 'main')).slice(0, 160)}`);
const orderNumber = await student.locator('[data-testid="checkout-orders"] [data-order]').first().getAttribute('data-order').catch(() => '');

// ------------------------------------------------------------ 3. the shop hands it over with the cash

await vendor.goto(`${BASE}/en/vendor/orders?status=cash_due`, { waitUntil: 'networkidle' });
check('Fitrah finds it under cash on delivery', (await inner(vendor, 'main')).includes(orderNumber) && (await inner(vendor, 'main')).includes('Cash on delivery'), orderNumber);
// One order in the search opens by itself, whatever its status becomes.
await vendor.goto(`${BASE}/en/vendor/orders?q=${orderNumber}`, { waitUntil: 'networkidle' });
await settle(vendor, `[data-testid="steps-${orderNumber}"]`);
await vendor.locator(`[data-testid="steps-${orderNumber}"] [data-testid="step-processing"]`).click();
await settle(vendor, '[data-testid="flash-success"]');
await vendor.locator(`[data-testid="steps-${orderNumber}"] [data-testid="carrier"]`).fill('SMOKE-Bike');
await vendor.locator(`[data-testid="steps-${orderNumber}"] [data-testid="step-dispatched"]`).click();
await settle(vendor, `[data-testid="steps-${orderNumber}"] [data-testid="cash-received"]`);
const deliveredButton = vendor.locator(`[data-testid="steps-${orderNumber}"] [data-testid="step-delivered"]`);
check('Delivered waits for the cash to be ticked', await deliveredButton.isDisabled() && (await inner(vendor, `[data-testid="steps-${orderNumber}"]`)).includes('270.00'));
await vendor.locator(`[data-testid="steps-${orderNumber}"] [data-testid="cash-received"]`).check();
await deliveredButton.click();
await settle(vendor, '[data-testid="flash-success"]');
await vendor.goto(`${BASE}/en/vendor/orders?status=delivered`, { waitUntil: 'networkidle' });
check('delivered, and paid', (await inner(vendor, 'main')).includes(orderNumber));
await student.goto(`${BASE}/en/my-orders/${orderNumber}`, { waitUntil: 'networkidle' });
check('the customer sees it delivered', (await inner(student, '[data-testid="order-status"]')).includes('Delivered'), await inner(student, '[data-testid="order-status"]'));

// ------------------------------------------------------------ 4. the money

await vendor.goto(`${BASE}/en/vendor/money`, { waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="money-heading"]');
const earningRow = vendor.locator('tr').filter({ hasText: orderNumber }).first();
const earningText = (await earningRow.innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the earning shows MVR 270.00 cash taken and MVR −24.00 owed to Akuru', earningText.includes('270.00') && earningText.includes('-24.00'), earningText);

// ------------------------------------------------------------ 5. the office turns it off

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-cod"]');
await office.click('[data-testid="toggle-cod"]');
await settle(office, '[data-testid="flash-success"]');
check('the office turns cash on delivery off', (await inner(office, '[data-testid="cod-state"]')).includes('off'));
await student.goto(`${BASE}/en/shop/products/${PUZZLE}`, { waitUntil: 'networkidle' });
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
check('and the checkout no longer offers it', (await count(student, '[data-testid="pay-cash_on_delivery"]')) === 0 && (await count(student, '[data-testid="place-order"]')) === 1);

await finish();
