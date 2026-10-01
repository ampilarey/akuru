/**
 * Can the office send an SMS offer to the customers who asked for one, and
 * can a customer stop it? (COMMERCE_PARITY_PLAN P7b.)
 *
 *   1. The student buys a tracing book from Fitrah, ticking "Text me Akuru
 *      Bookstore offers" at checkout.
 *   2. The office opens SMS offers: the student counts among Fitrah's
 *      opted-in buyers; the message's length, messages each and cost show
 *      as it is typed; the office confirms the number and the price, and
 *      the offer goes (the log sender writes it to sms_receipts).
 *   3. The message as sent ends with its own stop link; the student opens
 *      it, sees the masked number, and stops the offers with the button.
 *   4. The office's page counts one fewer opted-in buyer.
 *
 * `SmokeMarkerSeeder` (vendorCycle, smsOffers) plants the products, resets
 * the student's opt-in and drops the last run's smoke campaigns.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/campaigns.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM. The sent message is read from sms_receipts through local
 * tinker, so only against the local server.
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const BOOK = 'smoke-arabic-letters-tracing-book';
const MESSAGE = `SMOKE-Eid offer ${Date.now()}: 20% off tracing books this week.`;

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


// ------------------------------------------------------------ 1. opt in at checkout

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
await student.fill('[data-testid="quantity"]', '1');
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
const optBox = student.locator('[data-testid="sms-offers"]');
check('the checkout asks, unticked, whether to text offers', (await optBox.count()) === 1 && !(await optBox.isChecked()));
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
await optBox.check();
await submit(student, '[data-testid="place-order"]');
const number = (await student.locator('[data-testid="checkout-orders"] [data-order]').first().getAttribute('data-order').catch(() => '')) ?? '';
check('the student buys with the box ticked', number !== '', number);

// ------------------------------------------------------------ 2. the office sends

const office = await signIn(ADMIN);
office.on('dialog', (dialog) => dialog.accept());
await office.goto(`${BASE}/en/admin/bookshop/campaigns`, { waitUntil: 'networkidle' });
const shopOption = office.locator('[data-testid="campaign-shop"] option').filter({ hasText: 'Fitrah' }).first();
const fitrahId = await shopOption.getAttribute('value').catch(() => null);
const before = Number(((await shopOption.innerText().catch(() => '')).match(/\((\d+)\)/) ?? [])[1] ?? -1);
check('the office sees Fitrah\'s opted-in buyers', before >= 1, `${before}`);
await office.selectOption('[data-testid="campaign-shop"]', fitrahId ?? '');
await office.fill('[data-testid="campaign-message"]', MESSAGE);
const cost = (await office.locator('[data-testid="campaign-cost"]').innerText()).replace(/\s+/g, ' ');
check('the length and the cost show before sending', /1 message/.test(await office.locator('[data-testid="campaign-length"]').innerText()) && cost.includes(`${before} people`) && /MVR \d+\.\d{2}$/.test(cost), cost);
await office.click('[data-testid="campaign-send"]');
await settle(office, '[data-testid="flash-success"]');
// The messages go from the queue; locally, drain it (production's worker does this).
execSync(`cd ${process.cwd()} && php artisan queue:work --stop-when-empty --queue=default --tries=1`, { encoding: 'utf8' });
await office.reload({ waitUntil: 'networkidle' });
const sent = office.locator('[data-testid^="campaign-"][data-status]').filter({ hasText: MESSAGE }).first();
check('the office sends it and it shows as sent', (await sent.getAttribute('data-status').catch(() => '')) === 'sent', (await office.locator('[data-testid="flash-success"]').innerText().catch(() => '')));

// ------------------------------------------------------------ 3. the student stops it

const receipt = JSON.parse(execSync(`cd ${process.cwd()} && php artisan tinker --execute="echo json_encode(\\Illuminate\\Support\\Facades\\DB::table('sms_receipts')->where('phone','7700000')->where('body','like','%${MESSAGE.slice(0, 30)}%')->orderByDesc('id')->value('body'));"`, { encoding: 'utf8' }).trim() || 'null');
const stopUrl = (String(receipt ?? '').match(/https?:\/\/\S+\/shop\/sms\/stop\/[a-z0-9]{12}/) ?? [])[0] ?? null;
check('the message as sent ends with its own stop link', Boolean(stopUrl) && String(receipt).startsWith(MESSAGE), String(receipt ?? '').slice(-80));
if (stopUrl) {
    await student.goto(stopUrl.replace(/^https?:\/\/[^/]+/, BASE), { waitUntil: 'networkidle' });
}
const stopPage = await text(student);
check('the stop page shows the masked number and asks first', (await student.locator('[data-testid="sms-stop-confirm"]').count()) === 1 && stopPage.includes('000') && !stopPage.includes('7700000'));
await submit(student, '[data-testid="sms-stop-confirm"]');
check('the student stops the offers', (await student.locator('[data-testid="sms-stopped"]').count()) === 1);

// ------------------------------------------------------------ 4. one fewer

await office.goto(`${BASE}/en/admin/bookshop/campaigns`, { waitUntil: 'networkidle' });
const after = Number(((await office.locator('[data-testid="campaign-shop"] option').filter({ hasText: 'Fitrah' }).first().innerText().catch(() => '')).match(/\((\d+)\)/) ?? [])[1] ?? -1);
check('the office counts one fewer opted-in buyer', after === before - 1, `${before} → ${after}`);

await finish();
