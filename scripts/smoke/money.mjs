/**
 * Can the school take money, and give it back?
 *
 * §1f, and the loop that matters most right now. **While
 * `BML_WEBHOOK_SECRET` is unset — the state of every environment
 * (`OWNER_ACTIONS` item 2) — a manual payment is the only way the school can
 * take money at all**, and a refund is the only way to return it.
 *
 *   1. an enrolment is waiting to be paid for,
 *   2. the office records cash received — and it activates **without BML**,
 *   3. the payment shows as confirmed on the payments screen,
 *   4. the office refunds it to the family's **wallet**,
 *   5. the enrolment is revoked and the money is in the wallet,
 *   6. the refunded filter finds it and the CSV carries the totals,
 *   7. an offering price override changes what the family is charged — and an
 *      override of **0** makes the course free rather than falling back to the
 *      course fee.
 *
 * ## Why steps 4–5 are one question and not two
 *
 * `RefundPaymentAction` is the only way a payment is refunded: it locks the
 * row, enforces the refundable remainder, **appends** a refund rather than
 * mutating the payment (rule 12 — ledgers are append-only), credits the wallet,
 * and fires `PaymentRefunded` *inside* the transaction so listeners revoke what
 * the money bought.
 *
 * That last part is the join this session has watched break over and over: two
 * halves that are each plausible alone. A refund that returns the money and
 * leaves the course open is a family being paid to keep it; a revoke that takes
 * the course away without the money is worse. So the walk asserts **both**, and
 * asserts the wallet went up by the amount refunded rather than merely that a
 * refund row exists.
 *
 * ## What it does not cover
 *
 * Partial refunds, discount release, gift cards, and the BML path itself. The
 * gateway cannot be exercised anywhere until item 2 is done, which is precisely
 * why the manual path is worth this much attention.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/money.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const COURSE = 'SMOKE-Payable-Course';
const AMOUNT = '250';
const RECEIPT = `SMOKE-Receipt ${new Date().toISOString().replace(/[^0-9]/g, '').slice(8, 14)}`;
const REASON = 'SMOKE-Refund: family withdrew before the course began.';
// Who the seeded payable enrolment belongs to. The payments screen identifies a
// payment by student, and the refund is supposed to land in *their* wallet.
const STUDENT = process.env.SMOKE_PAYER_NAME ?? 'Fatima Yoosuf';
const PAYER = process.env.SMOKE_PAYER ?? 'student@akuru.edu.mv';

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
    // These are Blade screens, so a confirm() dialog is a real dialog rather
    // than an Inertia prop. Both money buttons ask before acting, which is
    // correct and would otherwise hang the walk for ever.
    page.on('dialog', (dialog) => dialog.accept());
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

const body = async (page) => (await page.innerText('body')).replace(/\s+/g, ' ');

const staff = await signIn(STAFF);
check('the office signs in', !staff.url().includes('/login'), staff.url());

// The family's wallet **before** any of this. Balances accumulate across runs —
// two runs put 500 in this one — so looking for "250" somewhere on the page
// would pass whatever happened, the same trap the earnings card set. The delta
// is the assertion, and it is the same number however many times this has run.
const payerPage = await signIn(PAYER);

const walletBalance = async () => {
    await payerPage.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
    const match = (await body(payerPage)).match(/Balance MVR ([\d,]+\.\d\d)/);
    return match ? Number(match[1].replace(/,/g, '')) : null;
};

const walletBefore = await walletBalance();
check('the family\'s wallet balance is readable', walletBefore !== null, String(walletBefore));

// ------------------------------------- 1. an enrolment waiting to be paid for

await staff.goto(`${BASE}/en/admin/enrollments`, { waitUntil: 'networkidle' });
const list = await body(staff);

const enrolHref = (await staff.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || '')))
    .find((href) => /\/admin\/enrollments\/\d+$/.test(href));

check('the enrolment list names the payable course', list.includes(COURSE), list.slice(0, 200));
check('an enrolment is open to look at', Boolean(enrolHref), enrolHref ?? 'no /admin/enrollments/N link');

let paid = false;

if (enrolHref) {
    // The right enrolment, found by its course rather than by position — the
    // list carries every enrolment in the school.
    const rows = await staff.$$eval('tr', (trs) => trs.map((tr) => [
        tr.innerText.replace(/\s+/g, ' ').trim(),
        [...tr.querySelectorAll('a')].map((a) => a.getAttribute('href') || '').find((h) => /\/admin\/enrollments\/\d+$/.test(h)) || '',
    ]));
    const mine = rows.find(([label, href]) => label.includes(COURSE) && href);

    check('the payable enrolment has its own page', Boolean(mine), mine ? mine[1] : 'no row for ' + COURSE);

    if (mine) {
        await staff.goto(new URL(mine[1], BASE).href, { waitUntil: 'networkidle' });
        const page = await body(staff);

        // §1f's first line. The form only renders while the enrolment is
        // awaiting payment, so its presence is the precondition and its absence
        // means the fixture has already been paid.
        const form = staff.locator('form[action*="record-payment"]').first();
        const hasForm = (await form.count()) > 0;

        check(
            'the office is offered a way to record money received',
            hasForm,
            hasForm ? '' : 'no manual-payment form — already paid? re-seed: php artisan db:seed --class=SmokeMarkerSeeder',
        );

        if (hasForm) {
            await form.locator('input[name=amount]').fill(AMOUNT);
            await form.locator('input[name=note]').fill(RECEIPT);
            await form.locator('button[type=submit]').click();
            await staff.waitForLoadState('networkidle');

            const after = await body(staff);
            // Activation **without BML** is the whole point: no gateway is
            // configured anywhere, and the enrolment still opens.
            paid = /active/i.test(after) && !/pending\s*\/\s*pending/i.test(after);
            check('recording it activates the enrolment, with no gateway involved', paid, after.slice(0, 220));
        }
    }
}

// ------------------------------- 3. the payment is on the payments screen

await staff.goto(`${BASE}/en/admin/enrollments/payments`, { waitUntil: 'networkidle' });
const payments = await body(staff);

// The screen shows reference / payer / student / amount / status — **not** the
// course or the receipt note, so looking for either found nothing on a table
// that was perfectly correct. The student and the amount are what identify it
// there, and they are what the CSV carries too.
check(
    'the payment reaches the payments screen, confirmed',
    payments.includes(STUDENT) && payments.includes('250.00'),
    payments.slice(0, 220),
);

// ------------------------------------------ 4–5. refund it to the wallet

const refundForm = staff.locator('form[action*="/refund"]').first();
const canRefund = (await refundForm.count()) > 0;

check(
    'a confirmed payment can be refunded',
    canRefund,
    canRefund ? '' : 'no refund form — nothing confirmed and refundable on this screen',
);

if (canRefund) {
    // `details`/`summary` hides the form until opened; filling a hidden input
    // is fine, but the submit needs it visible.
    const summary = staff.locator('details summary').first();
    if (await summary.count()) {
        await summary.click();
        await staff.waitForTimeout(200);
    }

    await refundForm.locator('select[name=destination]').selectOption('wallet');
    await refundForm.locator('input[name=reason]').fill(REASON);
    await refundForm.locator('button[type=submit]').click();
    await staff.waitForLoadState('networkidle');

    const after = await body(staff);

    check('refunding it is accepted', /refunded/i.test(after), after.slice(0, 220));

    // The two consequences, which are the reason this walk exists.
    //
    // `RefundPaymentAction` fires `PaymentRefunded` inside the transaction so
    // listeners revoke what the money bought. A refund that returns the money
    // and leaves the course open is a family being paid to keep it; a revoke
    // without the money is worse. Both halves are plausible alone, which is
    // exactly the shape that has been breaking all session — so both are
    // asserted, from the screens rather than from the columns.
    if (enrolHref) {
        await staff.goto(new URL(enrolHref, BASE).href, { waitUntil: 'networkidle' });
        const enrolment = await body(staff);
        check(
            'and the enrolment it bought is revoked',
            /cancelled|canceled|refunded/i.test(enrolment),
            enrolment.slice(enrolment.indexOf('Enrollment #'), enrolment.indexOf('Enrollment #') + 220),
        );
    }

    const walletAfter = await walletBalance();
    const credited = walletAfter === null || walletBefore === null
        ? null
        : Math.round((walletAfter - walletBefore) * 100) / 100;

    check(
        'and the money is in the family\'s wallet',
        credited === Number(AMOUNT),
        `balance ${walletBefore} → ${walletAfter} (expected +${AMOUNT})`,
    );

    // -------------------------- 6. the refunded filter, and the CSV totals

    await staff.goto(`${BASE}/en/admin/enrollments/payments?status=refunded`, { waitUntil: 'networkidle' });
    const filtered = await body(staff);

    check(
        'the refunded filter finds it',
        !filtered.includes('No payments found.'),
        filtered.includes('No payments found.') ? 'the refunded filter is empty after a refund' : '',
    );

    // Fetched rather than navigated to: it is a download, and `page.goto`
    // throws "Download is starting" instead of returning a response.
    const csv = await staff.request.get(`${BASE}/en/admin/enrollments/payments/export`);
    const csvBody = csv.ok() ? await csv.text() : '';

    check(
        'the payments CSV downloads, carrying the refund',
        csv.ok() && /refund/i.test(csvBody),
        csv.ok() ? csvBody.split('\n')[0].slice(0, 200) : `HTTP ${csv.status()}`,
    );
}

// ------------------------------------------- 7. the offering price override
//
// §1f's last line, and the only one of its four that was still by hand.
//
// The chain has three links and each is separately plausible: the admin form
// stores `price_override` (SaveCourseOfferingAction), the public listing reads
// it through `DefaultSelfLearningOfferingAction`, and checkout reads it again
// through the same action. The one that matters is that `0` is an *override*
// and not an absent one — `null` means "charge the course fee" and `0.00` means
// "this offering is free", and a single `?:` anywhere along that chain collapses
// the two into the same thing and starts charging 250 for something advertised
// as free.
//
// Run after the refund on purpose: the seeded student is enrolled on this course
// until it is refunded, and an enrolled row shows *Open* instead of a price.

const OFFERING = 'SMOKE-Payable-Offering';
const OVERRIDE = '99';

// The catalog row for this course, as the family sees it. Scoped to the row
// carrying the course rather than searched for across the page — "250" appears
// in several places on a listing, and a page-wide match would pass on somebody
// else's money.
const catalogRow = async () => {
    await payerPage.goto(`${BASE}/en/learn/catalog`, { waitUntil: 'networkidle' });
    const rows = await payerPage.$$eval('tr', (trs) => trs.map((tr) => tr.innerText.replace(/\s+/g, ' ').trim()));
    return rows.find((row) => row.includes(COURSE)) ?? '';
};

const priceOf = (row) => {
    const match = row.match(/MVR ([\d.]+)/);
    return match ? Number(match[1]) : null;
};

const baseline = await catalogRow();
check(
    'with no override the catalog charges the course\'s own fee',
    priceOf(baseline) === Number(AMOUNT),
    baseline.slice(0, 200),
);

// Edited, not created. `DefaultSelfLearningOfferingAction` reads the *first*
// self-learning offering of the course by id, so a walk that made a new one
// each run would set a price nobody reads — the seeder plants exactly one and
// resets it to "no override", which is what makes the reading above mean
// something on the second run as well as the first.
await staff.goto(`${BASE}/en/catalog/offerings`, { waitUntil: 'networkidle' });
const offeringRow = staff.locator('tr', { hasText: OFFERING }).first();
const haveOffering = (await offeringRow.count()) > 0;

check(
    'the seeded offering is on the catalog screen',
    haveOffering,
    haveOffering ? '' : `no row for ${OFFERING} — re-seed: php artisan db:seed --class=SmokeMarkerSeeder`,
);

const offeringRowText = async () =>
    (await staff.locator('tr', { hasText: OFFERING }).first().innerText()).replace(/\s+/g, ' ').trim();

/**
 * The saved price, once the table agrees it was saved.
 *
 * This is an Inertia form, so the page never navigates: the PUT resolves, the
 * props come back and React repaints the row some time after `networkidle` has
 * already gone quiet. Reading the row straight after the click caught the *old*
 * value twice and reported a save that had plainly worked — the catalog was
 * showing the new price on the very next step — as a failure.
 */
