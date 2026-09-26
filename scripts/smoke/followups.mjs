/**
 * The seven follow-ups the Bookstore audit found unbuilt (BOOKSHOP_PLAN
 * §15, slice B11): can each be done in a browser?
 *
 * `SmokeMarkerSeeder::vendorCycle()` reopens the shop and resets Fitrah's
 * products; `librarySmoke()` publishes SMOKE-Primer.
 *
 * Fitrah's owner:
 *   1. edits the tracing book — ties it to the SMOKE-Primer e-book, writes
 *      the words for its photo — and saves;
 * A guest:
 *   2. sees the "read it online" link and the photo's words on the product
 *      page, and opens the photo in the lightbox;
 * The student:
 *   3. buys it with a gift message; the message is on the order page;
 * Fitrah's owner:
 *   4. sees the message on the order and the packing slip;
 *   5. sees the shop's returns rate on the money page;
 * The office:
 *   6. sees GST collected on the tax report and in its CSV;
 *   7. closes the bookstore with a notice — a guest sees the notice, the
 *      student still opens their orders — then reopens it.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/followups.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const BOOK = 'smoke-arabic-letters-tracing-book';
const GIFT = 'SMOKE: Happy birthday, Hawwa! From Aishath.';
const NOTICE = 'SMOKE: closed for stocktaking until Thursday.';

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

async function newPage(label) {
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 }, acceptDownloads: true });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${label}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        // 503 is the closed notice itself in step 7; anything else at 500+ is a defect.
        if (response.status() >= 500 && response.status() !== 503) {
            problems.push(`${label}: HTTP ${response.status()} ${response.url()}`);
        }
    });

    return page;
}

async function signIn(email) {
    const page = await newPage(email);
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

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
const count = (page, selector) => page.locator(selector).count();
const inner = async (page, selector) => (await page.locator(selector).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
const text = async (page) => (await page.innerText('body').catch(() => '')).replace(/\s+/g, ' ');

// ------------------------------------------------------------ 1. the owner ties the e-book and writes the alt text

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, `[data-testid="edit-${BOOK}"]`);
}
await vendor.click(`[data-testid="edit-${BOOK}"]`);
await settle(vendor, '[data-testid="product-editor"]');
const ebookOptions = await vendor.locator('[data-testid="product-ebook"] option').allInnerTexts();
check('the product form offers the published Digital Library items as the e-book', ebookOptions.some((o) => o.includes('SMOKE-Primer')), ebookOptions.slice(0, 4).join(', '));
await vendor.selectOption('[data-testid="product-ebook"]', { label: 'SMOKE-Primer' });
const altInput = vendor.locator('[data-testid^="image-alt-"]').first();
check('and asks for words for the photo', (await count(vendor, '[data-testid^="image-alt-"]')) >= 1 && (await altInput.getAttribute('required')) !== null);
await altInput.fill('SMOKE: front cover, a child tracing alif');
await vendor.click('[data-testid="save-product"]');
await vendor.waitForSelector('[data-testid="product-editor"]', { state: 'detached', timeout: 20000 }).catch(() => {});
await settle(vendor, '[data-testid="flash-success"]');
check('saved', (await count(vendor, '[data-testid="flash-success"]')) === 1, await inner(vendor, '[data-testid="flash-success"]'));

// ------------------------------------------------------------ 2. a guest: the link, the alt text, the lightbox

const guest = await newPage('guest');
await guest.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
const ebookHref = await guest.locator('[data-testid="ebook-link"] a').getAttribute('href').catch(() => '');
check('the product page links to the e-book in the Digital Library', (ebookHref || '').includes('/library/smoke-primer') && (await inner(guest, '[data-testid="ebook-link"]')).includes('SMOKE-Primer'), ebookHref || '');
check('the photo carries the words the shop wrote', (await guest.locator('[data-main-image]').getAttribute('alt')) === 'SMOKE: front cover, a child tracing alif', await guest.locator('[data-main-image]').getAttribute('alt'));
await guest.click('[data-testid="product-gallery"] a[data-zoom="0"]');
await guest.waitForSelector('[data-testid="zoom-dialog"][open]', { timeout: 10000 }).catch(() => {});
check('clicking the photo opens it larger in a lightbox, on the same page', (await count(guest, '[data-testid="zoom-dialog"][open]')) === 1 && (await guest.locator('[data-testid="zoom-image"]').getAttribute('alt')) === 'SMOKE: front cover, a child tracing alif' && guest.url().includes(`/shop/products/${BOOK}`));
await guest.click('[data-testid="zoom-close"]');
await guest.waitForSelector('[data-testid="zoom-dialog"][open]', { state: 'detached', timeout: 10000 }).catch(() => {});
check('and closes it', (await count(guest, '[data-testid="zoom-dialog"][open]')) === 0);

// ------------------------------------------------------------ 3. the student buys with a gift message

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
await student.fill('[data-testid="quantity"]', '1');
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
await settle(student, '[data-testid="gift-message"]');
check('the checkout has a gift message field', (await count(student, '[data-testid="gift-message"]')) === 1);
await student.fill('[data-testid="recipient-name"]', 'Smoke Customer');
await student.fill('[data-testid="phone"]', '7700000');
await student.fill('[data-testid="atoll"]', 'K');
await student.fill('[data-testid="island"]', 'Malé');
await student.fill('[data-testid="street"]', 'M. Smoke Villa');
await student.locator('[data-testid="delivery-fitrah"] input[type=radio]:not([disabled])').first().check();
await student.check('[data-testid="pay-wallet"]');
await student.fill('[data-testid="gift-message"]', GIFT);
await submit(student, '[data-testid="place-order"]');
const paidNumber = ((await student.locator('[data-testid="checkout-number"]').innerText().catch(() => '')).match(/AK-\d{4}-\d{6}/) ?? [''])[0];
const number = paidNumber ? `${paidNumber}-FIT` : '';
check('paid from the wallet', /\/shop\/checkout\/AK-/.test(student.url()) && (await student.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid', student.url().replace(BASE, ''));
await student.goto(`${BASE}/en/my-orders/${number}`, { waitUntil: 'networkidle' });
check('the order page shows the gift message', (await inner(student, '[data-testid="order-gift-message"]')).includes(GIFT), await inner(student, '[data-testid="order-gift-message"]'));

// ------------------------------------------------------------ 4. the shop sees it on the order and the slip

await vendor.goto(`${BASE}/en/vendor/orders`, { waitUntil: 'networkidle' });
await settle(vendor, `[data-testid="vendor-order-${number}"]`);
const card = vendor.locator(`[data-testid="vendor-order-${number}"]`);
if ((await card.locator('button[aria-expanded]').first().getAttribute('aria-expanded').catch(() => 'true')) !== 'true') {
    await card.locator('button[aria-expanded]').first().click();
}
await card.locator('[data-testid="vendor-gift-message"]').waitFor({ timeout: 20000 }).catch(() => {});
check('the shop sees the gift message on the order', (await card.locator('[data-testid="vendor-gift-message"]').innerText().catch(() => '')).includes(GIFT));
const orderId = await card.locator('[data-testid="print-order"]').getAttribute('href').then((h) => h?.match(/orders\/(\d+)\/print/)?.[1]).catch(() => null);
const print = await vendor.context().newPage();
await print.goto(`${BASE}/en/vendor/orders/${orderId}/print`, { waitUntil: 'networkidle' });
check('and on the packing slip', (await inner(print, '[data-testid="print-gift-message"]')).includes(GIFT), await inner(print, '[data-testid="print-gift-message"]'));
await print.close();

// ------------------------------------------------------------ 5. the returns rate

await vendor.goto(`${BASE}/en/vendor/money`, { waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="stat-returns-rate"]');
const rate = await inner(vendor, '[data-testid="stat-returns-rate"]');
check('the money page shows the returns rate, of the orders delivered', rate.includes('Returns rate') && /\d+\.\d%|—/.test(rate) && rate.includes('delivered orders'), rate);

// ------------------------------------------------------------ 6. GST collected on the tax report

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="tax-report"]');
const taxHead = await inner(office, '[data-testid="tax-report"] thead');
check('the tax report has a GST collected column', taxHead.includes('GST collected') && (await count(office, '[data-testid^="tax-sales-gst-"]')) >= 1, taxHead);
const csv = await office.request.get(`${BASE}/en/admin/bookshop/money/tax-report/export`);
const csvHead = (await csv.text()).split('\n')[0];
check('and its CSV carries it too', csv.status() === 200 && csvHead.includes('sales_gst'), csvHead);

// ------------------------------------------------------------ 7. closed, then open again

await settle(office, '[data-testid="office-shop-open"]');
check('the office sees the bookstore open', (await inner(office, '[data-testid="shop-open-state"]')).includes('open'));
await office.fill('[data-testid="shop-closed-message"]', NOTICE);
await office.click('[data-testid="toggle-shop-open"]');
await office.waitForFunction(() => document.querySelector('[data-testid="shop-open-state"]')?.textContent?.includes('closed'), null, { timeout: 20000 }).catch(() => {});
check('closes it with a notice', (await inner(office, '[data-testid="shop-open-state"]')).includes('closed'), await inner(office, '[data-testid="shop-open-state"]'));

const closedResponse = await guest.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('a guest sees the notice, as a 503', closedResponse?.status() === 503 && (await count(guest, '[data-testid="shop-closed"]')) === 1 && (await text(guest)).includes(NOTICE), `${closedResponse?.status()}`);
const productClosed = await guest.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
check('the product page too', productClosed?.status() === 503 && (await count(guest, '[data-testid="shop-closed"]')) === 1);
await student.goto(`${BASE}/en/my-orders/${number}`, { waitUntil: 'networkidle' });
check('the student still opens their order', (await count(student, '[data-testid="order-gift-message"]')) === 1 && (await count(student, '[data-testid="shop-closed"]')) === 0, student.url().replace(BASE, ''));
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
check('and the shop still runs its portal', (await count(vendor, `[data-testid="edit-${BOOK}"]`)) === 1);

await office.click('[data-testid="toggle-shop-open"]');
await office.waitForFunction(() => document.querySelector('[data-testid="shop-open-state"]')?.textContent?.includes(' open'), null, { timeout: 20000 }).catch(() => {});
const openResponse = await guest.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('reopened, the guest browses again', openResponse?.status() === 200 && (await count(guest, '[data-testid="shop-closed"]')) === 0, `${openResponse?.status()}`);

await finish();
