/**
 * Can someone apply to open a shop, and the office open it for them?
 * (BOOKSHOP_PLAN slice B9a: public vendor onboarding, apply → approve.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` leaves the parent with no shop and no
 * application, and the form open.
 *
 * The parent:
 *   1. finds "Open a shop" on the shop home and fills in the application,
 *      accepting the Vendor Agreement — it waits for the office;
 * The office:
 *   2. sees it at the top of the Bookstore page, approves it with an 8%
 *      commission and the code SWA;
 * The parent:
 *   3. is told, finds it approved, and opens their new shop's portal —
 *      owner, agreement already accepted, ready to add products;
 * The office:
 *   4. closes the form: the shop home drops "Open a shop", and the form
 *      says applications are closed.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/apply.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_APPLICANT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const PARENT = process.env.SMOKE_APPLICANT ?? 'parent@akuru.edu.mv';
const SHOP = 'SMOKE-Walk Applicant Shop';
const SLUG = 'smoke-walk-applicant-shop';

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

// ------------------------------------------------------------ 1. the application

const parent = await signIn(PARENT);
await parent.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('the shop home offers "Open a shop"', (await count(parent, '[data-testid="open-a-shop-link"]')) === 1);
await Promise.all([parent.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), parent.click('[data-testid="open-a-shop-link"]')]);
await settle(parent, '[data-testid="apply-form"]');
check('it opens the application form', /\/vendor\/apply$/.test(parent.url()) && (await count(parent, '[data-testid="apply-form"]')) === 1, parent.url().replace(BASE, ''));
await parent.fill('[data-testid="apply-shop-name"]', SHOP);
await parent.fill('[data-testid="apply-island"]', 'Hulhumalé');
await parent.fill('[data-testid="apply-email"]', 'smoke-applicant@example.test');
await parent.fill('[data-testid="apply-phone"]', '7770000');
await parent.fill('[data-testid="apply-what"]', 'Exercise books, pencils and Dhivehi alphabet charts for primary pupils.');
await parent.click('[data-testid="apply-submit"]');
await settle(parent, '[data-testid="flash-success"]');
check('without the agreement it is refused', (await count(parent, '[data-testid="flash-success"]')) === 0 && (await count(parent, '[data-testid="application-status"]')) === 0);
await parent.check('[data-testid="apply-agreement"]');
await parent.click('[data-testid="apply-submit"]');
await settle(parent, '[data-testid="application-status"]');
check('sent: the application waits for the office, and the form is gone', (await parent.locator('[data-testid="application-status"]').getAttribute('data-status').catch(() => '')) === 'pending' && (await count(parent, '[data-testid="apply-form"]')) === 0);

// ------------------------------------------------------------ 2. the office approves

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-applications"]');
const row = office.locator('[data-testid^="application-"][data-status="pending"]').filter({ hasText: SHOP }).first();
const appId = ((await row.getAttribute('data-testid').catch(() => '')) || '').replace('application-', '');
check('the office sees it, with what they sell and how to reach them', appId !== '' && (await row.innerText()).includes('Hulhumalé') && (await row.innerText()).includes('7770000'), (await row.innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 160));
await office.fill(`[data-testid="application-rate-${appId}"]`, '8');
await office.fill(`[data-testid="application-code-${appId}"]`, 'SWA');
await office.click(`[data-testid="application-approve-${appId}"]`);
await settle(office, '[data-testid="flash-success"]');
check('approved: the shop is open and the owner told', (await inner(office, '[data-testid="flash-success"]')).includes(SHOP) && (await office.locator(`[data-testid="application-${appId}"]`).getAttribute('data-status')) === 'approved', await inner(office, '[data-testid="flash-success"]'));
check('the new shop is in the vendor list', (await inner(office, 'body')).includes(SHOP));

// ------------------------------------------------------------ 3. the new owner

await parent.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the parent is told their shop is open', (await inner(parent, 'main')).includes(SHOP));
await parent.goto(`${BASE}/en/vendor/apply`, { waitUntil: 'networkidle' });
check('the application shows approved, with the way into the portal', (await parent.locator('[data-testid="application-status"]').getAttribute('data-status').catch(() => '')) === 'approved' && (await count(parent, '[data-testid="open-portal"]')) === 1);
await Promise.all([parent.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), parent.click('[data-testid="open-portal"]')]);
await settle(parent, '[data-testid="vendor-name"]');
check('the portal is their shop, as owner, the agreement already accepted', (await inner(parent, '[data-testid="vendor-name"]')) === SHOP && (await count(parent, '[data-testid="accept-agreement"]')) === 0 && (await count(parent, '[data-testid="new-product"]')) === 1, await inner(parent, 'header'));
const shopPage = await parent.request.get(`${BASE}/en/shop/${SLUG}`);
check('and its public page is live', shopPage.status() === 200, `HTTP ${shopPage.status()}`);

// ------------------------------------------------------------ 4. closed

await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await office.click('[data-testid="toggle-applications"]');
await settle(office, '[data-testid="flash-success"]');
check('the office closes the form', (await inner(office, '[data-testid="applications-state"]')).includes('closed'));
const guest = await browser.newContext();
await guest.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const visitor = await guest.newPage();
await visitor.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('the shop home no longer offers "Open a shop"', (await count(visitor, '[data-testid="open-a-shop"]')) === 0);
await parent.goto(`${BASE}/en/vendor/apply`, { waitUntil: 'networkidle' });
check('and the form says applications are closed', (await count(parent, '[data-testid="applications-closed"]')) === 1);

await finish();
