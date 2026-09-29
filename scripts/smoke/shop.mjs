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
// STATUS §5kw: every open shop is listed; one with nothing on sale says it is opening soon.
const shopLines = await guest.locator('[data-testid="shop-vendor-count"]').allInnerTexts();
check('every shop card says how many items it has, or that it is opening soon', shopLines.length > 0 && shopLines.every((t) => /\d+ items|Opening soon/.test(t.trim())), shopLines.map((t) => t.trim()).join(' · '));
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
await guest.locator('[data-testid="shop-search-go"]').click();
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

// ------------------------------------------------------------ deals: a timed sale (STATUS §5lb)
{
    const editBook = async () => {
        await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
        if ((await vendor.locator('[data-testid="accept-agreement"]').count()) > 0) {
            await vendor.check('[data-testid="accept-agreement"]');
            await vendor.click('[data-testid="agreement-form"] button[type=submit]');
            await vendor.waitForSelector(`[data-testid="edit-${BOOK}"]`);
        }
        await vendor.click(`[data-testid="edit-${BOOK}"]`);
        await vendor.waitForSelector('[data-testid="product-editor"]');
        for (const alt of await vendor.locator('[data-testid^="image-alt-"]').all()) {
            if (!(await alt.inputValue())) await alt.fill('SMOKE: front cover');
        }
    };
    const save = async () => {
        await vendor.click('[data-testid="save-product"]');
        await vendor.waitForSelector('[data-testid="product-editor"]', { state: 'detached', timeout: 20000 }).catch(() => {});
        await vendor.waitForLoadState('networkidle');
    };
    const pad = (n) => String(n).padStart(2, '0');
    const inTwoDays = new Date(Date.now() + 2 * 86400000);
    const local = `${inTwoDays.getFullYear()}-${pad(inTwoDays.getMonth() + 1)}-${pad(inTwoDays.getDate())}T${pad(inTwoDays.getHours())}:${pad(inTwoDays.getMinutes())}`;

    await editBook();
    check('the product form has a timed sale', await vendor.locator('[data-testid="product-sale"]').isVisible());
    await vendor.fill('[data-testid="product-sale-percent"]', '20');
    await vendor.fill('[data-testid="product-sale-ends"]', local);
    await save();
    const flag = await vendor.locator(`[data-testid="sale-${BOOK}"]`).innerText().catch(() => '');
    check('the shop sets 20% off for two days, and its list says it is on sale', /On sale now/.test(flag) && /20% off/.test(flag), flag);

    const shopper = await newPage('deals');
    await shopper.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
    await shopper.click('[data-testid="nav-bookstore-more"]');
    await Promise.all([shopper.waitForURL(/\/shop\/deals$/), shopper.click('[data-testid="nav-bookstore-deals"]')]);
    await shopper.waitForLoadState('networkidle');
    const card = shopper.locator(`[data-testid="shop-grid"] [data-product="${BOOK}"]`);
    const cardText = (await card.innerText().catch(() => '')).replace(/\s+/g, ' ');
    check('Deals in the Bookstore menu lists the product with its badge and the price struck through', (await card.count()) === 1 && /20% off/.test(cardText) && (await card.locator('.line-through').count()) === 1, cardText.slice(0, 160));
    const first = await card.locator('[data-testid="card-sale-ends"]').innerText().catch(() => '');
    await shopper.waitForTimeout(1500);
    const second = await card.locator('[data-testid="card-sale-ends"]').innerText().catch(() => '');
    check('its countdown ticks', /Sale ends in/.test(first) && first !== second, `${first} → ${second}`);
    await shopper.screenshot({ path: `${process.env.SMOKE_SHOTS ?? '/tmp'}/deals.png` }).catch(() => {});
    await shopper.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
    check('the product page shows the sale', await shopper.locator('[data-testid="product-sale"]').isVisible(), (await shopper.locator('[data-testid="product-price"]').innerText().catch(() => '')).replace(/\s+/g, ' '));
    await shopper.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
    check('the store\'s front has a Deals shelf', await shopper.locator('[data-testid="shop-deals"]').isVisible());

    // The sale ends when the shop empties "% off" — the walk leaves the book as it found it.
    await editBook();
    await vendor.fill('[data-testid="product-sale-percent"]', '');
    await vendor.fill('[data-testid="product-sale-ends"]', '');
    await save();
    await shopper.goto(`${BASE}/en/shop/deals`, { waitUntil: 'networkidle' });
    check('ending the sale takes it off Deals', (await shopper.locator(`[data-product="${BOOK}"]`).count()) === 0 && (await shopper.locator('[data-testid="shop-empty"]').count()) + (await shopper.locator('[data-testid="shop-grid"] [data-product]').count()) > 0);
}

