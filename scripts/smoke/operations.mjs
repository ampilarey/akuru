/**
 * Can a shop with many items run its stock and products in bulk, and can
 * the office set how notices travel?
 * (BOOKSHOP_PLAN slice B8: bulk and operations.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` leaves Fitrah with the Kids Prayer Mat
 * at 3 (its low level is 5) and the Wooden Quran Stand sold out, no stock
 * log, default notice choices, and the office's switches at their defaults.
 *
 * Fitrah's owner:
 *   1. opens Stock from the portal: the mat is low, the stand sold out;
 *   2. receives five mats with a note — the log shows +5 by her, the mat
 *      leaves the low list;
 *   3. exports the product sheet and the blank template;
 *   4. imports a sheet: one price change, two new products, one bad row —
 *      the check shows exactly that and writes nothing; applying writes the
 *      three good rows and skips the bad one;
 *   5. on the products list, filters to low stock, then archives the two
 *      imported products together, and duplicates the tracing book;
 *   6. turns email off for new orders (SMS is still closed by the office);
 *   7. exports order lines from a date.
 * The office:
 *   8. sees low stock across shops, exports order lines, and lets shops get
 *      SMS — which Fitrah's notice settings then offer.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/operations.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const MAT = 'KIDS-PRAYER-MAT';
const STAND = 'QURAN-STAND';
const BOOK = 'smoke-arabic-letters-tracing-book';
const BOOK_SKU = 'ARABIC-LETTERS-TRACI';
const NOTE = 'SMOKE-Delivery from the printer';

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

// ------------------------------------------------------------ 1. the stock page

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-stock"]');
}
await vendor.click('[data-testid="open-stock"]');
await settle(vendor, '[data-testid="stock-heading"]');
check('the portal opens Stock', /\/vendor\/stock$/.test(vendor.url()) && (await inner(vendor, '[data-testid="stock-heading"]')).includes('Fitrah'), vendor.url().replace(BASE, ''));
check('the mat is low and the stand sold out', (await vendor.locator(`[data-testid="low-${MAT}"]`).getAttribute('data-state').catch(() => '')) === 'low'
    && (await vendor.locator(`[data-testid="low-${STAND}"]`).getAttribute('data-state').catch(() => '')) === 'sold_out', await inner(vendor, '[data-testid="low-stock"]'));

// ------------------------------------------------------------ 2. stock received

await vendor.selectOption('[data-testid="adjust-product"]', { label: `Kids Prayer Mat (${MAT})` });
await vendor.selectOption('[data-testid="adjust-mode"]', 'in');
await vendor.fill('[data-testid="adjust-quantity"]', '5');
await vendor.fill('[data-testid="adjust-note"]', NOTE);
await vendor.click('[data-testid="adjust-save"]');
await settle(vendor, '[data-testid="flash-success"]');
const received = vendor.locator('[data-testid^="movement-"][data-kind="in"]').first();
const receivedText = (await received.innerText().catch(() => '')).replace(/\s+/g, ' ');
check('five mats received: +5 in the log, with the note and who did it', receivedText.includes('+5') && receivedText.includes(NOTE) && receivedText.includes('Fitrah Owner'), receivedText);
check('and the mat leaves the low list', (await count(vendor, `[data-testid="low-${MAT}"]`)) === 0);

// ------------------------------------------------------------ 3. the sheet out

const sheet = await csvOf(vendor, await vendor.locator('[data-testid="export-sheet"]').getAttribute('href'));
check('the product sheet exports, the import layout with Fitrah\'s products', sheet.status === 200 && sheet.text.startsWith('id,sku,variant,parent_sku,title') && sheet.text.includes(BOOK_SKU) && !sheet.text.includes('OTHER-SECRET'), `HTTP ${sheet.status}`);
const template = await csvOf(vendor, '/en/vendor/stock/template');
check('and the blank template is the header alone', template.status === 200 && template.text.trim().split('\n').length === 1);

// ------------------------------------------------------------ 4. the sheet in

const dir = mkdtempSync(join(tmpdir(), 'akuru-sheet-'));
const file = join(dir, 'fitrah-sheet.csv');
writeFileSync(file, [
    'sku,title,price,stock,status',
    `${BOOK_SKU},,95,40,`,
    'WALK-IMP-A,SMOKE-Walk Import A,25,10,active',
    'WALK-IMP-B,SMOKE-Walk Import B,30,12,active',
    'WALK-BAD,SMOKE-Walk Bad Row,abc,1,',
].join('\n') + '\n');
await vendor.setInputFiles('[data-testid="import-file"]', file);
await vendor.click('[data-testid="import-check"]');
await settle(vendor, '[data-testid="import-preview"]');
const summary = await inner(vendor, '[data-testid="import-summary"]');
check('the check says: 2 new, 1 changed, 1 with errors', summary.includes('4 rows') && summary.includes('2 new') && summary.includes('1 changed') && summary.includes('1 with errors'), summary);
check('the bad row names its problem', (await vendor.locator('[data-action="error"]').innerText().catch(() => '')).includes('price must be a number'));
const before = await csvOf(vendor, `/en/shop/products/${BOOK}`);
check('nothing is written before Apply', !before.text.includes('SMOKE-Walk Import A') && before.text.includes('85.00'));
await Promise.all([vendor.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), vendor.click('[data-testid="import-apply"]')]);
await settle(vendor, '[data-testid="flash-success"]');
check('applied: 2 new, 1 changed, 1 skipped', (await inner(vendor, '[data-testid="flash-success"]')).includes('2 new, 1 changed') && (await inner(vendor, '[data-testid="flash-success"]')).includes('1 skipped'), await inner(vendor, '[data-testid="flash-success"]'));
const after = await csvOf(vendor, `/en/shop/products/${BOOK}`);
check('the tracing book now costs MVR 95.00 on the shop', after.text.includes('95.00'));
check('the new products\' opening stock is in the log', (await count(vendor, '[data-testid^="movement-"][data-kind="in"][data-sku="WALK-IMP-A"]')) === 1);

// ------------------------------------------------------------ 5. the products list

await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
await vendor.check('[data-testid="filter-low"]');
await vendor.click('[data-testid="filter-products"]');
await settle(vendor);
check('low stock only: the stand, not the tracing book', (await count(vendor, '[data-testid="product-row-smoke-quran-stand"]')) === 1 && (await count(vendor, `[data-testid="product-row-${BOOK}"]`)) === 0, await inner(vendor, '[data-testid="products-total"]'));
await vendor.uncheck('[data-testid="filter-low"]');
await vendor.fill('input[placeholder]', 'SMOKE-Walk Import');
await vendor.click('[data-testid="filter-products"]');
await settle(vendor, '[data-testid="product-row-smoke-walk-import-a"]');
await vendor.check('[data-testid="select-all"]');
await vendor.selectOption('[data-testid="bulk-status"]', 'archived');
await vendor.click('[data-testid="bulk-apply"]');
await settle(vendor, '[data-testid="flash-success"]');
check('the two imported products are archived together', (await inner(vendor, '[data-testid="flash-success"]')).includes('2 products changed')
    && (await inner(vendor, '[data-testid="product-row-smoke-walk-import-a"]')).includes('Archived') && (await inner(vendor, '[data-testid="product-row-smoke-walk-import-b"]')).includes('Archived'), await inner(vendor, '[data-testid="flash-success"]'));
await vendor.fill('input[placeholder]', 'Tracing');
await vendor.click('[data-testid="filter-products"]');
await settle(vendor, `[data-testid="duplicate-${BOOK}"]`);
await vendor.click(`[data-testid="duplicate-${BOOK}"]`);
await settle(vendor, `[data-testid="product-row-${BOOK}-copy"]`);
check('duplicated: a draft copy of the tracing book', (await inner(vendor, `[data-testid="product-row-${BOOK}-copy"]`)).includes('Copy of Arabic Letters Tracing Book') && (await inner(vendor, `[data-testid="product-row-${BOOK}-copy"]`)).includes('Draft'));

// ------------------------------------------------------------ 6. notices

check('new-order SMS is closed by the office', await vendor.locator('[data-testid="notice-new_order-sms"]').isDisabled() && (await count(vendor, '[data-testid="notices-office-off"]')) === 1);
await vendor.uncheck('[data-testid="notice-new_order-email"]');
await vendor.click('[data-testid="save-notices"]');
await settle(vendor, '[data-testid="flash-success"]');
await vendor.reload({ waitUntil: 'networkidle' });
check('email off for new orders, kept', !(await vendor.locator('[data-testid="notice-new_order-email"]').isChecked()) && (await vendor.locator('[data-testid="notice-low_stock-email"]').isChecked()));

// ------------------------------------------------------------ 7. order lines

await vendor.goto(`${BASE}/en/vendor/orders`, { waitUntil: 'networkidle' });
const since = new Date(Date.now() - 60 * 86400000).toISOString().slice(0, 10);
await vendor.fill('[data-testid="export-from"]', since);
const linesHref = await vendor.locator('[data-testid="export-order-lines"]').getAttribute('href');
const lines = await csvOf(vendor, linesHref);
check('order lines export from a date, one row per line', linesHref.includes(`from=${since}`) && lines.status === 200 && lines.text.startsWith('number,status,placed_at') && lines.text.includes(BOOK_SKU), `${linesHref} HTTP ${lines.status}`);

// ------------------------------------------------------------ 8. the office

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-low-stock"]');
check('the office sees low stock across shops', (await inner(office, '[data-testid="office-low-stock"]')).includes('Wooden Quran Stand'), (await inner(office, '[data-testid="office-low-stock"]')).slice(0, 160));
const officeLines = await csvOf(office, await office.locator('[data-testid="export-order-lines"]').getAttribute('href'));
check('and exports order lines for every shop', officeLines.status === 200 && officeLines.text.startsWith('number,status,vendor'));
await office.check('[data-testid="switch-vendor_sms"]');
await office.click('[data-testid="save-switches"]');
await settle(office, '[data-testid="flash-success"]');
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
check('with shop SMS on, Fitrah can choose SMS for new orders', !(await vendor.locator('[data-testid="notice-new_order-sms"]').isDisabled()));

await finish();