const setOverride = async (value, expected) => {
    await staff.goto(`${BASE}/en/catalog/offerings`, { waitUntil: 'networkidle' });
    await staff.locator('tr', { hasText: OFFERING }).first().locator('button').first().click();

    const field = staff.locator('input[placeholder="Price override (MVR)"]');
    const form = staff.locator('form').filter({ has: field }).first();
    await field.fill(value);
    await form.locator('button[type=submit]').first().click();

    for (let attempt = 0; attempt < 40; attempt += 1) {
        if (expected.test(await offeringRowText())) {
            break;
        }
        await staff.waitForTimeout(250);
    }

    return offeringRowText();
};

if (haveOffering) {
    const saved = await setOverride(OVERRIDE, new RegExp(`MVR ${OVERRIDE}\\b`));

    check(
        'an override can be set on the offering',
        saved.includes(`MVR ${OVERRIDE}`),
        saved.slice(0, 200),
    );

    const overridden = await catalogRow();
    check(
        'and the public listing charges the override, not the course fee',
        priceOf(overridden) === Number(OVERRIDE),
        `${baseline.slice(0, 90)} → ${overridden.slice(0, 90)}`,
    );

    // The line the whole step exists for. `0` has to survive as a number all the
    // way to the listing; anywhere it is treated as "empty" the family is
    // charged the course fee for a free offering.
    const zeroed = await setOverride('0', /MVR 0\b/);
    check('an override of 0 saves as 0 rather than clearing', /MVR 0\b/.test(zeroed), zeroed.slice(0, 200));

    const free = await catalogRow();
    check(
        'and a 0 override makes the course free, not 250',
        priceOf(free) === null && /enrol/i.test(free),
        free.slice(0, 200),
    );

    // Free on the screen and free at the till are two different claims. This is
    // the second: the enrolment opens with no payment behind it at all.
    if (priceOf(free) === null) {
        await payerPage.locator('tr', { hasText: COURSE }).first().locator('button').first().click();
        await payerPage.waitForLoadState('networkidle');

        const enrolled = await catalogRow();
        check(
            'and enrolling at 0 opens the course with no payment taken',
            /open/i.test(enrolled) && priceOf(enrolled) === null,
            enrolled.slice(0, 200),
        );
    }
}

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
