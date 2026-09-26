/**
 * Can a shop collect newsletter sign-ups with consent, and can a reader
 * leave? Is a forgotten cart remembered? (BOOKSHOP_PLAN slice B9c.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` resets Fitrah's storefront and list,
 * and leaves the parent a cart from a day and a half ago with the
 * reminder already run (the hourly job, done once by the seeder).
 *
 * Fitrah's owner:
 *   1. adds a Newsletter section to the shop page and publishes it;
 * A guest:
 *   2. signs up — the form wants the consent box — and is thanked;
 * Fitrah's owner:
 *   3. sees one subscriber, and the CSV has the address and its own
 *      unsubscribe link;
 * The guest:
 *   4. opens that link — nothing happens until they press the button —
 *      and leaves; the owner's count goes back to none;
 * The parent:
 *   5. finds "Still thinking it over?" among their notifications, and the
 *      puzzle still in the cart it points to.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/newsletter.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_APPLICANT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PARENT = process.env.SMOKE_APPLICANT ?? 'parent@akuru.edu.mv';
const HEADING = 'SMOKE-News from Fitrah';
const READER = 'smoke-reader@example.test';

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

// ------------------------------------------------------------ 1. the section

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-sections"]');
}
await vendor.goto(`${BASE}/en/vendor/storefront/sections`, { waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="sections-heading"]');
await vendor.click('[data-testid="tab-home"]').catch(() => {});
await vendor.selectOption('[data-testid="home-add-type"]', 'newsletter');
await vendor.click('[data-testid="home-add"]');
const idx = (await count(vendor, '[data-testid^="home-row-"]')) - 1;
await vendor.fill(`[data-testid="home-${idx}-heading"]`, HEADING);
await vendor.click('[data-testid="save-sections"]');
await settle(vendor, '[data-testid="flash-success"]');
await vendor.fill('[data-testid="version-note"]', 'Newsletter');
await vendor.click('[data-testid="publish"]');
await settle(vendor, '[data-testid="flash-success"]');
check('Fitrah adds a Newsletter section and publishes', (await inner(vendor, 'main')).includes('Published'));

// ------------------------------------------------------------ 2. a guest signs up

const guestContext = await browser.newContext();
await guestContext.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const guest = await guestContext.newPage();
guest.on('response', (r) => { if (r.status() >= 500) problems.push(`guest: HTTP ${r.status()} ${r.url()}`); });
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('the shop page shows the sign-up', (await inner(guest, '[data-testid="newsletter-signup"]')).includes(HEADING));
await guest.fill('[data-testid="newsletter-email"]', READER);
await guest.fill('[data-testid="newsletter-name"]', 'Smoke Reader');
await guest.click('[data-testid="newsletter-submit"]');
await guest.waitForTimeout(500);
check('without the consent box nothing is sent', (await count(guest, '[data-testid="newsletter-thanks"]')) === 0);
await guest.check('[data-testid="newsletter-consent"]');
await Promise.all([guest.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), guest.click('[data-testid="newsletter-submit"]')]);
check('with it, the reader is thanked', (await count(guest, '[data-testid="newsletter-thanks"]')) === 1);

// ------------------------------------------------------------ 3. the shop's list

await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
check('Fitrah sees one subscriber', (await inner(vendor, '[data-testid="newsletter-count"]')).includes('1'), await inner(vendor, '[data-testid="newsletter-count"]'));
const csv = await (await vendor.request.get(`${BASE}/en/vendor/newsletter/export`)).text();
const link = (csv.match(/https?:\/\/\S+\/shop\/newsletter\/unsubscribe\/[A-Za-z0-9]{48}/) || [''])[0];
check('the CSV has the address and its unsubscribe link', csv.includes(READER) && link !== '', link.replace(BASE, ''));

// ------------------------------------------------------------ 4. leaving

await guest.goto(link.replace(/^https?:\/\/[^/]+/, BASE), { waitUntil: 'networkidle' });
check('the link asks first, naming the address and the shop', (await inner(guest, '[data-testid="newsletter-leave"]')).includes(READER) && (await count(guest, '[data-testid="newsletter-leave-confirm"]')) === 1);
await Promise.all([guest.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), guest.click('[data-testid="newsletter-leave-confirm"]')]);
check('pressing the button unsubscribes', (await count(guest, '[data-testid="newsletter-left"]')) === 1);
await vendor.reload({ waitUntil: 'networkidle' });
check('and the owner\'s count is back to none', (await inner(vendor, '[data-testid="newsletter-count"]')).includes('0'), await inner(vendor, '[data-testid="newsletter-count"]'));

// ------------------------------------------------------------ 5. the forgotten cart

const parent = await signIn(PARENT);
await parent.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the parent is reminded about the cart', (await inner(parent, 'main')).includes('Still thinking it over?'), (await inner(parent, 'main')).slice(0, 160));
await parent.goto(`${BASE}/en/shop/cart`, { waitUntil: 'networkidle' });
check('and the puzzle is still in it', (await inner(parent, 'main')).includes('Wooden Alphabet Puzzle'));

await finish();
