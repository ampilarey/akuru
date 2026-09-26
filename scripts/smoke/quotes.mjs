/**
 * Can a school buying in bulk get a shop's price, and does the checkout
 * charge it? (BOOKSHOP_PLAN B9 "bulk quotes for schools", slice B9d.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` empties the teacher's cart and quotes.
 *
 * The teacher:
 *   1. puts 12 Arabic Letters Tracing Books in the cart, opens "ask Fitrah
 *      for a price" and asks for Majeediyya School;
 *   2. sees the quote waiting in My quotes;
 * Fitrah's owner:
 *   3. finds the request on the Quotes page, prices the book at 70.00
 *      for 14 days, and sends it — the live total reads 840.00;
 *   4. the CSV has the line and the price;
 * The teacher:
 *   5. sees the price and the saving, accepts it into the cart — the line
 *      is marked "Quoted price", its quantity locked, the line 840.00;
 *   6. the checkout's subtotal is 840.00, not 1020.00;
 * The office:
 *   7. sees one accepted quote on the bookstore page.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/quotes.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_TEACHER, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const SCHOOL = 'SMOKE-Majeediyya School';

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

// ------------------------------------------------------------ 1. asking

const teacher = await signIn(TEACHER);
await teacher.goto(`${BASE}/en/shop/products/smoke-arabic-letters-tracing-book`, { waitUntil: 'networkidle' });
await teacher.fill('[data-testid="quantity"]', '12');
await Promise.all([teacher.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), teacher.click('[data-testid="add-to-cart"]')]);
await teacher.goto(`${BASE}/en/shop/cart`, { waitUntil: 'networkidle' });
check('the cart offers a quote from Fitrah', (await count(teacher, '[data-testid="quote-form-fitrah"]')) === 1);
await teacher.click('[data-testid="quote-form-fitrah"] summary');
await teacher.fill('[data-testid="quote-organisation"]', SCHOOL);
await Promise.all([teacher.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), teacher.click('[data-testid="quote-submit"]')]);
const number = (await inner(teacher, '[data-testid="quote-number"]')).match(/QT-\d{4}-\d{6}/)?.[0] ?? '';
check('the quote is asked for', number !== '', number);

// ------------------------------------------------------------ 2. waiting

check('it waits for the shop', (await count(teacher, '[data-testid="quote-waiting"]')) === 1);
await teacher.goto(`${BASE}/en/my-quotes`, { waitUntil: 'networkidle' });
check('My quotes lists it', (await inner(teacher, '[data-testid="quotes-list"]')).includes(number));

// ------------------------------------------------------------ 3. the shop prices it

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-quotes"]');
}
await vendor.click('[data-testid="open-quotes"]');
await settle(vendor, '[data-testid="quotes-heading"]');
const card = `[data-testid="quote-${number}"]`;
check('Fitrah sees the request', (await inner(vendor, card)).includes(SCHOOL), (await inner(vendor, card)).slice(0, 120));
await vendor.locator(`${card} input[data-testid^="quote-price-"]`).first().fill('70');
await vendor.fill(`${card} [data-testid="quote-valid-days"]`, '14');
await vendor.fill(`${card} [data-testid="quote-vendor-note"]`, 'SMOKE school price');
check('the live total reads 840.00', (await inner(vendor, `${card} [data-testid="quote-live-total"]`)) === '840.00', await inner(vendor, `${card} [data-testid="quote-live-total"]`));
await vendor.click(`${card} [data-testid="quote-send"]`);
await settle(vendor, '[data-testid="flash-success"]');
check('the quote is sent', (await vendor.locator(card).getAttribute('data-status')) === 'quoted');

// ------------------------------------------------------------ 4. the CSV

const csv = await csvOf(vendor, '/en/vendor/quotes/export');
check('the CSV has the line and the price', csv.status === 200 && csv.text.includes(SCHOOL) && csv.text.includes('70.00'));

// ------------------------------------------------------------ 5. accepting

await teacher.goto(`${BASE}/en/my-quotes/${number}`, { waitUntil: 'networkidle' });
check('the teacher sees the price and the saving', (await inner(teacher, '[data-testid="quote-total"]')).includes('840.00') && (await inner(teacher, '[data-testid="quote-saving"]')).includes('180.00'));
await Promise.all([teacher.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), teacher.click('[data-testid="quote-accept"]')]);
check('accepting puts it in the cart at the quoted price', (await count(teacher, '[data-testid="cart-quoted"]')) === 1 && (await inner(teacher, '[data-testid="cart-line-total"]')).includes('840.00'), await inner(teacher, '[data-testid="cart-line-total"]'));
check('with its quantity locked', (await teacher.locator('[data-testid="cart-qty"]').first().getAttribute('readonly')) !== null);

// ------------------------------------------------------------ 6. the checkout

await teacher.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
const checkoutText = await inner(teacher, 'main');
check('the checkout charges the quoted price', checkoutText.includes('840.00') && !checkoutText.includes('1020.00'));

// ------------------------------------------------------------ 7. the office

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-quotes"]');
check('the office sees one accepted quote', (await inner(office, '[data-testid="quotes-count-accepted"]')).includes('1'), await inner(office, '[data-testid="quotes-count-accepted"]'));

await finish();
