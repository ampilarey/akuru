/**
 * Can the office open a credit account for a school, the school pay on
 * account, and the office record what it paid? (COMMERCE_PARITY_PLAN P8c.)
 *
 *   1. The office opens an account for the student (standing in for a
 *      school): limit MVR 1000, 30 days.
 *   2. The student's checkout offers *Pay on account* with what is
 *      available; the order is paid at once.
 *   3. My orders shows the account: owed and available.
 *   4. The office sees what is owed, records a payment, and the statement
 *      shows the charge, the payment and the balance.
 *
 * `SmokeMarkerSeeder` (vendorCycle) plants the products and clears the
 * walk's account, its ledger and its orders.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/credit.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
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

async function signIn(email) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
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
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');

    return page;
}

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
// Inertia posts are XHR: wait for what the step expects, not for the network.
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const money = (value) => Number(String(value ?? '').replace(/[^0-9.]/g, ''));
// A Blade form post is a real navigation. "networkidle" straight after the
// click can resolve before it starts, and the walk's next goto then cuts the
// post off, so wait for the navigation itself.
const submit = async (page, selector) => {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click(selector)]);
};


// ------------------------------------------------------------ 1. the office opens an account

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop/credit`, { waitUntil: 'networkidle' });
await office.fill('[data-testid="credit-identifier"]', STUDENT);
await office.fill('[data-testid="credit-organisation"]', 'SMOKE Majeediyya School');
await office.fill('[data-testid="credit-limit"]', '1000');
await office.fill('[data-testid="credit-terms"]', '30');
await office.click('[data-testid="credit-open"]');
await settle(office, '[data-testid="flash-success"]');
const account = office.locator('[data-testid^="credit-account-"]').filter({ hasText: 'SMOKE Majeediyya School' }).first();
const accountId = ((await account.getAttribute('data-testid').catch(() => '')) || '').replace('credit-account-', '');
check('the office opens a credit account for the school', Boolean(accountId) && (await account.getAttribute('data-owed')) === '0.00', accountId);

// ------------------------------------------------------------ 2. pay on account

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
await student.fill('[data-testid="quantity"]', '2');
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
const hint = (await student.locator('[data-testid="credit-available"]').innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the checkout offers pay on account with what is available', (await student.locator('[data-testid="pay-credit"]').count()) === 1 && hint.includes('1000.00'), hint);
if ((await student.locator('[data-testid="saved-address"]').count()) > 0) {
    await student.locator('[data-testid="saved-address"]').first().check();
} else {
    await student.fill('[data-testid="recipient-name"]', 'Smoke Customer');
    await student.fill('[data-testid="phone"]', '7700000');
    await student.fill('[data-testid="atoll"]', 'K');
    await student.fill('[data-testid="island"]', 'Malé');
    await student.fill('[data-testid="street"]', 'M. Smoke Villa');
}
await student.locator('[data-testid="delivery-fitrah"] input[data-delivery-kind="collect_vendor"]').first().check();
await student.check('[data-testid="pay-credit"]');
await submit(student, '[data-testid="place-order"]');
check('the order is paid on account at once', (await student.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid', student.url().replace(BASE, ''));

// ------------------------------------------------------------ 3. My orders shows the account

await student.goto(`${BASE}/en/my-orders`, { waitUntil: 'networkidle' });
const panel = (await student.locator('[data-testid="credit-account"]').innerText().catch(() => '')).replace(/\s+/g, ' ');
check('My orders shows what is owed and what is left', /owed MVR 170\.00/.test(panel) && /available MVR 830\.00/.test(panel), panel);

// ------------------------------------------------------------ 4. the office records a payment

await office.goto(`${BASE}/en/admin/bookshop/credit`, { waitUntil: 'networkidle' });
check('the office sees MVR 170.00 owed', (await office.locator(`[data-testid="credit-account-${accountId}"]`).getAttribute('data-owed')) === '170.00');
await office.fill(`[data-testid="credit-pay-amount-${accountId}"]`, '100');
await office.fill(`[data-testid="credit-pay-ref-${accountId}"]`, 'SMOKE-BML-TRX');
await office.click(`[data-testid="credit-pay-${accountId}"]`);
await settle(office, '[data-testid="flash-success"]');
check('and records a payment: MVR 70.00 still owed', (await office.locator(`[data-testid="credit-account-${accountId}"]`).getAttribute('data-owed')) === '70.00');
await office.click(`[data-testid="credit-show-${accountId}"]`);
await settle(office, `[data-testid="credit-statement-${accountId}"]`);
const statement = (await office.locator(`[data-testid="credit-statement-${accountId}"]`).innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the statement shows the order, the payment and the balance', statement.includes('170.00') && statement.includes('SMOKE-BML-TRX') && statement.includes('70.00'), statement.slice(0, 200));

await finish();
