/**
 * Can a shop use its own domain, and can visitors from abroad see prices in
 * dollars? (BOOKSHOP_PLAN slice B9f.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` clears Fitrah's domain and the dollar
 * switch.
 *
 * Fitrah's owner:
 *   1. enters "https://www.Smoke-Fitrah.test/" as the shop's domain; it is
 *      saved as www.smoke-fitrah.test, waiting for the office;
 * A request on that domain:
 *   2. is not answered as the shop while it waits;
 * The office:
 *   3. sees the request, checks where it points (it does not, here — the
 *      check still reports), and turns it on;
 * A request on that domain:
 *   4. now goes to Fitrah's page on the canonical site;
 * The owner:
 *   5. sees the domain is on;
 * The office:
 *   6. shows prices in dollars at 15.42; a guest sees "≈ USD" beside the
 *      tracing book's price and on the cards; the office turns it off and
 *      the dollars go.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/hosts.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const DOMAIN = 'www.smoke-fitrah.test';
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

// Asks the app for a page as if it came in on another host (the browser cannot resolve a made-up domain).
const onHost = async (page, host, path = '/') => {
    const response = await page.request.get(`${BASE}${path}`, { headers: { Host: host }, maxRedirects: 0, failOnStatusCode: false });
    return { status: response.status(), location: response.headers().location ?? '' };
};

// ------------------------------------------------------------ 1. the owner asks

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="own-domain"]');
}
await vendor.fill('[data-testid="host-input"]', 'https://www.Smoke-Fitrah.test/');
await vendor.click('[data-testid="host-save"]');
await settle(vendor, '[data-testid="host-status"]');
check('the domain is saved, waiting for the office', (await vendor.locator('[data-testid="host-status"]').getAttribute('data-status')) === 'requested' && (await inner(vendor, '[data-testid="host-status"]')).includes(DOMAIN), await inner(vendor, '[data-testid="host-status"]'));

// ------------------------------------------------------------ 2. not yet

const waiting = await onHost(vendor, DOMAIN, '/');
check('while it waits, the domain is not the shop', !waiting.location.includes('/shop/fitrah'), `${waiting.status} ${waiting.location}`);

// ------------------------------------------------------------ 3. the office

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-hosts"]');
check('the office sees the request', (await office.locator('[data-testid="host-fitrah"]').getAttribute('data-status')) === 'requested');
await office.click('[data-testid="host-check-fitrah"]');
await settle(office, '[data-testid="host-check"]');
check('the check reports where it points', (await inner(office, '[data-testid="host-check"]')).includes(DOMAIN), await inner(office, '[data-testid="host-check"]'));
await office.click('[data-testid="host-approve-fitrah"]');
await office.waitForSelector('[data-testid="host-fitrah"][data-status="active"]', { timeout: 20000 }).catch(() => {});
check('the office turns it on', (await office.locator('[data-testid="host-fitrah"]').getAttribute('data-status')) === 'active');

// ------------------------------------------------------------ 4. the domain answers

const on = await onHost(vendor, DOMAIN, '/');
check('the domain now opens Fitrah on the canonical site', on.status === 302 && on.location === `${BASE}/shop/fitrah`, `${on.status} ${on.location}`);
const deep = await onHost(vendor, DOMAIN, '/about?ref=card');
check('a path goes under the shop page', deep.location === `${BASE}/shop/fitrah/about?ref=card`, deep.location);

// ------------------------------------------------------------ 5. the owner sees it on

await vendor.reload({ waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="host-status"]');
check('the owner sees the domain is on', (await vendor.locator('[data-testid="host-status"]').getAttribute('data-status')) === 'active');

// ------------------------------------------------------------ 6. dollars

await office.fill('[data-testid="usd-rate"]', '15.42');
await office.check('[data-testid="usd-on"]');
await office.click('[data-testid="usd-save"]');
await settle(office, '[data-testid="flash-success"]');
const guestContext = await browser.newContext();
await guestContext.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const guest = await guestContext.newPage();
guest.on('response', (r) => { if (r.status() >= 500) problems.push(`guest: HTTP ${r.status()} ${r.url()}`); });
await guest.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
check('a guest sees the price in dollars as a guide', (await inner(guest, '[data-testid="product-usd"]')).includes('≈ USD 5.51'), await inner(guest, '[data-testid="product-usd"]'));
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('and on the cards', (await count(guest, '[data-testid="card-usd"]')) > 0);
await office.uncheck('[data-testid="usd-on"]');
await office.click('[data-testid="usd-save"]');
await settle(office, '[data-testid="flash-success"]');
await guest.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
check('turned off, the dollars go', (await count(guest, '[data-testid="product-usd"]')) === 0);

await finish();
