/**
 * Does a fee become an invoice a family can see, and cash a receipt they can open?
 *
 * S4 is the money the school itself bills: fee structure → invoices →
 * issue → the guardian's portal → a payment plan → cash at the office →
 * receipt → arrears, collections, reconciliation and their CSVs. Every
 * screen on the path had tests, the S4 audit found the engine sound, and
 * the DoD's *"real cycle"* line had never been walked by a script — the
 * STATUS rows still said UNVERIFIED, "a load, not a data check" and
 * "partial" (§2, S4.1 / S4.4 / S4.6). This is the walk.
 *
 * Two logins, one loop:
 *
 *   1. the admin builds `SMOKE-Fees` for the walk's own class, generates
 *      the drafts, issues them, sees the invoice in arrears, splits it into
 *      two installments, records the first as cash and the second as a
 *      transfer, and reads collections, reconciliation and four CSVs;
 *   2. the parent sees the invoice with its balance, then the next
 *      installment, then nothing owed — and opens both receipts.
 *
 * ## The class is the walk's own
 *
 * One active structure per class per year, and generation is idempotent
 * per student, structure and period — both rules working as designed, and
 * both would stop a second run cold. So `SmokeMarkerSeeder` enrols the
 * child in `SMOKE-Class A`, which no other structure covers, and removes
 * everything the walk made last time. The seeder explains.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/fees.mjs
 *
 * ## What it does not cover
 *
 * The BML half of the DoD line. The gateway cannot be exercised anywhere
 * until `OWNER_ACTIONS` item 2 sets a webhook secret, which is exactly why
 * the cash path gets this much attention (see money.mjs for the same
 * reasoning on course payments). "Pay now" is asserted present, not pressed.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PARENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const CLASS_LABEL = 'SMOKE-Class A';
const STRUCTURE = 'SMOKE-Fees';
const AMOUNT = '123.45';
// The seeder plants an approved 7.77% scholarship on the child (S4.5's
// sweep marker), and adjustments apply at generation as their own discount
// line — so the invoice is not the structure's amount, and the walk checks
// that it is exactly the discounted one.
const TOTAL = (Number(AMOUNT) * (1 - 0.0777)).toFixed(2);
const SECOND = '100.00';
const FIRST = (Number(TOTAL) - Number(SECOND)).toFixed(2);
const today = new Date().toISOString().slice(0, 10);
const inAMonth = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);

// See sweep.mjs: without this the run stalls on fonts and Chromium's own
// background services rather than on anything this application does.
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
    const context = await browser.newContext();
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

// `main`, not `body` — see review.mjs. The nav is ninety links.
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

// Wait, bounded, for the screen to say something — then read it once. See
// review.mjs: Inertia re-renders after the redirect, and reading straight
// after the click sees the old page.
async function settles(page, needle, ms = 5000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await text(page)).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

const rowText = async (page, needle) => {
    const row = page.locator('tr', { hasText: needle }).first();

    return (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : '';
};

// A CSV export, fetched with the page's own session.
async function csv(page, path, needle) {
    const response = await page.request.get(`${BASE}/en${path}`);
    const body = await response.text();

    return [response.status() === 200 && body.includes(needle), `HTTP ${response.status()}, ${body.split('\n').length} lines`];
}

// ---------------------------------------------------------------- the parent

const parent = await signIn(PARENT);
check('the parent signs in', !parent.url().includes('/login'), parent.url());

await parent.goto(`${BASE}/en/portal/invoices`, { waitUntil: 'networkidle' });
const CHILD = ((await parent.locator('main select option').first().textContent()) ?? '').trim();
check('the portal names a child', CHILD !== '', CHILD || 'no child option on /portal/invoices');

// ------------------------------------------------------------------ the admin

const admin = await signIn(ADMIN);
check('the admin signs in', !admin.url().includes('/login'), admin.url());

// Is there a clean class to walk? See exams.mjs: say it plainly rather than
// dying thirty seconds later inside a Playwright click.
await admin.goto(`${BASE}/en/finance/fee-structures`, { waitUntil: 'networkidle' });
if ((await admin.locator('tr', { hasText: STRUCTURE }).count()) > 0) {
    check(
        'there is a clean SMOKE-Class to walk',
        false,
        `${STRUCTURE} already exists — left over from an earlier run. `
            + 'Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder',
    );
    await finish();
}
const classBox = admin.locator('label', { hasText: CLASS_LABEL }).locator('input[type=checkbox]');
check(`the structure form offers ${CLASS_LABEL} (seeded by SmokeMarkerSeeder)`, (await classBox.count()) > 0);

// ---------------------------------------------------------- 1. the structure

const structureForm = admin.locator('form', { hasText: 'Create structure' });
await structureForm.locator('input[placeholder="Structure name"]').fill(STRUCTURE);
await structureForm.locator('select').nth(0).selectOption('class');
await structureForm.locator('select').nth(1).selectOption('active');
if (await classBox.count()) {
    await classBox.check();
}
await structureForm.locator('input[placeholder="Amount"]').first().fill(AMOUNT);
await structureForm.locator('input[placeholder="Due day"]').first().fill('');
await structureForm.locator('button:has-text("Create structure")').click();
check('an active structure is created for the class', await settles(admin, 'Fee structure saved.'), (await text(admin)).slice(0, 200));
check('it is listed as active', /\bactive\b/.test(await rowText(admin, STRUCTURE)), await rowText(admin, STRUCTURE));

// ----------------------------------------------------------- 2. the drafts

await admin.goto(`${BASE}/en/finance/invoices`, { waitUntil: 'networkidle' });
const generate = admin.locator('form', { hasText: 'Generate drafts' });
await generate.locator('select').first().selectOption({ label: `${STRUCTURE} (active)` });
await generate.locator('input[type=date]').nth(0).fill(today);
await generate.locator('input[type=date]').nth(1).fill(today);
await generate.locator('button:has-text("Generate drafts")').click();
check('one draft is generated for the class', await settles(admin, '1 draft invoices generated.'), (await text(admin)).slice(0, 160));

// The invoice is found by the child's name and the walk's amount, never by a
// hard-coded number: numbering is `INV-<structure>-<student>-<period>`, and
// every one of those varies per host.
const draftRow = await rowText(admin, CHILD);
const INVOICE = draftRow.match(/INV-[\w-]+/)?.[0] ?? '';
check('the draft is the structure\'s amount less the seeded 7.77% scholarship', INVOICE !== '' && draftRow.includes(TOTAL) && /\bdraft\b/.test(draftRow), draftRow);

await admin.locator('button:has-text("Issue drafts")').click();
check('the drafts are issued', await settles(admin, '1 invoices issued.'), (await text(admin)).slice(0, 160));
check('the invoice is now sent', /\bsent\b/.test(await rowText(admin, INVOICE)), await rowText(admin, INVOICE));

await admin.goto(`${BASE}/en/finance/arrears`, { waitUntil: 'networkidle' });
const arrears = await rowText(admin, INVOICE);
check('arrears lists it as current, with the guardian', arrears.includes(TOTAL) && /current/.test(arrears), arrears || (await text(admin)).slice(0, 160));
{
    const [ok, detail] = await csv(admin, '/finance/arrears/export', INVOICE);
    check('the arrears CSV exports', ok, detail);
}

// ---------------------------------------------------- 3. the family sees it

await parent.goto(`${BASE}/en/portal/invoices`, { waitUntil: 'networkidle' });
const owed = await rowText(parent, INVOICE);
check('the parent sees the invoice and its balance', owed.includes(TOTAL), owed || (await text(parent)).slice(0, 160));
check('with a Pay now button (BML itself is not pressed)', (await parent.locator('tr', { hasText: INVOICE }).locator('button:has-text("Pay now")').count()) > 0);

// ---------------------------------------------------------- 4. a plan

await admin.goto(`${BASE}/en/finance/payment-plans`, { waitUntil: 'networkidle' });
const planForm = admin.locator('form', { hasText: 'Create plan' });
await planForm.locator('select').selectOption({ label: `${INVOICE} — ${TOTAL} due` });
await planForm.locator('input[placeholder="Installment 1 amount"]').fill(FIRST);
await planForm.locator('input[type=date]').nth(0).fill(today);
await planForm.locator('input[placeholder="Installment 2 amount"]').fill(SECOND);
await planForm.locator('input[type=date]').nth(1).fill(inAMonth);
await planForm.locator('button:has-text("Create plan")').click();
check('a two-installment plan is created', await settles(admin, 'Payment plan created.'), (await text(admin)).slice(0, 160));
check('it starts at nothing paid', (await rowText(admin, INVOICE)).includes(`0.00 / ${TOTAL}`), await rowText(admin, INVOICE));

// ---------------------------------------------------- 5. cash at the office

async function receive(amount, method) {
    await admin.goto(`${BASE}/en/finance/receipts/manual`, { waitUntil: 'networkidle' });
    const form = admin.locator('form', { hasText: 'Record cash / transfer' });
    await form.locator('select').first().selectOption({ label: `${INVOICE} — ${await balanceOf()}` });
    await form.locator('input[placeholder="Amount"]').fill(amount);
    await form.locator('select').nth(1).selectOption(method);
    await form.locator('button:has-text("Record cash / transfer")').click();

    return settles(admin, 'Receipt recorded.');
}

async function balanceOf() {
    const option = admin.locator('option', { hasText: INVOICE }).first();
    const label = (await option.count()) ? (await option.textContent()) ?? '' : '';

    return label.split('—')[1]?.trim() ?? '';
}

check('the first installment is received in cash', await receive(FIRST, 'cash'), (await text(admin)).slice(0, 160));

await admin.goto(`${BASE}/en/finance/payment-plans`, { waitUntil: 'networkidle' });
check('the plan shows the first installment paid', (await rowText(admin, INVOICE)).includes(`${FIRST} / ${TOTAL}`), await rowText(admin, INVOICE));

await parent.goto(`${BASE}/en/portal/invoices`, { waitUntil: 'networkidle' });
const partly = await rowText(parent, INVOICE);
check('the parent sees the balance fall and the next installment', partly.includes(SECOND) && /next 100\.00/.test(partly), partly);

const firstReceipt = parent.locator('tr', { hasText: INVOICE }).locator('a:has-text("Receipt")').first();
check('with a receipt to open', (await firstReceipt.count()) > 0);
if (await firstReceipt.count()) {
    const href = await firstReceipt.getAttribute('href');
    const opened = await parent.goto(new URL(href, BASE).href, { waitUntil: 'domcontentloaded' });
    const body = await parent.content();
    check('and the receipt names the invoice and the cash', opened.status() === 200 && body.includes(INVOICE) && body.includes(FIRST), `HTTP ${opened.status()}, ${body.length} bytes`);
}

check('the second installment is received by transfer', await receive(SECOND, 'transfer'), (await text(admin)).slice(0, 160));

await admin.goto(`${BASE}/en/finance/payment-plans`, { waitUntil: 'networkidle' });
check('the plan completes', /\bcompleted\b/.test(await rowText(admin, INVOICE)), await rowText(admin, INVOICE));

await parent.goto(`${BASE}/en/portal/invoices`, { waitUntil: 'networkidle' });
const settled = await rowText(parent, INVOICE);
check('the parent owes nothing and has nothing left to pay', settled.includes('0.00') && /\bcompleted\b/.test(settled) && !settled.includes('Pay now'), settled);
check('with two receipts', (await parent.locator('tr', { hasText: INVOICE }).locator('a:has-text("Receipt")').count()) === 2);

// ------------------------------------------------- 6. the office's reports

await admin.goto(`${BASE}/en/finance/collections`, { waitUntil: 'networkidle' });
const collected = (await text(admin)).match(new RegExp(`${TOTAL} ${TOTAL}`));
check('collections shows the amount billed and collected', Boolean(collected), (await text(admin)).slice(0, 200));

await admin.goto(`${BASE}/en/finance/reconciliation`, { waitUntil: 'networkidle' });
const recon = await text(admin);
check('reconciliation lists both receipts against the invoice', (recon.match(new RegExp(INVOICE, 'g')) || []).length >= 2 && recon.includes('cash') && recon.includes('transfer'), recon.slice(0, 200));

for (const [name, path, needle] of [
    ['invoices', '/finance/invoices/export', INVOICE],
    ['collections', '/finance/collections/export', TOTAL],
    ['reconciliation', '/finance/reconciliation/export', INVOICE],
]) {
    const [ok, detail] = await csv(admin, path, needle);
    check(`the ${name} CSV exports`, ok, detail);
}

await finish();
