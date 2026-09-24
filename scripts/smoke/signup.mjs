/**
 * Does a sign-up sheet with a fee reach one class, wait for the parent, bill
 * the family, and close?
 *
 * E6 (`docs/EDUPAGE_FEATURES_PLAN.md`, Wave 2) shipped in three cuts — the
 * sheet and its results (E6a), the guardian's confirmation (E6b), the fee
 * raised as an ordinary invoice (E6c) — with forty tests and no walk. Its
 * acceptance line is one sentence, and this walks that sentence (E-track
 * audit D1, STATUS §5fq). Three logins:
 *
 *   1. the office builds a trip sign-up aimed at the pupil's class only, with
 *      a fee and a parent's confirmation required;
 *   2. the pupil finds it, answers, and is told it is waiting for a parent;
 *      the fee note says an invoice has been raised;
 *   3. the parent finds the answer waiting, sees what the child said, and
 *      confirms it from their own account; the invoice is on their Invoices
 *      page at the sheet's price, unpaid;
 *   4. the office reads the answer confirmed on the results table, downloads
 *      the CSV, and closes the sheet; the pupil now sees it closed with
 *      nothing to submit.
 *
 * `SmokeMarkerSeeder::signupCycle()` clears the sheet, the answer and the
 * invoice before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/signup.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_PARENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const TITLE = 'SMOKE-Trip';
const QUESTION = 'SMOKE-Q: coming on the trip?';
const FEE = '15.00';

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
    const context = await browser.newContext();
    // Sixty seconds, not thirty: staging behind Cloudflare stalled past thirty on two page loads in one run (STATUS §5fz).
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    // Read only a mounted page. On a real host the app's JavaScript can land
    // after the network goes idle, and a read at that moment sees an empty
    // `main` — a fifth of the first staging run's failures were that (STATUS
    // §5fz). Bounded, so a page with nothing in it still reads as empty.
    const mounted = async () => {
        const deadline = Date.now() + 4000;
        while (Date.now() < deadline) {
            const ready = await page.evaluate(() => {
                const main = document.querySelector('main');
                return !document.querySelector('#app') || (main !== null && main.innerText.trim().length > 0);
            }).catch(() => true);
            if (ready) {
                return;
            }
            await page.waitForTimeout(150);
        }
    };
    for (const method of ['goto', 'reload']) {
        const raw = page[method].bind(page);
        page[method] = async (...args) => {
            const response = await raw(...args);
            await mounted();
            return response;
        };
    }
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

async function settles(page, needle, ms = 6000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await text(page)).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

// ---------------------------------------------------------------- the pupil

const student = await signIn(STUDENT);
check('the pupil signs in', !student.url().includes('/login'), student.url());
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the pupil has a name', NAME.length > 0, NAME || 'no h2 on /portal/performance');

// ---------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());

// Which class the pupil is in, read off the directory rather than assumed.
await admin.goto(`${BASE}/en/people/students?search=${encodeURIComponent(NAME)}`, { waitUntil: 'networkidle' });
const CLASS = ((await admin.locator('tr', { hasText: NAME }).first().locator('td').nth(4).innerText().catch(() => '')) || '').trim();
check('the directory says which class the pupil is in', CLASS.length > 0, CLASS || `no class cell on the row for ${NAME}`);

await admin.goto(`${BASE}/en/forms`, { waitUntil: 'networkidle' });
if ((await text(admin)).includes(TITLE)) {
    check('there is a clean sheet to walk', false, `${TITLE} already exists — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

// 1. the sheet: one class, a fee, a parent's confirmation
await admin.click('button:has-text("New form")');
const build = admin.locator('form', { hasText: 'Save form' });
await build.locator('label', { hasText: 'Title' }).first().locator('input').fill(TITLE);
await build.locator('label', { hasText: 'Description' }).first().locator('textarea').fill('SMOKE-Trip: the museum, Sunday morning.');
await build.locator('input[placeholder="Question"]').first().fill(QUESTION);
await build.locator('select').first().selectOption('yes_no');
await build.locator('label', { hasText: 'Required' }).first().locator('input').check();
const classBox = build.locator('label', { hasText: CLASS }).locator('input[type="checkbox"]').first();
check('the builder offers the pupil\'s class as a target', (await classBox.count()) > 0, CLASS);
if (await classBox.count()) {
    await classBox.check();
}
await build.locator('label', { hasText: 'needs a parent to confirm' }).locator('input').check();
await build.locator('label', { hasText: 'Fee (MVR' }).locator('input').fill(FEE);
await build.locator('button:has-text("Save form")').click();
const listed = await settles(admin, TITLE);
const row = admin.locator('li', { hasText: TITLE }).first();
check('the sheet is saved, open, aimed at one class', listed && /open/i.test(await row.innerText().catch(() => '')) && /1 class/i.test(await row.innerText().catch(() => '')), (await row.innerText().catch(() => '')).replace(/\s+/g, ' ') || (await text(admin)).slice(0, 160));
const resultsHref = await row.locator('a').first().getAttribute('href').catch(() => null);

// ---------------------------------------------------------------- the pupil

// 2. answers, and is told to wait for a parent
await student.goto(`${BASE}/en/portal/forms`, { waitUntil: 'networkidle' });
const card = student.locator('li', { hasText: TITLE }).first();
check('the pupil finds the sheet, with its fee and question', (await card.count()) > 0 && (await card.innerText()).includes(`Fee: MVR ${FEE}`) && (await card.innerText()).includes(QUESTION), (await card.count()) ? (await card.innerText()).replace(/\s+/g, ' ').slice(0, 160) : (await text(student)).slice(0, 160));
if (await card.count()) {
    await card.locator('label', { hasText: /^\s*Yes\s*$/ }).locator('input[type="radio"]').check();
    await card.locator('button:has-text("Submit")').click();
}
check('the answer is taken and waits for a parent', await settles(student, 'Waiting for a parent to confirm'), (await student.locator('li', { hasText: TITLE }).first().innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 160));
check('the fee note says the invoice has been raised', (await student.locator('li', { hasText: TITLE }).first().innerText().catch(() => '')).includes('An invoice has been raised'), (await student.locator('li', { hasText: TITLE }).first().innerText().catch(() => '')).match(/An invoice[^.]*\./)?.[0] ?? '');

// ---------------------------------------------------------------- the parent

// 3. confirms from their own account, and is billed
const parent = await signIn(PARENT);
check('the parent signs in', !parent.url().includes('/login'), parent.url());
await parent.goto(`${BASE}/en/portal/forms`, { waitUntil: 'networkidle' });
const pending = parent.locator('section', { hasText: 'Waiting for you to confirm' }).locator('li', { hasText: TITLE }).first();
check('the parent finds the child\'s answer waiting, and can read it', (await pending.count()) > 0 && (await pending.innerText()).includes(NAME) && /\?: yes\b/.test(await pending.innerText()), (await pending.count()) ? (await pending.innerText()).replace(/\s+/g, ' ').slice(0, 160) : (await text(parent)).slice(0, 160));
if (await pending.count()) {
    await pending.locator('button:has-text("Confirm")').click();
    // The flash, not `networkidle` — which resolves at once on a page that is
    // already idle, so the next line reloaded the page before the confirmation
    // had been written (third staging run, STATUS §5fz).
    await settles(parent, 'Confirmed.');
}
await parent.goto(`${BASE}/en/portal/forms`, { waitUntil: 'networkidle' });
check('after confirming there is nothing left waiting', (await parent.locator('section', { hasText: 'Waiting for you to confirm' }).locator('li', { hasText: TITLE }).count()) === 0, (await text(parent)).slice(0, 120));

await parent.goto(`${BASE}/en/portal/invoices`, { waitUntil: 'networkidle' });
// Named for what it is for — the sheet's title — not only by its number.
const invoice = parent.locator('tr', { hasText: TITLE }).first();
check('the fee is an ordinary invoice on the family\'s Invoices page, saying what it is for', (await invoice.count()) > 0 && (await invoice.innerText()).includes(FEE) && /ADH-/.test(await invoice.innerText()), (await invoice.count()) ? (await invoice.innerText()).replace(/\s+/g, ' ').slice(0, 160) : (await text(parent)).slice(0, 160));

// ---------------------------------------------------------------- the office

// 4. reads it confirmed, exports, closes
await admin.goto(new URL(resultsHref ?? '/forms', BASE).href, { waitUntil: 'networkidle' });
const resultRow = admin.locator('tr', { hasText: NAME }).first();
check('the results table shows the answer, confirmed by a parent', (await resultRow.count()) > 0 && !(await resultRow.innerText()).includes('Not confirmed') && (await text(admin)).includes('1 confirmed by a parent'), (await resultRow.count()) ? (await resultRow.innerText()).replace(/\s+/g, ' ').slice(0, 160) : (await text(admin)).slice(0, 160));
const csv = await admin.request.get(new URL(`${resultsHref?.replace(/\/results$/, '')}/export`, BASE).href);
check('the results export as CSV', csv.status() === 200 && /csv/i.test(csv.headers()['content-type'] ?? '') && (await csv.text()).includes(NAME), `HTTP ${csv.status()} ${csv.headers()['content-type'] ?? ''}`);

await admin.click('button:has-text("Close sign-up now")');
// The flash, not `networkidle`: the close is a PUT that redirects to the
// list, and the fourth staging run re-opened the list before it had landed
// and read the sheet still open (STATUS §5fz).
await settles(admin, 'Form updated.');
await admin.goto(`${BASE}/en/forms`, { waitUntil: 'networkidle' });
check('the office closes the sheet', /closed/i.test(await admin.locator('li', { hasText: TITLE }).first().innerText().catch(() => '')), (await admin.locator('li', { hasText: TITLE }).first().innerText().catch(() => '')).replace(/\s+/g, ' '));

await student.goto(`${BASE}/en/portal/forms`, { waitUntil: 'networkidle' });
const closedCard = student.locator('li', { hasText: TITLE }).first();
check('the pupil sees it closed with nothing to submit', (await closedCard.count()) > 0 && (await closedCard.innerText()).includes('Closed') && (await closedCard.locator('button[type="submit"]').count()) === 0, (await closedCard.innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 120));

await finish();
