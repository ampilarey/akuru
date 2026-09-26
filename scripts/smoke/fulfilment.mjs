/**
 * Can a shop send an order, and a customer track it, return part of it,
 * cancel another and talk to the shop? (BOOKSHOP_PLAN slice B3.)
 *
 *   1. The student buys two tracing books and a prayer mat from Fitrah,
 *      delivered, paid from the wallet.
 *   2. Fitrah's owner finds it in the order queue, starts processing, marks
 *      it dispatched with a carrier and a tracking note, opens the packing
 *      slip and label, and marks it delivered.
 *   3. The student sees each step and the tracking, writes to the shop, and
 *      asks to return one tracing book as damaged.
 *   4. The owner sees the return under Returns, accepts it back onto the
 *      shelf, and reads the student's message in Messages.
 *   5. The student sees the return accepted and the money back in the
 *      wallet; then orders again for collection and cancels before the shop
 *      has it ready, and the money comes back.
 *   6. The owner puts the shop on holiday: the product page says when it is
 *      back and offers no cart button; then takes the holiday off.
 *
 * `SmokeMarkerSeeder::vendorCycle()` plants the products, clears the last
 * run's orders, returns, refunds and threads, reopens Fitrah and tops the
 * student's wallet up.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/fulfilment.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_VENDOR, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const BOOK = 'smoke-arabic-letters-tracing-book';
const MAT = 'smoke-kids-prayer-mat';
const MESSAGE = 'SMOKE-Please send it in a padded envelope.';

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

async function walletBalance(page) {
    await page.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
    const found = (await text(page)).match(/MVR\s*([\d,]+\.\d{2})/);

    return found ? money(found[1]) : NaN;
}

async function buy(page, lines, kind) {
    for (const [slug, quantity] of lines) {
        await page.goto(`${BASE}/en/shop/products/${slug}`, { waitUntil: 'networkidle' });
        await page.fill('[data-testid="quantity"]', String(quantity));
        await submit(page, '[data-testid="add-to-cart"]');
        await page.waitForLoadState('networkidle');
    }
    await page.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
    if ((await page.locator('[data-testid="saved-address"]').count()) > 0) {
        await page.locator('[data-testid="saved-address"]').first().check();
    } else {
        await page.fill('[data-testid="recipient-name"]', 'Smoke Customer');
        await page.fill('[data-testid="phone"]', '7700000');
        await page.fill('[data-testid="atoll"]', 'K');
        await page.fill('[data-testid="island"]', 'Malé');
        await page.fill('[data-testid="street"]', 'M. Smoke Villa');
        await page.check('[data-testid="save-address"]');
    }
    await page.locator(`[data-testid="delivery-fitrah"] input[data-delivery-kind="${kind}"]`).first().check();
    await page.check('[data-testid="pay-wallet"]');
    await submit(page, '[data-testid="place-order"]');
    await page.waitForLoadState('networkidle');

    return (await page.locator('[data-testid="checkout-orders"] [data-order]').first().getAttribute('data-order').catch(() => '')) ?? '';
}

// ------------------------------------------------------------ 1. the order

const student = await signIn(STUDENT);
const startBalance = await walletBalance(student);
const number = await buy(student, [[BOOK, 2], [MAT, 1]], 'courier_male');
check('the student buys from Fitrah, paid from the wallet', /^AK-\d{4}-\d{6}-FIT$/.test(number), number || (await text(student)).slice(0, 160));

// ------------------------------------------------------------ 2. the shop sends it

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await vendor.locator('[data-testid="accept-agreement"]').count()) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-orders"]');
}
await vendor.click('[data-testid="open-orders"]');
await settle(vendor, '[data-testid="order-tabs"]');
check('the portal opens the order queue, with a Paid tab', /\/vendor\/orders$/.test(vendor.url()) && (await vendor.locator('[data-testid="tab-paid"]').count()) === 1, vendor.url().replace(BASE, ''));

const card = vendor.locator(`[data-testid="vendor-order-${number}"]`);
const ensureOpen = async (locator) => {
    if ((await locator.locator('button[aria-expanded]').first().getAttribute('aria-expanded')) !== 'true') {
        await locator.locator('button[aria-expanded]').first().click();
    }
};
await ensureOpen(card);
await settle(vendor, `[data-testid="steps-${number}"]`);
const opened = (await card.innerText()).replace(/\s+/g, ' ');
check('the order opens with its lines and where it goes', opened.includes('Arabic Letters Tracing Book') && opened.includes('Kids Prayer Mat') && opened.includes('M. Smoke Villa'), opened.slice(0, 160));

await card.locator('[data-testid="step-processing"]').click();
await settle(vendor, `[data-testid="vendor-order-${number}"][data-status="processing"]`);
check('the shop starts processing it', (await vendor.locator(`[data-testid="vendor-order-${number}"]`).getAttribute('data-status')) === 'processing');

await ensureOpen(vendor.locator(`[data-testid="vendor-order-${number}"]`));
await vendor.fill(`[data-testid="steps-${number}"] [data-testid="carrier"]`, 'SMOKE-Courier');
await vendor.fill(`[data-testid="steps-${number}"] [data-testid="tracking-note"]`, 'Out with Ibrahim, 3pm');
await vendor.click(`[data-testid="steps-${number}"] [data-testid="step-dispatched"]`);
await settle(vendor, `[data-testid="vendor-order-${number}"][data-status="dispatched"]`);
check('and marks it dispatched with the carrier and a tracking note', (await vendor.locator(`[data-testid="vendor-order-${number}"]`).getAttribute('data-status')) === 'dispatched');

const orderId = await vendor.evaluate(() => {
    const link = document.querySelector('[data-testid="print-order"]');

    return link ? link.getAttribute('href').match(/orders\/(\d+)\/print/)?.[1] : null;
});
const print = await vendor.context().newPage();
await print.goto(`${BASE}/en/vendor/orders/${orderId}/print`, { waitUntil: 'networkidle' });
const label = (await print.locator('[data-testid="delivery-label"]').innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the packing slip and label print with the address and the order number', label.includes('Smoke Customer') && label.includes('M. Smoke Villa') && label.includes(number) && (await print.locator('[data-testid="packing-slip"] tbody tr').count()) === 2, label.slice(0, 160));
await print.close();

await vendor.click(`[data-testid="steps-${number}"] [data-testid="step-delivered"]`);
await settle(vendor, `[data-testid="vendor-order-${number}"][data-status="delivered"]`);
check('the shop marks it delivered', (await vendor.locator(`[data-testid="vendor-order-${number}"]`).getAttribute('data-status')) === 'delivered');

// ------------------------------------------------------------ 3. the customer tracks, writes, returns

await student.goto(`${BASE}/en/my-orders/${number}`, { waitUntil: 'networkidle' });
const done = await student.locator('[data-testid="order-progress"] [data-done="1"]').count();
check('the student sees every step done and the tracking note', done === 4 && (await student.locator('[data-testid="tracking"]').innerText()).includes('Out with Ibrahim'), `${done} of 4 steps`);

await student.fill('[data-testid="message-body"]', MESSAGE);
await submit(student, '[data-testid="send-message"]');
await student.waitForLoadState('networkidle');
check('the student writes to the shop about the order', (await student.locator('[data-testid="open-thread"]').count()) === 1);

// The tracing book is the order's first line, so the first choice.
check('the return form offers the tracing book first', (await student.locator('[data-testid="return-item"] option').first().innerText()).includes('Tracing Book'));
await student.fill('[data-testid="return-quantity"]', '1');
await student.selectOption('[data-testid="return-reason"]', 'damaged');
await student.fill('[data-testid="return-note"]', 'SMOKE-Cover torn');
await submit(student, '[data-testid="request-return"]');
await student.waitForLoadState('networkidle');
check('and asks to return one tracing book as damaged', (await student.locator('[data-testid="order-returns"] [data-return-status="requested"]').count()) === 1, `${student.url().replace(BASE, '')} ${(await text(student)).slice(0, 200)}`);

// ------------------------------------------------------------ 4. the shop answers

await vendor.goto(`${BASE}/en/vendor/orders?status=returns`, { waitUntil: 'networkidle' });
const returnCard = vendor.locator(`[data-testid="vendor-order-${number}"]`);
check('the shop finds it under Returns', (await returnCard.count()) === 1 && (await vendor.locator('[data-testid="tab-returns"]').innerText()).includes('(1)'));
await ensureOpen(returnCard);
await returnCard.locator('[data-testid="accept-return"]').click();
await settle(vendor, '[data-testid="flash-success"]');
// An answered return leaves the Returns tab; the order itself shows the outcome.
check('and accepts it, and the Returns tab is empty again', (await vendor.locator('[data-testid="flash-success"]').innerText().catch(() => '')).includes('Return accepted') && (await vendor.locator(`[data-testid="vendor-order-${number}"]`).count()) === 0);
await vendor.goto(`${BASE}/en/vendor/orders?q=${number}`, { waitUntil: 'networkidle' });
await ensureOpen(vendor.locator(`[data-testid="vendor-order-${number}"]`));
check('the order shows the return accepted and the refund done', (await vendor.locator('[data-return-status="accepted"]').count()) === 1 && (await vendor.locator('[data-testid="vendor-refunds"]').innerText().catch(() => '')).includes('Refunded'));

const threadHref = await vendor.locator(`[data-testid="vendor-order-${number}"] [data-testid="open-thread"]`).getAttribute('href').catch(() => null);
if (threadHref) {
    await vendor.goto(`${BASE}/en${threadHref}`, { waitUntil: 'networkidle' });
}
check('the student\'s message is in the shop\'s Messages', Boolean(threadHref) && (await text(vendor)).includes(MESSAGE), threadHref ?? 'no thread link');

// ------------------------------------------------------------ 5. money back; cancel another

await student.goto(`${BASE}/en/my-orders/${number}`, { waitUntil: 'networkidle' });
const refunds = await text(student);
check('the student sees the return accepted and the refund in the wallet', (await student.locator('[data-testid="order-returns"] [data-return-status="accepted"]').count()) === 1 && refunds.includes('refunded to your Akuru wallet'));

const second = await buy(student, [[MAT, 1]], 'collect_vendor');
await student.goto(`${BASE}/en/my-orders/${second}`, { waitUntil: 'networkidle' });
await student.locator('[data-testid="cancel-order"] summary').click();
await student.fill('[data-testid="cancel-reason"]', 'SMOKE-Bought one at school');
await submit(student, '[data-testid="confirm-cancel"]');
await student.waitForLoadState('networkidle');
check('the student cancels a collection order before it is ready', (await student.locator('[data-testid="order-cancelled"]').count()) === 1 && (await text(student)).includes('refunded to your Akuru wallet'), second);

const endBalance = await walletBalance(student);
// Paid 2×85 + 180 + 30 delivery = 380; back 85 + 30 delivery (the shop's fault). The mat, bought and cancelled, nets to zero.
check('the wallet is down by what was kept: 380 less the 115 returned', Math.abs((startBalance - endBalance) - 265) < 0.01, `${startBalance} → ${endBalance}`);

// ------------------------------------------------------------ 6. holiday mode

await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
const today = new Date().toISOString().slice(0, 10);
const later = new Date(Date.now() + 2 * 86400000).toISOString().slice(0, 10);
await vendor.fill('[data-testid="holiday-from"]', today);
await vendor.fill('[data-testid="holiday-until"]', later);
await vendor.fill('[data-testid="holiday-notice"]', 'SMOKE-Away for stock-take');
await vendor.click('[data-testid="save-settings"]');
await settle(vendor, '[data-testid="on-holiday"]');
check('the owner puts the shop on holiday', (await vendor.locator('[data-testid="on-holiday"]').count()) === 1);

await student.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
const away = (await student.locator('[data-testid="holiday-notice"]').innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the product says when the shop is back, and offers no cart button', away.includes('Back on') && away.includes('stock-take') && (await student.locator('[data-testid="add-to-cart"]').count()) === 0, away);

await vendor.fill('[data-testid="holiday-from"]', '');
await vendor.fill('[data-testid="holiday-until"]', '');
await vendor.click('[data-testid="save-settings"]');
await settle(vendor);
await vendor.reload({ waitUntil: 'networkidle' });
check('and takes the holiday off again', (await vendor.locator('[data-testid="on-holiday"]').count()) === 0);

await finish();
