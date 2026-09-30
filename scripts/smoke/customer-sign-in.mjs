/**
 * Can a customer sign in on the phone number, the Bake & Grill way?
 * (COMMERCE_PARITY_PLAN P1.)
 *
 * A guest with a book in the cart:
 *
 *   1. follows "Sign in with your mobile number" from the cart, gives a new
 *      number, gets a code (read from the log, where SMS goes when live SMS
 *      is off), enters it with a name;
 *   2. is asked to set a password first, sets one, and lands on the checkout
 *      with the book;
 *   3. signs out, comes back with the same number and is asked for the
 *      password — not a code — and is in;
 *
 * and the student's number, which has a password, goes to the password box.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/customer-sign-in.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM, SMOKE_LOG (the Laravel log).
 */
import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOG = process.env.SMOKE_LOG ?? 'storage/logs/laravel.log';
const BOOK = 'smoke-arabic-letters-tracing-book';

const browser = await chromium.launch(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {});
const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}
for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => { check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200)); await finish(); });
}

async function newPage(label) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${label}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`${label}: HTTP ${r.status()} ${r.url()}`); });

    return page;
}
const submit = async (page, selector) => {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click(selector)]);
};
/** The newest code the log sender wrote for this number. */
const codeFor = (phone) => {
    const lines = fs.readFileSync(LOG, 'utf8').split('\n').filter((l) => l.includes('SMS log sender') && l.includes(phone));
    const match = (lines.at(-1) ?? '').match(/verification code is: (\d{6})/);

    return match ? match[1] : '';
};

const number = `7${String(Date.now()).slice(-6)}`;
const password = 'walk-password-1';

// ------------------------------------------------------------ 1. a new number, by code
const customer = await newPage('customer');
await customer.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
await submit(customer, '[data-testid="add-to-cart"]');
await submit(customer, '[data-testid="cart-sign-in-link"]');
check('the cart\'s "Sign in with your mobile number" opens the phone sign-in', (await customer.locator('[data-testid="phone-step"]').count()) === 1, customer.url().replace(BASE, ''));

await customer.fill('[data-testid="phone-number"]', number);
await submit(customer, '[data-testid="phone-continue"]');
check('a new number is sent a code, and asked for a name', (await customer.locator('[data-testid="code-step"]').count()) === 1 && (await customer.locator('[data-testid="phone-name"]').count()) === 1);
const code = codeFor(`+960${number}`);
check('the code reached the SMS log', /^\d{6}$/.test(code), code || 'none');

await customer.fill('[data-testid="phone-code"]', '000000');
await submit(customer, '[data-testid="phone-verify"]');
check('a wrong code is refused', (await customer.locator('[data-testid="phone-error"]').count()) === 1);

await customer.fill('[data-testid="phone-code"]', code);
await customer.fill('[data-testid="phone-name"]', 'Walk Customer');
await submit(customer, '[data-testid="phone-verify"]');
await customer.locator('[data-testid="set-password-form"]').waitFor({ timeout: 20000 }).catch(() => {});
check('the right code signs in and asks for a password first', /account\/set-password/.test(customer.url()) && (await customer.locator('[data-testid="set-password-form"]').count()) === 1, customer.url().replace(BASE, ''));

// ------------------------------------------------------------ 2. the password, then the checkout
await customer.fill('input[name="password"]', password);
await customer.fill('input[name="password_confirmation"]', password);
await Promise.all([customer.waitForURL(/\/shop\/checkout/, { timeout: 30000 }).catch(() => {}), customer.click('[data-testid="set-password-form"] button[type=submit]')]);
await customer.waitForLoadState('networkidle');
check('with a password set, the checkout opens with the book', /\/shop\/checkout$/.test(customer.url()) && (await customer.locator('main').innerText()).includes('Arabic Letters Tracing Book'), customer.url().replace(BASE, ''));

// ------------------------------------------------------------ 3. next time: the password, no code
await customer.context().clearCookies();
await customer.goto(`${BASE}/en/sign-in`, { waitUntil: 'networkidle' });
await customer.fill('[data-testid="phone-number"]', number);
await submit(customer, '[data-testid="phone-continue"]');
check('the same number next time is asked for its password, not a code', (await customer.locator('[data-testid="password-step"]').count()) === 1 && (await customer.locator('[data-testid="code-step"]').count()) === 0);
await customer.fill('[data-testid="phone-password"]', password);
await submit(customer, '[data-testid="phone-sign-in"]');
check('and the password signs them in', !/sign-in|login/.test(customer.url()), customer.url().replace(BASE, ''));

// ------------------------------------------------------------ the student's number has a password
const student = await newPage('student');
await student.goto(`${BASE}/en/sign-in`, { waitUntil: 'networkidle' });
await student.fill('[data-testid="phone-number"]', '7000001');
await submit(student, '[data-testid="phone-continue"]');
check('a number whose account has a password goes to the password box', (await student.locator('[data-testid="password-step"]').count()) === 1);

await finish();
