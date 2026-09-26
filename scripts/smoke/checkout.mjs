/**
 * Can a customer buy from two shops in one basket, and pay by bank transfer?
 * (BOOKSHOP_PLAN slice B2.)
 *
 * A guest:
 *
 *   1. puts two tracing books in the cart from the product page, sees them on
 *      the cart page with the sign-in prompt and a count on the phone bar;
 *   2. signs in as the student and finds the same cart, adds the other shop's
 *      item, and checks out: an address, a delivery method per shop, the
 *      wallet — lands on the checkout page as **paid** with one order per
 *      shop, and both are on My orders with a receipt;
 *   3. orders again by bank transfer: the account to pay into, uploads a slip,
 *      and the page says the office is looking;
 *
 * then the office confirms the slip and the student's page says paid; then
 * Fitrah's owner sets the shop's delivery methods from the template.
 *
 * `SmokeMarkerSeeder::vendorCycle()` plants the products, clears the last
 * run's orders and tops the student's wallet up. Bank transfer needs
 * `BOOKSHOP_BANK_ACCOUNT_NUMBER` in the host's `.env`.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/checkout.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_VENDOR,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const BOOK = 'smoke-arabic-letters-tracing-book';
const OTHER = 'smoke-other-secret';
const MAT = 'smoke-kids-prayer-mat';
const SLIP = new URL('../../database/seeders/fixtures/vendors/fitrah-logo.jpg', import.meta.url).pathname;

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

async function newPage(label, viewport = { width: 1280, height: 900 }) {
    const context = await browser.newContext({ viewport });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${label}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${label}: HTTP ${response.status()} ${response.url()}`);
        }
    });

    return page;
}

async function signIn(page, email, password = PASSWORD) {
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', password);
    await submit(page, 'button[type=submit]');
    await page.waitForLoadState('networkidle');

    return page;
}

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
// A Blade form post or link is a real navigation. "networkidle" straight
// after the click could resolve before it started, so the next step read the
// old page or cut the post off (an intermittent red, 2026-09-26). Wait for
// the navigation itself.
const submit = async (page, selector) => {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click(selector)]);
};
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};

async function addToCart(page, slug, quantity = 1) {
    await page.goto(`${BASE}/en/shop/products/${slug}`, { waitUntil: 'networkidle' });
    await page.fill('[data-testid="quantity"]', String(quantity));
    await submit(page, '[data-testid="add-to-cart"]');
    await page.waitForLoadState('networkidle');
}

async function fillAddress(page) {
    await page.fill('[data-testid="recipient-name"]', 'Smoke Customer');
    await page.fill('[data-testid="phone"]', '7700000');
    await page.fill('[data-testid="atoll"]', 'K');
    await page.fill('[data-testid="island"]', 'Malé');
    await page.fill('[data-testid="street"]', 'M. Smoke Villa');
}

// ------------------------------------------------------------ 1. a guest's cart

const customer = await newPage('customer');
await addToCart(customer, BOOK, 2);
check('a guest adds two tracing books from the product page and lands on the cart', /\/shop\/cart$/.test(customer.url()) && (await customer.locator(`[data-cart-line="${BOOK}"]`).count()) === 1, customer.url().replace(BASE, ''));
const guestCart = await text(customer);
check('the cart shows the line, its total, and asks the guest to sign in', guestCart.includes('MVR 170.00') && guestCart.includes('Sign in to check out') && (await customer.locator('[data-testid="cart-sign-in"] a').count()) === 2, guestCart.slice(0, 160));

const phone = await newPage('phone', { width: 390, height: 844 });
await phone.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('the phone bar has Cart', (await phone.locator('[data-testid="bar-cart"]').count()) === 1 && (await phone.locator('[data-testid="bar-cart"]').isVisible()));

// ------------------------------------------------------------ 2. signed in, two shops, the wallet

await signIn(customer, STUDENT);
check('the customer signs in', !customer.url().includes('/login'), customer.url());
await customer.goto(`${BASE}/en/shop/cart`, { waitUntil: 'networkidle' });
check('the guest\'s cart followed them in, with the checkout button', (await customer.locator(`[data-cart-line="${BOOK}"] [data-testid="cart-qty"]`).inputValue()) === '2' && (await customer.locator('[data-testid="go-to-checkout"]').count()) === 1);
check('and the phone bar counts what is in it', (await customer.locator('[data-testid="bar-cart-count"]').innerText().catch(() => '')) === '2');

await addToCart(customer, OTHER, 1);
check('an item from the other shop joins as its own group', (await customer.locator('[data-testid="cart-group-smoke-other-shop"]').count()) === 1 && (await customer.locator('[data-testid="cart-group-fitrah"]').count()) === 1);

await submit(customer, '[data-testid="go-to-checkout"]');
await customer.waitForLoadState('networkidle');
check('the checkout opens', /\/shop\/checkout$/.test(customer.url()) && (await customer.locator('[data-testid="checkout-heading"]').count()) === 1, customer.url().replace(BASE, ''));
check('with a delivery choice per shop and three ways to pay', (await customer.locator('[data-testid="delivery-fitrah"] input[type=radio]').count()) >= 1 && (await customer.locator('[data-testid="delivery-smoke-other-shop"] input[type=radio]').count()) >= 1 && (await customer.locator('[data-testid="pay-wallet"]').count()) === 1 && (await customer.locator('[data-testid="pay-bank_transfer"]').count()) === 1 && (await customer.locator('[data-testid="pay-card"]').count()) === 1);

// Refused first: no address and no delivery choice.
await submit(customer, '[data-testid="place-order"]');
await customer.waitForLoadState('networkidle');
check('placing it with nothing filled in is refused, and the basket kept', (await customer.locator('[data-testid="checkout-errors"]').count()) === 1 && (await customer.locator('[data-testid="checkout-form"]').count()) === 1);

await fillAddress(customer);
await customer.check('[data-testid="save-address"]');
await customer.locator('[data-testid="delivery-fitrah"] input[type=radio]:not([disabled])').first().check();
await customer.locator('[data-testid="delivery-smoke-other-shop"] input[type=radio]:not([disabled])').first().check();
await customer.check('[data-testid="pay-wallet"]');
await submit(customer, '[data-testid="place-order"]');
await customer.waitForLoadState('networkidle');
const paidNumber = ((await customer.locator('[data-testid="checkout-number"]').innerText().catch(() => '')).match(/AK-\d{4}-\d{6}/) ?? [''])[0];
check('paid from the wallet, the customer lands on the checkout marked paid', /\/shop\/checkout\/AK-/.test(customer.url()) && (await customer.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid' && (await customer.locator('[data-testid="paid-thanks"]').count()) === 1, `${customer.url().replace(BASE, '')}`);
const orderNumbers = await customer.locator('[data-testid="checkout-orders"] [data-order]').evaluateAll((els) => els.map((el) => el.getAttribute('data-order')));
check('with one order per shop', orderNumbers.length === 2 && orderNumbers.includes(`${paidNumber}-FIT`) && orderNumbers.includes(`${paidNumber}-SOS`), orderNumbers.join(', '));

await customer.goto(`${BASE}/en/my-orders`, { waitUntil: 'networkidle' });
check('My orders lists both as paid', (await customer.locator(`[data-order="${paidNumber}-FIT"]`).count()) === 1 && (await customer.locator(`[data-order="${paidNumber}-SOS"]`).count()) === 1 && (await text(customer)).includes('Paid'));
await customer.goto(`${BASE}/en/my-orders/${paidNumber}-FIT`, { waitUntil: 'networkidle' });
const receipt = await text(customer);
check('the receipt shows the lines, the total and who sold them', (await customer.locator('[data-testid="order-item"]').count()) === 1 && receipt.includes('Arabic Letters Tracing Book') && receipt.includes('Fitrah') && (await customer.locator('[data-testid="receipt-total"]').innerText()).includes('170.00'), receipt.slice(0, 160));
check('and an empty cart afterwards', (await customer.goto(`${BASE}/en/shop/cart`, { waitUntil: 'networkidle' }), (await customer.locator('[data-testid="cart-empty"]').count()) === 1));

// ------------------------------------------------------------ 3. by bank transfer

await addToCart(customer, MAT, 1);
await customer.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
check('the saved address is offered next time', (await customer.locator('[data-testid="saved-address"]').count()) === 1);
await customer.locator('[data-testid="saved-address"]').first().check();
await customer.locator('[data-testid="delivery-fitrah"] input[type=radio]:not([disabled])').first().check();
await customer.check('[data-testid="pay-bank_transfer"]');
await submit(customer, '[data-testid="place-order"]');
await customer.waitForLoadState('networkidle');
const bankNumber = ((await customer.locator('[data-testid="checkout-number"]').innerText().catch(() => '')).match(/AK-\d{4}-\d{6}/) ?? [''])[0];
check('a bank-transfer order waits, showing the account to pay into and the reference', (await customer.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'pending_payment' && (await customer.locator('[data-testid="bank-account"]').count()) === 1 && (await text(customer)).includes(bankNumber), bankNumber || `${customer.url().replace(BASE, '')} ${(await text(customer)).slice(0, 200)}`);

await customer.setInputFiles('[data-testid="slip-file"]', SLIP);
await customer.fill('[data-testid="slip-reference"]', 'SMOKE-TRX');
await submit(customer, '[data-testid="send-slip"]');
await customer.waitForLoadState('networkidle');
check('the slip is received and the page says the office is looking', (await customer.locator('[data-testid="slips"] [data-slip-status="waiting"]').count()) === 1 && (await customer.locator('[data-testid="slip-form"]').count()) === 0);

// ------------------------------------------------------------ the office confirms

const office = await newPage('office');
await signIn(office, ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
const slipRow = office.locator('[data-testid="bank-slips"] tr[data-slip-status="waiting"]').first();
check('the office sees the slip waiting, with the checkout and the customer', (await slipRow.count()) === 1 && (await slipRow.innerText()).includes(bankNumber) && (await slipRow.innerText()).includes('SMOKE-TRX'), (await slipRow.count()) ? (await slipRow.innerText()).replace(/\s+/g, ' ').slice(0, 160) : (await text(office)).slice(0, 160));
const slipId = ((await slipRow.getAttribute('data-testid').catch(() => '')) ?? '').replace('slip-row-', '');
const slipFile = await office.request.get(`${BASE}/en/shop/slips/${slipId}`);
check('and can open the slip itself', slipFile.status() === 200 && /image\/jpeg/.test(slipFile.headers()['content-type'] ?? ''), `HTTP ${slipFile.status()}`);
await office.click(`[data-testid="confirm-slip-${slipId}"]`);
await settle(office, `[data-testid="slip-row-${slipId}"][data-slip-status="confirmed"]`);
check('confirming it marks the slip confirmed', (await office.locator(`[data-testid="slip-row-${slipId}"]`).getAttribute('data-slip-status').catch(() => '')) === 'confirmed');
check('and the orders list has all three orders', (await office.locator(`[data-testid="order-row-${bankNumber}-FIT"]`).count()) === 1 && (await office.locator(`[data-testid="order-row-${paidNumber}-SOS"]`).count()) === 1);
const ordersCsv = await office.request.get(`${BASE}/en/admin/bookshop/orders/export`);
check('the office exports orders as CSV', ordersCsv.status() === 200 && (await ordersCsv.text()).includes(bankNumber), `HTTP ${ordersCsv.status()}`);

await customer.reload({ waitUntil: 'networkidle' });
check('the customer\'s page now says paid', (await customer.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid');
await customer.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('and they were told', (await text(customer)).includes(bankNumber), (await text(customer)).slice(0, 120));

// ------------------------------------------------------------ the vendor's delivery methods

const vendor = await newPage('vendor');
await signIn(vendor, VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await vendor.locator('[data-testid="accept-agreement"]').count()) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="delivery-methods"]');
}
check('the vendor portal shows the delivery methods, on the office\'s standard ones', (await vendor.locator('[data-testid="no-methods"]').count()) === 1);
await vendor.click('[data-testid="use-template"]');
await settle(vendor, '[data-testid="delivery-row-4"]');
check('starting from the template gives five rows to edit', (await vendor.locator('[data-testid="delivery-form"] [data-testid^="delivery-row-"]').count()) === 5);
await vendor.fill('[data-testid="delivery-name-0"]', 'Collect from Fitrah, Majeedhee Magu');
await vendor.click('[data-testid="save-methods"]');
await settle(vendor);
await vendor.reload({ waitUntil: 'networkidle' });
check('and the edit is kept', (await vendor.locator('[data-testid="delivery-name-0"]').inputValue().catch(() => '')) === 'Collect from Fitrah, Majeedhee Magu');

await finish();
