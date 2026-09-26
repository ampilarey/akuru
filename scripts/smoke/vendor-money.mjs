/**
 * Does a vendor see what it is owed, and is it paid?
 * (BOOKSHOP_PLAN slice B6: money to vendors.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` leaves Fitrah one paid, delivered
 * order from three weeks ago — two tracing books at MVR 85 plus MVR 30
 * delivery, Akuru's 10% on the goods only — whose earning has matured.
 *
 * Fitrah's owner:
 *   1. opens Money from the portal: the earning is listed as available, the
 *      balance she may ask for is MVR 183.00 (170 + 30 − 17), and Request
 *      payout waits for bank details;
 *   2. enters (fake, staging-only) bank details; the number shows masked;
 *   3. requests the payout: MVR 183.00 requested, the button held.
 * The office:
 *   4. sees the request with the account to pay, records the transfer
 *      reference and marks it paid;
 *   5. issues the commission invoices for the order's month: ACI-…-FIT for
 *      MVR 17.00, readable as a printable page.
 * Fitrah's owner:
 *   6. sees the payout paid with its reference, MVR 183.00 paid out, and the
 *      invoice under Commission invoices, on its own printable page.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/vendor-money.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const BALANCE = '183.00';
const COMMISSION = '17.00';
const ACCOUNT = '7730000999999';
const REFERENCE = 'SMOKE-TRF-0001';

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
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 } });
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

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const count = (page, selector) => page.locator(selector).count();
const inner = async (page, selector) => (await page.locator(selector).first().innerText().catch(() => '')).replace(/\s+/g, ' ');

// ------------------------------------------------------------ 1. the money page

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-money"]');
}
await vendor.click('[data-testid="open-money"]');
await settle(vendor, '[data-testid="money-heading"]');
check('the portal opens Money', /\/vendor\/money$/.test(vendor.url()) && (await inner(vendor, '[data-testid="money-heading"]')).includes('Fitrah'), vendor.url().replace(BASE, ''));
check('the three-week-old order is listed as an available earning', (await count(vendor, '[data-earning-status="available"]')) === 1 && (await inner(vendor, '[data-earning-status="available"]')).includes(COMMISSION), await inner(vendor, '[data-earning-status="available"]'));
check(`the balance she may ask for is MVR ${BALANCE}, and Request payout waits for bank details`, (await inner(vendor, '[data-testid="requestable"]')).includes(BALANCE) && (await vendor.locator('[data-testid="request-payout"]').isDisabled()), await inner(vendor, '[data-testid="requestable"]'));

// ------------------------------------------------------------ 2. bank details

await vendor.fill('[data-testid="bank-name"]', 'Bank of Maldives');
await vendor.fill('[data-testid="account-name"]', 'SMOKE Fitrah Trading');
await vendor.fill('[data-testid="account-number"]', ACCOUNT);
await vendor.click('[data-testid="save-bank"]');
await settle(vendor, '[data-testid="bank-summary"]');
check('bank details save and show masked to the last four digits', (await inner(vendor, '[data-testid="bank-summary"]')).includes('9999') && !(await inner(vendor, '[data-testid="bank-summary"]')).includes(ACCOUNT), await inner(vendor, '[data-testid="bank-summary"]'));

// ------------------------------------------------------------ 3. the request

check('Request payout is now offered', !(await vendor.locator('[data-testid="request-payout"]').isDisabled()));
await vendor.click('[data-testid="request-payout"]');
// The bank save's flash is still on the page, so wait for the figure itself, not the flash.
await vendor.waitForFunction((b) => document.querySelector('[data-testid="stat-requested"]')?.innerText.includes(b), BALANCE, { timeout: 20000 }).catch(() => {});
await settle(vendor);
check(`the payout of MVR ${BALANCE} is requested and the button held`, (await inner(vendor, '[data-testid="stat-requested"]')).includes(BALANCE) && (await inner(vendor, '[data-testid="requestable"]')).includes('0.00') && (await vendor.locator('[data-testid="request-payout"]').isDisabled()), await inner(vendor, '[data-testid="flash-success"]'));

// ------------------------------------------------------------ 4. the office pays

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="payout-requests"]');
const requestRow = office.locator('[data-testid^="payout-request-"]').first();
const requestId = ((await requestRow.getAttribute('data-testid').catch(() => '')) || '').replace('payout-request-', '');
check('the office sees the request with the account to pay', requestId !== '' && (await requestRow.innerText()).includes(ACCOUNT) && (await requestRow.innerText()).includes(BALANCE), (await requestRow.innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 120));
await office.fill(`[data-testid="payout-reference-${requestId}"]`, REFERENCE);
await office.click(`[data-testid="payout-paid-${requestId}"]`);
await settle(office, `[data-testid="payout-done-${requestId}"]`);
check('recording the transfer moves it to the payout history as paid', (await inner(office, `[data-testid="payout-done-${requestId}"]`)).includes(REFERENCE) && (await count(office, '[data-testid^="payout-request-"]')) === 0);

// ------------------------------------------------------------ 5. the commission invoice

const paidOn = (await inner(vendor, '[data-earning-status="paid"], [data-earning-status="available"]')).match(/(\d{4})-(\d{2})-\d{2}/);
const month = paidOn ? `${paidOn[1]}-${paidOn[2]}` : new Date(Date.now() - 21 * 86400000).toISOString().slice(0, 7);
await office.fill('[data-testid="invoice-month"]', month);
await office.click('[data-testid="issue-invoices"]');
await settle(office, '[data-testid="office-invoices"]');
const invoiceNumber = `ACI-${month.replace('-', '')}-FIT`;
check(`the office issues ${invoiceNumber} for MVR ${COMMISSION}`, (await count(office, `[data-testid="office-invoice-${invoiceNumber}"]`)) === 1 && (await inner(office, `[data-testid="office-invoice-${invoiceNumber}"]`)).includes(COMMISSION), await inner(office, `[data-testid="office-invoice-${invoiceNumber}"]`));
const officeInvoiceHref = await office.locator(`[data-testid="office-invoice-${invoiceNumber}"] a`).getAttribute('href').catch(() => null);
if (officeInvoiceHref) {
    await office.goto(officeInvoiceHref.startsWith('http') ? officeInvoiceHref : `${BASE}${officeInvoiceHref}`, { waitUntil: 'networkidle' });
}
check('and reads it on a printable page', (await count(office, '[data-testid="commission-invoice"]')) === 1 && (await inner(office, '[data-testid="invoice-number"]')) === invoiceNumber && (await inner(office, '[data-testid="invoice-totals"]')).includes(COMMISSION));
check('the tax report shows the month', (await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' }), await count(office, `[data-testid="tax-${month}"]`)) === 1);

// ------------------------------------------------------------ 6. the vendor sees the outcome

await vendor.reload({ waitUntil: 'networkidle' });
await settle(vendor, '[data-testid="money-heading"]');
check(`MVR ${BALANCE} shows as paid out`, (await inner(vendor, '[data-testid="stat-paid"]')).includes(BALANCE), await inner(vendor, '[data-testid="stat-paid"]'));
await vendor.click('[data-testid="tab-payouts"]');
check('the payout is listed paid with its reference', (await count(vendor, '[data-payout-status="paid"]')) === 1 && (await inner(vendor, '[data-payout-status="paid"]')).includes(REFERENCE));
await vendor.click('[data-testid="tab-invoices"]');
check('the commission invoice is under Commission invoices', (await count(vendor, `[data-testid="invoice-${invoiceNumber}"]`)) === 1);
const vendorInvoiceHref = await vendor.locator(`[data-testid="open-invoice-${invoiceNumber}"]`).getAttribute('href').catch(() => null);
if (vendorInvoiceHref) {
    await vendor.goto(vendorInvoiceHref.startsWith('http') ? vendorInvoiceHref : `${BASE}${vendorInvoiceHref}`, { waitUntil: 'networkidle' });
}
check('and opens on its own printable page for the shop', (await count(vendor, '[data-testid="commission-invoice"]')) === 1 && (await inner(vendor, '[data-testid="invoice-number"]')) === invoiceNumber);
await vendor.goto(`${BASE}/en/vendor/money`, { waitUntil: 'networkidle' });
await vendor.click('[data-testid="tab-statements"]');
check('the statement for the month shows the commission and the payout', (await count(vendor, `[data-testid="statement-${month}"]`)) === 1 && (await inner(vendor, `[data-testid="statement-${month}"]`)).includes(COMMISSION) && (await inner(vendor, `[data-testid="statement-${month}"]`)).includes(invoiceNumber));

await finish();
