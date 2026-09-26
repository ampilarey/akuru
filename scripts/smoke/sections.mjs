/**
 * Can a vendor arrange its page from sections, add a page and a collection,
 * and publish — and can the office moderate it?
 * (BOOKSHOP_PLAN slice B5: the storefront designer, part 2.)
 *
 * Fitrah's owner:
 *
 *   1. opens Sections and pages from the portal, uploads two photos to the
 *      image library;
 *   2. arranges the home: a hero with a photo and a button to a product,
 *      featured products, an FAQ and a video; saves; the preview shows them;
 *   3. sets the storefront menu and the SEO title; adds an "About us" page
 *      with a text section, and a "Starter kit" collection of two products,
 *      then a Collection section pointing at it;
 *   4. publishes: a guest sees the hero, the menu, the featured products,
 *      the FAQ, the video and the collection; the About page at /p/about-us;
 *      the collection at /starter-kit with its two products; and the product
 *      page carries structured data.
 *
 * The office then takes the storefront down (a guest sees the plain page),
 * lifts it (back at once), and locks the Video section for Fitrah (the
 * designer says so and refuses to save one).
 *
 * `SmokeMarkerSeeder::vendorCycle()` clears Fitrah's storefront, pages,
 * collections and image library.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/sections.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const PHOTO = new URL('../../database/seeders/fixtures/vendors/fitrah-logo.jpg', import.meta.url).pathname;
const HERO = 'SMOKE-Learning made joyful';
const FAQ_Q = 'SMOKE-Do you deliver to the atolls?';
const ABOUT = 'SMOKE-Two parents and a printer.';
const SEO_TITLE = 'SMOKE-Fitrah learning materials';
const BOOK = 'smoke-arabic-letters-tracing-book';
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

async function newPage(label) {
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 } });
    context.setDefaultNavigationTimeout(60000);
    // Google Fonts, YouTube and OpenStreetMap are off-host and aborted like everything else.
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

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const count = (page, selector) => page.locator(selector).count();
const frameText = async (page) => (await page.frameLocator('[data-testid="preview-frame"]').locator('body').innerText().catch(() => '')).replace(/\s+/g, ' ');

// ------------------------------------------------------------ 1. the designer and the image library

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-sections"]');
}
await vendor.click('[data-testid="open-sections"]');
await settle(vendor, '[data-testid="sections-heading"]');
check('the portal opens Sections and pages', /\/vendor\/storefront\/sections$/.test(vendor.url()) && (await vendor.locator('[data-testid="sections-heading"]').innerText()).includes('Fitrah'), vendor.url().replace(BASE, ''));

await vendor.click('[data-testid="tab-images"]');
await vendor.setInputFiles('[data-testid="upload-images"]', [PHOTO, PHOTO]);
await vendor.click('[data-testid="upload-submit"]');
await settle(vendor, '[data-testid="library"]');
check('two photos join the image library', (await count(vendor, '[data-testid="library"] li')) === 2, `${await count(vendor, '[data-testid="library"] li')} in the library`);

// ------------------------------------------------------------ 2. the home's sections

await vendor.click('[data-testid="tab-home"]');
const addSection = async (type) => {
    await vendor.selectOption('[data-testid="home-add-type"]', type);
    await vendor.click('[data-testid="home-add"]');
};
await addSection('hero');
await vendor.fill('[data-testid="home-0-heading"]', HERO);
await vendor.locator('[data-testid="home-0-images"] button').first().click();
await vendor.click('[data-testid="home-0-buttons-add"]');
await vendor.selectOption('[data-testid="home-0-buttons-0-kind"]', 'product');
await vendor.selectOption('[data-testid="home-0-buttons-0-target"]', BOOK);
await vendor.fill('[data-testid="home-0-buttons-0-label"]', 'Shop the book');
await addSection('featured_products');
await vendor.fill('[data-testid="home-1-heading"]', 'SMOKE-Picks');
await vendor.check(`[data-testid="home-1-products-${BOOK}"]`);
await vendor.check(`[data-testid="home-1-products-${PUZZLE}"]`);
await addSection('faq');
await vendor.click('[data-testid="home-2-items-add"]');
await vendor.fill('[data-testid="home-2-items-0-question"]', FAQ_Q);
await vendor.fill('[data-testid="home-2-items-0-answer"]', 'Yes, by boat.');
await addSection('video');
await vendor.fill('[data-testid="home-3-url"]', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
check('four sections are arranged: hero, featured products, FAQ, video', (await count(vendor, '[data-testid^="home-row-"]')) === 4);
await vendor.click('[data-testid="save-sections"]');
await settle(vendor, '[data-testid="flash-success"]');
check('the draft saves', (await count(vendor, '[data-testid="flash-success"]')) === 1 && (await count(vendor, '[data-testid^="home-row-"]')) === 4, (await text(vendor)).slice(0, 120));

await vendor.frameLocator('[data-testid="preview-frame"]').locator('[data-testid="storefront-sections"]').waitFor({ timeout: 20000 }).catch(() => {});
const previewBody = await frameText(vendor);
check('the preview shows the hero, the picks and the FAQ on the real page, marked a draft', previewBody.includes(HERO) && previewBody.includes('SMOKE-Picks') && previewBody.includes(FAQ_Q) && previewBody.includes('Preview'), previewBody.slice(0, 160));

// ------------------------------------------------------------ 3. menu, SEO, a page, a collection

await vendor.click('[data-testid="tab-menu"]');
await vendor.click('[data-testid="nav-add"]');
await vendor.fill('[data-testid="nav-0-label"]', 'Shop');
await vendor.fill('[data-testid="home-seo-title"]', SEO_TITLE);
await vendor.click('[data-testid="save-menu"]');
await settle(vendor, '[data-testid="flash-success"]');

await vendor.click('[data-testid="tab-pages"]');
await vendor.fill('[data-testid="new-page-title"]', 'About us');
await vendor.click('[data-testid="create-page"]');
await settle(vendor, '[data-testid="page-row-about-us"]');
check('an About us page is made at /p/about-us', (await count(vendor, '[data-testid="page-row-about-us"]')) === 1);
await vendor.click('[data-testid="edit-page-about-us"]');
await settle(vendor, '[data-testid="page-editor"]');
await vendor.selectOption('[data-testid="page-add-type"]', 'text_image');
await vendor.click('[data-testid="page-add"]');
await vendor.fill('[data-testid="page-0-heading"]', 'SMOKE-Who we are');
await vendor.fill('[data-testid="page-0-body"]', ABOUT);
await vendor.click('[data-testid="save-page-sections"]');
await settle(vendor, '[data-testid="flash-success"]');
await vendor.frameLocator('[data-testid="preview-frame"]').locator('[data-testid="page-title"]').waitFor({ timeout: 20000 }).catch(() => {});
const pagePreview = await frameText(vendor);
check('the page saves and its preview shows the text section', pagePreview.includes('About us') && pagePreview.includes(ABOUT), pagePreview.slice(0, 160));

await vendor.click('[data-testid="tab-collections"]');
await vendor.fill('[data-testid="collection-name"]', 'Starter kit');
await vendor.check(`[data-testid="collection-product-${BOOK}"]`);
await vendor.check(`[data-testid="collection-product-${PUZZLE}"]`);
await vendor.click('[data-testid="save-collection"]');
await settle(vendor, '[data-testid="collection-row-starter-kit"]');
check('a Starter kit collection of two products is made at /starter-kit', (await count(vendor, '[data-testid="collection-row-starter-kit"]')) === 1 && (await vendor.locator('[data-testid="collection-row-starter-kit"]').innerText()).includes('2 items'), (await vendor.locator('[data-testid="collection-row-starter-kit"]').innerText().catch(() => '')).replace(/\s+/g, ' '));

await vendor.click('[data-testid="tab-home"]');
await addSection('collection');
await vendor.locator('[data-testid="home-4-collection"] option').nth(1).waitFor({ timeout: 10000 }).catch(() => {});
await vendor.selectOption('[data-testid="home-4-collection"]', { index: 1 });
await vendor.click('[data-testid="save-sections"]');
await settle(vendor, '[data-testid="flash-success"]');
check('a Collection section points at it', (await count(vendor, '[data-testid^="home-row-"]')) === 5);

// ------------------------------------------------------------ 4. publish

const guest = await newPage('guest');
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('customers still see the plain page', (await count(guest, '[data-testid="storefront-sections"]')) === 0);

await vendor.fill('[data-testid="version-note"]', 'Sections');
await vendor.click('[data-testid="publish"]');
await settle(vendor, '[data-testid="flash-success"]');
check('the owner publishes', (await text(vendor)).includes('Published'), (await text(vendor)).slice(0, 120));

await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
const home = await text(guest);
check('a guest sees the hero with its button, the menu and the SEO title', home.includes(HERO) && (await count(guest, `[data-section-type="hero"] a[href*="/shop/products/${BOOK}"]`)) === 1 && (await count(guest, '[data-testid="storefront-nav"]')) === 1 && (await guest.title()).startsWith(SEO_TITLE), home.slice(0, 160));
check('the featured products, the FAQ, the video and the collection', (await count(guest, `[data-section-type="featured_products"] [data-product="${PUZZLE}"]`)) === 1 && home.includes(FAQ_Q) && (await count(guest, '[data-testid="video-embed"][src*="youtube-nocookie.com/embed/dQw4w9WgXcQ"]')) === 1 && (await count(guest, '[data-section-type="collection"] a[href$="/shop/fitrah/starter-kit"]')) === 1);

await guest.goto(`${BASE}/en/shop/fitrah/p/about-us`, { waitUntil: 'networkidle' });
const about = await text(guest);
check('the About page is live at /p/about-us, inside the storefront', about.includes('SMOKE-Who we are') && about.includes(ABOUT) && (await count(guest, '[data-testid="storefront-head"]')) === 1 && (await count(guest, '[data-testid="preview-banner"]')) === 0, about.slice(0, 160));

await guest.goto(`${BASE}/en/shop/fitrah/starter-kit`, { waitUntil: 'networkidle' });
check('the collection page lists its two products', (await count(guest, '[data-testid="collection-title"]')) === 1 && (await count(guest, `[data-testid="shop-grid"] [data-product="${BOOK}"]`)) === 1 && (await count(guest, `[data-testid="shop-grid"] [data-product="${PUZZLE}"]`)) === 1 && (await count(guest, '[data-testid="shop-grid"] [data-product]')) === 2, `${await count(guest, '[data-testid="shop-grid"] [data-product]')} products`);

await guest.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
const jsonLd = await guest.locator('script[type="application/ld+json"]').allInnerTexts();
check('the product page carries structured data with the price', jsonLd.some((s) => s.includes('"@type":"Product"') && s.includes('"price":"85.00"')), jsonLd.map((s) => s.slice(0, 80)).join(' | '));

// ------------------------------------------------------------ 5. the office moderates

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await office.locator('[data-testid="vendor-row-fitrah"] button').click();
await settle(office, '[data-testid="moderation-fitrah"]');
check('the office opens Fitrah and sees the storefront is published', (await office.locator('[data-testid="moderation-fitrah"] [data-testid="moderation-state"]').innerText()).includes('published'), (await office.locator('[data-testid="moderation-fitrah"] [data-testid="moderation-state"]').innerText().catch(() => '')).slice(0, 80));

await office.fill('[data-testid="moderation-fitrah"] [data-testid="moderation-note-input"]', 'SMOKE-Taken down pending the video licence.');
await office.click('[data-testid="moderation-fitrah"] [data-testid="take-down"]');
await settle(office, '[data-testid="lift-hold"]');
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('taking the storefront down: a guest sees the plain page again', (await count(guest, '[data-testid="storefront"]')) === 0 && (await text(guest)).includes('iman.noor.ihsan'));
await vendor.reload({ waitUntil: 'networkidle' });
check('and the designer says so', (await count(vendor, '[data-testid="moderation-held"]')) === 1 && (await vendor.locator('[data-testid="publish"]').isDisabled()));

await office.click('[data-testid="moderation-fitrah"] [data-testid="lift-hold"]');
await settle(office, '[data-testid="take-down"]');
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('lifting the hold brings the published storefront back at once', (await count(guest, '[data-testid="storefront"]')) === 1 && (await text(guest)).includes(HERO));

await office.check('[data-testid="moderation-fitrah"] [data-testid="lock-video"]');
await office.click('[data-testid="moderation-fitrah"] [data-testid="save-locks"]');
await settle(office);
await vendor.reload({ waitUntil: 'networkidle' });
check('locking Video for Fitrah: the designer names the locked type', (await count(vendor, '[data-testid="locked-types"]')) === 1 && (await vendor.locator('[data-testid="locked-types"]').innerText()).includes('Video'));
await vendor.click('[data-testid="save-sections"]');
await settle(vendor);
check('and refuses to save a draft that still has one', (await text(vendor)).includes('locked the “Video” section'), (await text(vendor)).slice(0, 160));

await finish();
