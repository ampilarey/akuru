/**
 * Can a shop pick a whole look from a gallery, and offer its own for other
 * shops? (BOOKSHOP_PLAN slice B10d, the theme gallery, ADR-039.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` resets Fitrah's storefront and removes
 * the looks it offered.
 *
 * Fitrah's owner:
 *   1. sees the four starter looks in the designer's gallery;
 *   2. uses "Classic bookshop" — the draft takes its fonts and colours, its
 *      CSS waits for the publish (the preview has both);
 *   3. publishes — a guest sees Merriweather and the theme's CSS;
 *   4. offers the published look as "SMOKE-Fitrah look" — it waits;
 * The office:
 *   5. sees it waiting with its CSS and publishes it — it joins the gallery;
 *   6. withdraws it — gone from the gallery, Fitrah's page unchanged.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/gallery.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const LOOK = 'SMOKE-Fitrah look';

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

// ------------------------------------------------------------ 1. the gallery

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-designer"]');
}
await vendor.goto(`${BASE}/en/vendor/storefront`, { waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="theme-gallery"]');
check('the gallery shows the four starter looks', (await count(vendor, '[data-testid^="gallery-apply-"]')) === 4);

// ------------------------------------------------------------ 2. use one

vendor.once('dialog', (dialog) => dialog.accept());
await Promise.all([vendor.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), vendor.click('[data-testid="gallery-apply-classic-bookshop"]')]);
await settle(vendor, '[data-testid="css-status"]');
check('its CSS waits for the publish', (await vendor.locator('[data-testid="css-status"]').getAttribute('data-status')) === 'theme', await inner(vendor, '[data-testid="css-status"]'));
const preview = await (await vendor.request.get(`${BASE}/en/vendor/storefront/preview`)).text();
check('the preview has the look and its CSS', preview.includes('Merriweather') && preview.includes('letter-spacing: .02em'));

// ------------------------------------------------------------ 3. publish

await vendor.fill('[data-testid="version-note"]', 'Classic bookshop');
await vendor.click('[data-testid="publish"]', { timeout: 20000 });
await settle(vendor, '[data-testid="flash-success"]');
const guestContext = await browser.newContext();
await guestContext.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const guest = await guestContext.newPage();
guest.on('response', (r) => { if (r.status() >= 500) problems.push(`guest: HTTP ${r.status()} ${r.url()}`); });
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
const html = await guest.content();
check('a guest sees the look and its CSS', html.includes('Merriweather') && (await count(guest, 'style[data-testid="shop-custom-css"]')) === 1);

// ------------------------------------------------------------ 4. offer the look

await vendor.reload({ waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="gallery-offer-name"]');
await vendor.fill('[data-testid="gallery-offer-name"]', LOOK);
await vendor.click('[data-testid="gallery-offer-send"]');
await vendor.waitForSelector('[data-testid="gallery-offered"]', { timeout: 20000 }).catch(() => {});
check('the offered look waits for the office', (await vendor.locator('[data-testid="gallery-offered"]').getAttribute('data-status')) === 'submitted');

// ------------------------------------------------------------ 5. the office publishes it

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-themes"]');
const waiting = office.locator('[data-testid^="theme-waiting-"]', { hasText: LOOK });
check('the office sees it with its CSS', (await waiting.count()) === 1 && (await waiting.innerText()).includes('letter-spacing: .02em'));
await waiting.locator('[data-testid^="theme-publish-"]').click();
await office.waitForSelector('[data-testid="office-themes"] li:has-text("SMOKE-Fitrah look") [data-testid^="theme-withdraw-"]', { timeout: 20000 }).catch(() => {});
check('and publishes it to the gallery', (await office.locator('[data-testid^="theme-"]', { hasText: LOOK }).locator('[data-testid^="theme-withdraw-"]').count()) === 1);
await vendor.reload({ waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="theme-gallery"]');
check('it is in the shops\' gallery', (await count(vendor, '[data-testid^="gallery-apply-"]')) === 5);

// ------------------------------------------------------------ 6. withdrawn

await office.locator('[data-testid^="theme-"]', { hasText: LOOK }).locator('[data-testid^="theme-withdraw-"]').click();
await settle(office, '[data-testid="flash-success"]');
await vendor.reload({ waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="theme-gallery"]');
await guest.reload({ waitUntil: 'networkidle' });
check('withdrawn: gone from the gallery, Fitrah\'s page unchanged', (await count(vendor, '[data-testid^="gallery-apply-"]')) === 4 && (await count(guest, 'style[data-testid="shop-custom-css"]')) === 1);

await finish();
