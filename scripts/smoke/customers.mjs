/**
 * Can the office see a Bookstore customer, tag them, keep a note with a
 * follow-up, and tick it off? (COMMERCE_PARITY_PLAN P7c.)
 *
 *   1. The student buys a tracing book from Fitrah (so is a customer).
 *   2. The office finds them on /admin/bookshop/customers by phone, with
 *      their orders and what they spent, and opens their page.
 *   3. The office tags them "school, VIP" (saved as lower case) and adds a
 *      note with today's follow-up; the note shows as due.
 *   4. "Follow-ups due" lists them; the tag filter finds them; ticking the
 *      follow-up off takes them out of the due list.
 *
 * `SmokeMarkerSeeder` (vendorCycle, smsOffers) plants the products and
 * clears the student's tags and notes.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/customers.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const BOOK = 'smoke-arabic-letters-tracing-book';
const NOTE = `SMOKE-Wants the Grade 3 set in January (${Date.now()})`;

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


// ------------------------------------------------------------ 1. a customer

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
await student.fill('[data-testid="quantity"]', '1');
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
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
await student.check('[data-testid="pay-wallet"]');
await submit(student, '[data-testid="place-order"]');
const number = (await student.locator('[data-testid="checkout-orders"] [data-order]').first().getAttribute('data-order').catch(() => '')) ?? '';
check('the student buys a book', number !== '', number);

// ------------------------------------------------------------ 2. the office finds them

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop/customers`, { waitUntil: 'networkidle' });
await office.fill('[data-testid="customers-search"]', number);
await office.click('[data-testid="customers-filter"]');
await settle(office);
const row = office.locator('[data-testid^="customer-"]').filter({ has: office.locator('a') }).first();
const rowText = (await row.innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the office finds the student by an order number, with orders and spend', (await office.locator('[data-testid="customers"] tbody tr').count()) === 1 && /MVR \d/.test(rowText), rowText.slice(0, 160));
await Promise.all([office.waitForURL(/\/admin\/bookshop\/customers\/\d+/), row.locator('a').first().click()]);
await settle(office, '[data-testid="customer-summary"]');
check('and opens their page with the order on it', (await text(office)).includes(number));

// ------------------------------------------------------------ 3. tags and a note

await office.fill('[data-testid="customer-tags"]', 'School, VIP');
await office.click('[data-testid="customer-save-tags"]');
await settle(office, '[data-testid="flash-success"]');
check('the office tags them (saved as lower case)', (await office.locator('[data-testid="customer-tags"]').inputValue()) === 'school, vip');
await office.fill('[data-testid="customer-note-body"]', NOTE);
await office.fill('[data-testid="customer-note-follow-up"]', new Date(Date.now() + 5 * 3600000).toISOString().slice(0, 10));
await office.click('[data-testid="customer-add-note"]');
await settle(office, '[data-testid^="customer-note-"][data-due="1"]');
const note = office.locator('[data-testid^="customer-note-"][data-due]').filter({ hasText: NOTE }).first();
check('and adds a note whose follow-up is due today', (await note.getAttribute('data-due').catch(() => '')) === '1');
const customerUrl = office.url();

// ------------------------------------------------------------ 4. follow-ups

await office.goto(`${BASE}/en/admin/bookshop/customers?follow_ups=1&tag=vip`, { waitUntil: 'networkidle' });
const due = office.locator('[data-testid^="customer-due-"]');
check('"Follow-ups due" with the VIP tag lists them', (await due.count()) >= 1 && (await office.locator('[data-testid="customers"] tbody tr').filter({ hasText: 'vip' }).count()) >= 1);
const customerId = customerUrl.match(/customers\/(\d+)/)?.[1];
await office.goto(customerUrl, { waitUntil: 'networkidle' });
await office.locator('[data-testid^="customer-note-done-"]').first().click();
await settle(office, '[data-testid="flash-success"]');
await office.goto(`${BASE}/en/admin/bookshop/customers?follow_ups=1`, { waitUntil: 'networkidle' });
check('ticking it off takes them out of the due list', (await office.locator(`[data-testid="customer-${customerId}"]`).count()) === 0);

await finish();
