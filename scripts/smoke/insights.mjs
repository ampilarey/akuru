/**
 * Does a shop see how visitors move through it, and does search still find
 * things behind its new contract? (BOOKSHOP_PLAN slice B9e.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` empties Fitrah's funnel counters.
 *
 * A guest:
 *   1. searches the shop for "tracing" and finds the tracing book (the
 *      database driver, the default);
 *   2. visits Fitrah's page, opens the tracing book twice (counted once),
 *      and adds it to the cart;
 * Fitrah's owner:
 *   3. opens Insights from the portal: one shop visit, one product view,
 *      one add to cart, the rate from the step before, the tracing book
 *      at the top of the products, the shop page among the pages;
 *   4. switches to the last 7 days, and the two CSVs download;
 *   5. opening their own product page does not count;
 * The office:
 *   6. sees Fitrah's row in the shop funnels, and its CSV.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/insights.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const BOOK = 'smoke-arabic-letters-tracing-book';

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

// ------------------------------------------------------------ 1. search

const guestContext = await browser.newContext();
await guestContext.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const guest = await guestContext.newPage();
guest.on('pageerror', (error) => problems.push(`guest: page error: ${String(error).slice(0, 140)}`));
guest.on('response', (r) => { if (r.status() >= 500) problems.push(`guest: HTTP ${r.status()} ${r.url()}`); });
await guest.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
await guest.fill('#shop-search', 'tracing');
await Promise.all([guest.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), guest.press('#shop-search', 'Enter')]);
check('searching "tracing" finds the tracing book', (await inner(guest, 'main')).includes('Arabic Letters Tracing Book'));

// ------------------------------------------------------------ 2. a visit

await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
await guest.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
await guest.reload({ waitUntil: 'networkidle' });
await Promise.all([guest.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), guest.click('[data-testid="add-to-cart"]')]);
check('the guest visits, looks twice and adds to cart', (await inner(guest, 'main')).length > 0);

// ------------------------------------------------------------ 3. the shop's funnel

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-insights"]');
}
await vendor.click('[data-testid="open-insights"]');
await settle(vendor, '[data-testid="insights-heading"]');
const stepCount = async (metric) => Number(await vendor.locator(`[data-testid="funnel-${metric}"]`).getAttribute('data-count'));
check('one shop visit', (await stepCount('shop_view')) === 1, String(await stepCount('shop_view')));
check('one product view (the reload not counted)', (await stepCount('product_view')) === 1, String(await stepCount('product_view')));
check('one add to cart, with its rate', (await stepCount('cart_add')) === 1 && (await inner(vendor, '[data-testid="funnel-cart_add"]')).includes('100%'), await inner(vendor, '[data-testid="funnel-cart_add"]'));
check('the tracing book tops the products', (await inner(vendor, '[data-testid="insights-products"] tbody tr')).includes('Arabic Letters Tracing Book'));
check('the shop page is among the pages', (await inner(vendor, '[data-testid="insights-pages"]')).includes('Shop page'));

// ------------------------------------------------------------ 4. range and CSVs

await vendor.click('[data-testid="range-7"]');
await vendor.waitForURL(/days=7/, { timeout: 20000 }).catch(() => {});
await settle(vendor, '[data-testid="funnel-shop_view"]');
check('the last 7 days', vendor.url().includes('days=7') && (await stepCount('shop_view')) === 1, vendor.url() + ' ' + (await stepCount('shop_view')));
const days = await csvOf(vendor, '/en/vendor/insights/export?days=7');
const products = await csvOf(vendor, '/en/vendor/insights/export?list=products&days=7');
check('the CSVs download', days.status === 200 && days.text.startsWith('day,') && products.text.includes('Arabic Letters Tracing Book'));

// ------------------------------------------------------------ 5. the owner's own visit

await vendor.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
await vendor.goto(`${BASE}/en/vendor/insights`, { waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="insights-heading"]');
check('the owner looking at their own product is not counted', (await stepCount('product_view')) === 1);

// ------------------------------------------------------------ 6. the office

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-insights"]');
check('the office sees Fitrah\'s funnel', (await inner(office, '[data-testid="insights-fitrah"]')).startsWith('Fitrah 1 1 1'), await inner(office, '[data-testid="insights-fitrah"]'));
const all = await csvOf(office, '/en/admin/bookshop/insights/export?days=30');
check('and its CSV', all.status === 200 && all.text.includes('Fitrah,fitrah,1,1,1'));

await finish();
