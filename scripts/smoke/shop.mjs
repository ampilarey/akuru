/**
 * Can anyone find Fitrah's products in the Akuru Bookstore?
 * (BOOKSHOP_PLAN slice B1b; the name settled 2026-09-26.)
 *
 * A guest, signed in as nobody:
 *
 *   1. finds "Bookstore" in the site's header and opens the bookstore;
 *   2. sees new arrivals with their photos (card-size copies, not the
 *      original), the categories that have something in them, and the
 *      shops; never a draft;
 *   3. opens a category, searches, filters to what is in stock and sorts;
 *   4. opens a product page — price, "sold by", tax note, stock, details,
 *      a large photo — and goes from it to the vendor's page, which says
 *      "at Akuru Bookstore" and shows only that vendor's products;
 *   5. reads the shop in Dhivehi, sees the phone's bottom bar at phone
 *      width, exports the listing and finds the product in the sitemap.
 *
 * Then Fitrah's owner finds the link from the portal to their shop page.
 *
 * `SmokeMarkerSeeder::vendorCycle()` plants Fitrah's three products (one
 * with a photo, one with a Dhivehi title), a draft that must stay hidden,
 * and a second shop.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/shop.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
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

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
const cards = async (page) => page.locator('[data-testid="shop-grid"] [data-product]').evaluateAll((els) => els.map((el) => el.getAttribute('data-product')));
const loads = async (page, src) => {
    if (!src) {
        return false;
    }
    const response = await page.request.get(src.startsWith('http') ? src : `${BASE}${src}`);

    return response.status() === 200;
};

// ------------------------------------------------------------ 1. finding it

const guest = await newPage('guest');
await guest.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
const headerLink = guest.locator('nav a', { hasText: /^\s*Bookstore\s*$/ }).first();
check('the site header offers Bookstore', (await headerLink.count()) === 1 && /\/shop$/.test((await headerLink.getAttribute('href')) ?? ''));
await headerLink.click();
await guest.waitForLoadState('networkidle');
check('it opens the Akuru Bookstore', /\/shop$/.test(guest.url()) && (await guest.locator('[data-testid="shop-heading"]').innerText()).includes('Akuru Bookstore'), guest.url());
check('and neither earlier name is on the page', !/Bookshop|Online Store/.test(await guest.innerText('body')));

// ------------------------------------------------------------ 2. the front

const arrivals = guest.locator('[data-testid="new-arrivals"]');
const arrivalsText = (await arrivals.count()) ? (await arrivals.innerText()).replace(/\s+/g, ' ') : '';
check('new arrivals show Fitrah\'s products with who sells them', arrivalsText.includes('Arabic Letters Tracing Book') && arrivalsText.includes('Sold by Fitrah'), arrivalsText.slice(0, 160));
const cardImage = await guest.locator(`[data-testid="new-arrivals"] [data-product="${BOOK}"] img`).first().getAttribute('src').catch(() => null);
check('a product photo is shown as a card-size copy', Boolean(cardImage) && /-w480\.webp$/.test(cardImage), cardImage ?? 'no image');
check('and it loads', await loads(guest, cardImage));
check('the categories with something in them are listed', (await guest.locator('[data-testid="shop-categories"] a', { hasText: 'Workbooks' }).count()) === 1);
check('the shops are listed', (await guest.locator('[data-testid="shop-vendors"] [data-vendor="fitrah"]').count()) === 1);
check('a draft is nowhere in the shop', !(await text(guest)).includes('SMOKE-Hidden-Draft'));
const draft = await guest.request.get(`${BASE}/en/shop/products/smoke-hidden-draft`);
check('and its address is not found', draft.status() === 404, `HTTP ${draft.status()}`);

// ------------------------------------------------------------ 3. finding things

await guest.locator('[data-testid="shop-categories"] a', { hasText: 'Workbooks' }).click();
await guest.waitForLoadState('networkidle');
const inCategory = await cards(guest);
check('a category shows what is in it and nothing else', /\/shop\/c\/workbooks$/.test(guest.url()) && inCategory.includes(BOOK) && !inCategory.includes('smoke-wooden-alphabet-puzzle'), `${guest.url().replace(BASE, '')} → ${inCategory.join(', ')}`);

await guest.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
await guest.fill('#shop-search', 'puzzle');
await guest.locator('[data-testid="shop-filters"] button[type=submit]').click();
await guest.waitForLoadState('networkidle');
const found = await cards(guest);
check('search finds by name', found.length === 1 && found[0] === 'smoke-wooden-alphabet-puzzle', found.join(', ') || 'nothing');

await guest.goto(`${BASE}/en/shop?vendor=fitrah&in_stock=1&sort=price_desc`, { waitUntil: 'networkidle' });
const sorted = await cards(guest);
check('in stock, dearest first', sorted[0] === 'smoke-wooden-alphabet-puzzle' && sorted.at(-1) === BOOK, sorted.join(' > '));

// ------------------------------------------------------------ 4. a product

await guest.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
const product = await text(guest);
check('the product page shows the price, the seller and the tax note', product.includes('MVR 85.00') && product.includes('Sold by Fitrah') && product.includes('Prices include any tax.'), product.slice(0, 160));
check('its stock, its details and an add-to-cart button', /In stock|Only \d+ left/.test(product) && product.includes('Author') && (await guest.locator('[data-testid="add-to-cart"]').count()) === 1);
const large = await guest.locator('[data-main-image]').getAttribute('src').catch(() => null);
check('its photo is a large copy that loads', Boolean(large) && /-w1200\.webp$/.test(large) && (await loads(guest, large)), large ?? 'no image');

await guest.locator('[data-testid="product-vendor"] a').click();
await guest.waitForLoadState('networkidle');
const vendorPage = await text(guest);
const vendorCards = await cards(guest);
check('the seller\'s page says "at Akuru Bookstore"', /\/shop\/fitrah$/.test(guest.url()) && vendorPage.includes('iman.noor.ihsan') && vendorPage.includes('at Akuru Bookstore'), guest.url().replace(BASE, ''));
// Four since B7: the sold-out Wooden Quran Stand polish.mjs is told about.
check('and shows only that seller\'s products', vendorCards.length === 4 && !vendorCards.includes('smoke-other-secret'), vendorCards.join(', '));

// ------------------------------------------------------------ 5. and the rest

await guest.goto(`${BASE}/dv/shop`, { waitUntil: 'networkidle' });
const dv = await text(guest);
check('the bookstore reads in Dhivehi, with a Dhivehi title where the seller gave one', dv.includes('އަކުރު ފޮތްފިހާރަ') && dv.includes('ލަކުޑި އަކުރު ޕަޒަލް') && dv.includes('އާ ތަކެތި'), dv.slice(0, 120));

const phone = await newPage('phone', { width: 390, height: 844 });
await phone.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('on a phone the shop has its bottom bar', await phone.locator('[data-testid="shop-bottom-bar"]').isVisible());
check('and on a desktop it does not', !(await guest.locator('[data-testid="shop-bottom-bar"]').isVisible()));

const csv = await guest.request.get(`${BASE}/en/shop/export?vendor=fitrah`);
const csvText = await csv.text();
check('the listing exports as CSV', csv.status() === 200 && csvText.includes('Arabic Letters Tracing Book') && !csvText.includes('SMOKE-Other-Secret'), `HTTP ${csv.status()}`);
const sitemap = await guest.request.get(`${BASE}/sitemap.xml`);
check('the sitemap lists the product and the shop page', (await sitemap.text()).includes(`/shop/products/${BOOK}`) && (await sitemap.text()).includes('/shop/fitrah'), `HTTP ${sitemap.status()}`);

// ------------------------------------------------------------ the vendor's link

const vendor = await newPage(VENDOR);
await vendor.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await vendor.fill('input[name="identifier"]', VENDOR);
await vendor.fill('input[name="password"]', PASSWORD);
await vendor.click('button[type=submit]');
await vendor.waitForLoadState('networkidle');
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
const portalLink = await vendor.locator('[data-testid="open-shop-page"]').getAttribute('href').catch(() => null);
check('the vendor portal links to the shop\'s own page', portalLink === '/shop/fitrah', portalLink ?? 'no link');

await finish();
