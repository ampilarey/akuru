/**
 * Can a shop add its own CSS, safely, with the office's say-so?
 * (BOOKSHOP_PLAN slice B10c, ADR-039.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` resets Fitrah's storefront and CSS.
 *
 * Fitrah's owner:
 *   1. publishes the storefront, then tries CSS with url() — refused, with
 *      the reason;
 *   2. sends `h1 { letter-spacing: .3em } .sf-band { border-bottom: 6px
 *      solid #b45309 }` — waiting for the office; the preview has it,
 *      confined under .storefront;
 * A guest:
 *   3. does not see it on Fitrah's page yet;
 * The office:
 *   4. sees it waiting, with the CSS and a preview link, and approves;
 * A guest:
 *   5. now sees it on Fitrah's page — the band's border is drawn;
 * The office:
 *   6. takes it down with a note; the guest's page is plain again; the
 *      owner sees why.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/css.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const CSS = 'h1 { letter-spacing: .3em }\n.sf-band { border-bottom: 6px solid #b45309 }';

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

// ------------------------------------------------------------ 1. publish; a refused CSS

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-designer"]');
}
await vendor.goto(`${BASE}/en/vendor/storefront`, { waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="custom-css"]');
await vendor.click('[data-testid="save-draft"]');
await settle(vendor, '[data-testid="flash-success"]');
await vendor.fill('[data-testid="version-note"]', 'CSS walk');
await vendor.click('[data-testid="publish"]', { timeout: 20000 });
await settle(vendor, '[data-testid="flash-success"]');
await vendor.fill('[data-testid="css-input"]', ".a { background: url(https://example.test/x.png) }");
await vendor.click('[data-testid="css-send"]');
await vendor.waitForSelector('[data-testid="custom-css"] [role="alert"]', { timeout: 20000 }).catch(() => {});
check('CSS with url() is refused, with the reason', (await inner(vendor, '[data-testid="custom-css"] [role="alert"]')).includes('url() and image-set() are not allowed'), await inner(vendor, '[data-testid="custom-css"] [role="alert"]'));

// ------------------------------------------------------------ 2. sent; the preview has it

await vendor.fill('[data-testid="css-input"]', CSS.replace('\\n', '\n'));
await vendor.click('[data-testid="css-send"]');
await vendor.waitForSelector('[data-testid="css-status"][data-status="pending"]', { timeout: 20000 }).catch(() => {});
check('sent, it waits for the office', (await vendor.locator('[data-testid="css-status"]').getAttribute('data-status')) === 'pending');
const preview = await (await vendor.request.get(`${BASE}/en/vendor/storefront/preview`)).text();
check('the preview has it, confined under .storefront', preview.includes('.storefront h1 { letter-spacing: .3em; }') && preview.includes('.storefront .sf-band { border-bottom: 6px solid #b45309; }'));

// ------------------------------------------------------------ 3. not public yet

const guestContext = await browser.newContext();
await guestContext.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const guest = await guestContext.newPage();
guest.on('response', (r) => { if (r.status() >= 500) problems.push(`guest: HTTP ${r.status()} ${r.url()}`); });
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('a guest does not see it yet', (await count(guest, 'style[data-testid="shop-custom-css"]')) === 0);

// ------------------------------------------------------------ 4. the office approves

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-css"]');
check('the office sees it waiting, with the CSS and a preview link',
    (await office.locator('[data-testid="css-fitrah"]').getAttribute('data-status')) === 'pending'
    && (await inner(office, '[data-testid="css-pending-fitrah"]')).includes('letter-spacing: .3em')
    && (await count(office, '[data-testid="css-preview-fitrah"]')) === 1);
await office.click('[data-testid="css-approve-fitrah"]');
await office.waitForSelector('[data-testid="css-fitrah"][data-status="approved"]', { timeout: 20000 }).catch(() => {});
check('and approves it', (await office.locator('[data-testid="css-fitrah"]').getAttribute('data-status')) === 'approved');

// ------------------------------------------------------------ 5. live

await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
const border = await guest.locator('.storefront .sf-band').first().evaluate((el) => getComputedStyle(el).borderBottomWidth).catch(() => '');
check('a guest now sees it — the band has its border', (await count(guest, 'style[data-testid="shop-custom-css"]')) === 1 && border === '6px', border);

// ------------------------------------------------------------ 6. taken down

await office.fill('[data-testid="css-note-fitrah"]', 'SMOKE: the border hides the menu');
await office.click('[data-testid="css-take-down-fitrah"]');
await settle(office, '[data-testid="flash-success"]');
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('taken down, the page is plain again', (await count(guest, 'style[data-testid="shop-custom-css"]')) === 0);
await vendor.reload({ waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="css-status"]');
check('and the owner sees why', (await inner(vendor, '[data-testid="css-status"]')).includes('the border hides the menu'), await inner(vendor, '[data-testid="css-status"]'));

await finish();
