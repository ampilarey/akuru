/**
 * Old and used books (LENDING_AND_USED_BOOKS_PLAN U1, STATUS §5mr).
 *
 * Fitrah's owner adds a used book from the short form — title, grade, a note
 * on its state, a price — and sends it to the office; the office approves
 * it; a guest finds it on the Used shelf and page, sees the grade on the card
 * and the note on the product page, and the Used filter keeps the new books
 * out.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/used-books.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const TITLE = `SMOKE-Walk Used Qaida ${Math.random().toString(36).slice(2, 7)}`;
const NOTE = 'SMOKE-Name written inside the cover.';

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
    const failed = results.filter(([, ok]) => !ok).length;
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(84)} ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

async function newPage(label) {
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 } });
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

async function signIn(email) {
    const page = await newPage(email);
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
const text = async (page) => (await page.innerText('body')).replace(/\s+/g, ' ');

// ------------------------------------------------------------ 1. the seller adds a used book from the short form

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="new-used-book"]');
}
await vendor.click('[data-testid="new-used-book"]');
await settle(vendor, '[data-testid="product-editor"]');
const editor = vendor.locator('[data-testid="product-editor"]');
const shortForm = (await editor.locator('[data-testid="product-sku"]').count()) === 0 && (await editor.locator('[data-testid="variants"]').count()) === 0 && (await editor.locator('[data-testid="show-full-form"]').count()) === 1;
check('"Add a used book" opens the short form: no SKU, no variants, condition set to Good', shortForm && (await editor.locator('[data-testid="product-condition"]').inputValue()) === 'good', `condition ${await editor.locator('[data-testid="product-condition"]').inputValue().catch(() => '?')}`);
await editor.locator('[data-testid="product-title"]').fill(TITLE);
await editor.locator('[data-testid="product-price"]').fill('40');
await editor.locator('[data-testid="product-condition"]').selectOption('fair');
await editor.locator('[data-testid="product-condition-note"]').fill(NOTE);
await editor.locator('[data-testid="product-status"]').selectOption('active');
await editor.locator('[data-testid="save-product"]').click();
const slug = TITLE.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
await settle(vendor, `[data-testid="product-row-${slug}"]`);
const row = vendor.locator(`[data-testid="product-row-${slug}"]`);
check('the book is saved with its grade and waits for the office', (await row.count()) === 1 && (await row.innerText()).includes('Used · Fair') && (await row.innerText()).includes('Waiting for approval'), (await row.innerText().catch(() => 'no row')).replace(/\s+/g, ' ').slice(0, 120));

// ------------------------------------------------------------ 2. the office approves it

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop#listings`, { waitUntil: 'networkidle' });
await settle(office, `[data-testid="listing-${slug}"]`);
const listing = office.locator(`[data-testid="listing-${slug}"]`);
check('the office sees it in the listings queue', (await listing.count()) === 1);
await Promise.all([
    office.waitForResponse((r) => r.url().includes('/listings/') && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}),
    listing.locator('[data-testid^="listing-approve-"]').click(),
]);
await settle(office);
// Approved means the seller's own list says so.
await vendor.reload({ waitUntil: 'networkidle' });
await settle(vendor, `[data-testid="product-row-${slug}"]`);
const approvedRow = (await vendor.locator(`[data-testid="status-${slug}"]`).getAttribute('data-status').catch(() => null));
check('and approves it: the seller\'s list says For sale', approvedRow === 'active', `status ${approvedRow}`);

// ------------------------------------------------------------ 3. a guest finds it

const guest = await newPage('guest');
await guest.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('the Bookstore menu has Used books, and a Used books shelf shows the card with its grade', (await count(guest, '[data-testid="shop-link-used"]')) === 1 && (await count(guest, `[data-testid="shop-used"] [data-product="${slug}"] [data-badge="condition"]`)) === 1);
await guest.click('[data-testid="shop-link-used"]');
await settle(guest, '[data-testid="shop-grid"]');
const usedCards = await count(guest, '[data-testid="shop-grid"] [data-product]');
const usedBadges = await count(guest, '[data-testid="shop-grid"] [data-badge="condition"]');
check('/shop/used lists only used books, every card with its grade', /\/shop\/used/.test(guest.url()) && usedCards >= 1 && usedBadges === usedCards && (await count(guest, `[data-product="${slug}"]`)) === 1, `${usedCards} cards, ${usedBadges} graded`);
await guest.goto(`${BASE}/en/shop/products/${slug}`, { waitUntil: 'networkidle' });
const page = await text(guest);
check('the product page says Used · Fair with the seller\'s note, and its structured data says used', (await count(guest, '[data-testid="product-condition"][data-condition="fair"]')) === 1 && page.includes(NOTE) && (await guest.locator('script[type="application/ld+json"]').allInnerTexts()).some((s) => s.includes('schema.org/UsedCondition')), page.slice(0, 120));
await guest.goto(`${BASE}/en/shop?used=1`, { waitUntil: 'networkidle' });
const filtered = await count(guest, '[data-testid="shop-grid"] [data-product]');
check('the Used filter on the main listing keeps the new books out', (await guest.locator('[data-testid="filter-used"]').isChecked()) && filtered === (await count(guest, '[data-testid="shop-grid"] [data-badge="condition"]')), `${filtered} cards`);

await finish();