// ------------------------------------------------------------ a school's book list (STATUS §5lc)
{
    const PUZZLE = 'smoke-wooden-alphabet-puzzle';
    vendor.on('dialog', (d) => d.accept());
    const removeList = async () => {
        if ((await vendor.locator('[data-testid="delete-collection-smoke-grade-3"]').count()) > 0) {
            await vendor.click('[data-testid="delete-collection-smoke-grade-3"]');
            await vendor.waitForSelector('[data-testid="collection-row-smoke-grade-3"]', { state: 'detached', timeout: 15000 }).catch(() => {});
        }
    };
    await vendor.goto(`${BASE}/en/vendor/storefront/sections`, { waitUntil: 'networkidle' });
    await vendor.click('[data-testid="tab-collections"]');
    await removeList();
    await vendor.fill('[data-testid="collection-name"]', 'SMOKE Grade 3 list');
    await vendor.fill('[data-testid="collection-slug"]', 'smoke-grade-3');
    await vendor.check('[data-testid="collection-book-list-on"]');
    await vendor.fill('[data-testid="collection-school"]', 'SMOKE School');
    await vendor.fill('[data-testid="collection-grade"]', 'Grade 3');
    await vendor.check(`[data-testid="collection-product-${BOOK}"]`);
    await vendor.check(`[data-testid="collection-product-${PUZZLE}"]`);
    await vendor.fill(`[data-testid="collection-qty-${BOOK}"]`, '2');
    await vendor.click('[data-testid="save-collection"]');
    await vendor.waitForSelector('[data-testid="collection-row-smoke-grade-3"]', { timeout: 15000 }).catch(() => {});
    const row = (await vendor.locator('[data-testid="collection-row-smoke-grade-3"]').innerText().catch(() => '')).replace(/\s+/g, ' ');
    check('the shop makes a Grade 3 book list for its school, two of the workbook', /Book list · SMOKE School · Grade 3/.test(row), row);

    const parent = await newPage('book-list');
    await parent.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
    await parent.click('[data-testid="shop-link-book-lists"]');
    const card = parent.locator('[data-book-list="fitrah/smoke-grade-3"]');
    check('School book lists on the store\'s front has the school and grade', (await card.count()) === 1 && /SMOKE School/.test(await card.innerText()), (await card.innerText().catch(() => '')).replace(/\s+/g, ' '));
    await Promise.all([parent.waitForURL(/\/shop\/fitrah\/smoke-grade-3$/), card.click()]);
    await parent.waitForLoadState('networkidle');
    const panel = (await parent.locator('[data-testid="book-list"]').innerText().catch(() => '')).replace(/\s+/g, ' ');
    check('the list shows each item\'s quantity and what the list comes to', /2 ×/.test(panel) && /Arabic Letters Tracing Book/.test(panel) && /2 items: MVR/.test(panel), panel.slice(0, 200));
    await parent.screenshot({ path: `${process.env.SMOKE_SHOTS ?? '/tmp'}/book-list.png` }).catch(() => {});
    await Promise.all([parent.waitForURL(/\/shop\/cart$/), parent.click('[data-testid="book-list-add"]')]);
    await parent.waitForLoadState('networkidle');
    const book = await parent.locator(`[data-cart-line="${BOOK}"] input[name="quantity"]`).inputValue().catch(() => '');
    check('one tap puts the whole list in the cart', (await parent.locator(`[data-cart-line="${PUZZLE}"]`).count()) === 1 && book === '2', `workbook × ${book}; ${(await parent.locator('[data-testid="flash-success"]').innerText().catch(() => '')).trim()}`);

    await vendor.goto(`${BASE}/en/vendor/storefront/sections`, { waitUntil: 'networkidle' });
    await vendor.click('[data-testid="tab-collections"]');
    await removeList();
}

// ------------------------------------------------------------ the store's doors (STATUS §5ky)
{
    const desk = await newPage('doors');
    await desk.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
    await desk.click('[data-testid="nav-bookstore-more"]');
    const doors = (await desk.locator('[data-testid="nav-bookstore-menu"] a').allInnerTexts()).map((t) => t.trim());
    check('the Bookstore caret opens its sections', ['Shops', 'Deals', 'School book lists', 'Categories', 'My orders', 'Sell on Akuru', 'Shop owners: sign in'].every((t) => doors.includes(t)), doors.join(' · '));
    await Promise.all([desk.waitForURL(/\/shop#shops$/), desk.click('[data-testid="nav-bookstore-shops"]')]);
    await desk.waitForLoadState('networkidle');
    check('Shops lands on the list of shops, in view', (await desk.locator('#shops [data-vendor="fitrah"]').isVisible()), desk.url().replace(BASE, ''));
    await Promise.all([desk.waitForURL(/\/shop\/fitrah$/), desk.click('#shops [data-vendor="fitrah"]')]);
    check('a shop in the list opens its own page', /\/shop\/fitrah$/.test(desk.url()), desk.url().replace(BASE, ''));
    await Promise.all([desk.waitForURL(/\/shop#shops$/), desk.click('[data-testid="shop-link-shops"]')]);
    check('from a shop\'s page, Shops leads back to every shop', (await desk.locator('#shops').isVisible()), desk.url().replace(BASE, ''));
    const owners = await desk.getAttribute('[data-testid="shop-link-owners"]', 'href');
    check('the shop page offers shop owners their way in', /\/vendor$/.test(owners ?? ''), owners);
}

// ------------------------------------------------------------ a shop's own footer (STATUS §5kz)
{
    const visitor = await newPage('shop-footer');
    await visitor.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
    const compact = await visitor.locator('[data-testid="footer-compact"]').innerText().catch(() => '');
    check('a shop\'s page ends with only the copyright line', /© \d{4} Akuru Institute/.test(compact) && (await visitor.locator('[data-footer-group]').count()) === 0, compact.trim());
    check('and keeps the Akuru header', await visitor.locator('[data-testid="nav-bookstore"]').isVisible());
    await visitor.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
    check('the store\'s front keeps the full footer', (await visitor.locator('[data-footer-group]').count()) === 5 && (await visitor.locator('[data-testid="footer-compact"]').count()) === 0);

    // On a phone nothing is fixed to the foot of a shop's page; the cart is in its links.
    const small = await newPage('shop-phone-foot', { width: 390, height: 844 });
    await small.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
    const bars = (await small.locator('[data-testid="bottom-bar"]').count()) + (await small.locator('[data-testid="shop-bottom-bar"]').count());
    const gap = await small.evaluate(() => document.documentElement.scrollHeight - document.querySelector('[data-testid="footer-compact"]').getBoundingClientRect().bottom - window.scrollY);
    check('on a phone a shop\'s page has no fixed bar, and ends at its copyright line', bars === 0 && gap <= 1, `bars: ${bars}, space under the line: ${Math.round(gap)}px`);
    check('the cart is one tap away in the shop\'s links', await small.locator('[data-testid="shop-link-cart"]').isVisible());
    await small.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
    check('the store\'s own front keeps its phone bar', await small.locator('[data-testid="bottom-bar"]').isVisible());
}

// ------------------------------------------------------------ on a phone (STATUS §5kv)
{
    const phone = await newPage('phone', { width: 390, height: 844 });
    await phone.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
    const folded = await phone.evaluate(() => !document.querySelector('[data-testid="shop-more"]').open);
    const firstCard = await phone.locator('[data-testid="shop-grid"] [data-product]').first().boundingBox();
    check('on a phone the filters fold under one button, and a product shows on the first screen', folded && (await phone.locator('#shop-search').isVisible()) && firstCard !== null && firstCard.y < 844, `fold closed: ${folded}, first product at ${Math.round(firstCard?.y ?? -1)}px`);
    await phone.click('[data-testid="shop-more-toggle"]');
    const box = await phone.locator('[data-testid="filter-in-stock"]').boundingBox();
    check('tapping Filter and sort opens them, and "In stock only" is a normal checkbox', (await phone.locator('[data-testid="filter-sort"]').isVisible()) && box !== null && box.width < 30, `checkbox ${Math.round(box?.width ?? -1)}px wide`);
    check('the shop page fits the phone', (await phone.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)) <= 0);
}

await finish();
