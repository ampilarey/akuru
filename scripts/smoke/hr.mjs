/**
 * Does a staff member's month — check-in, leave, appraisal, payslip — go through?
 *
 * S5's definition of done is one sentence: *"Staff month-in-the-life works end
 * to end: check-ins recorded → leave requested/approved (balance moves,
 * substitution created) → permit-expiry alert fires → appraisal completed →
 * payroll period run, reviewed, approved → payslip downloaded by staff in
 * Portal → bank CSV exported."* Every screen on that path had tests and a
 * planted row for the sweep to find; nobody had walked the sentence (S5
 * audit D1, STATUS §5fd). This walks it, two logins:
 *
 *   1. the staff member checks in, asks for a day's leave, acknowledges
 *      their appraisal and opens their payslip;
 *   2. the office approves the leave and sees the balance fall, the day go
 *      on leave and the absence and its cover request reach the cover
 *      register, sends the permit-expiry notices, opens the appraisal cycle,
 *      and runs, approves, pays and locks a payroll period.
 *
 * ## Payroll only where the flag allows
 *
 * `payroll.enabled` is two things: a settings row (the seeder sets it) and
 * the environment's `PAYROLL_ENABLED`, which stays off on every host until
 * two parallel cycles match the manual process (S5 DoD line 64, the owner's).
 * Where it is off the payroll steps are reported as **skipped**, not passed
 * and not failed, and the walk still says what it could not prove.
 *
 * The period it runs is 2099-12: nothing real can ever have that month, so
 * a synthetic host's real periods are never touched.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/hr.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STAFF (a login with a staff
 * profile — the seeded teacher by default), SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'teacher@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const REASON = 'SMOKE-Leave';
const CYCLE = 'SMOKE-Cycle';
const PERMIT = 'SMOKE-Permit';
const PERIOD = { year: '2099', month: '12' };
// A week out, and on a school day: the cover request only exists for a
// lesson on the timetable, and there are none on a Friday or Saturday.
const leaveDay = new Date(Date.now() + 7 * 86400000);
while ([5, 6].includes(leaveDay.getUTCDay())) {
    leaveDay.setUTCDate(leaveDay.getUTCDate() + 1);
}
const leaveDate = leaveDay.toISOString().slice(0, 10);
const today = new Date().toISOString().slice(0, 10);

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

// A locator that never resolves is a finding about a screen, not about the
// walk: report it as the failed step it is, with everything that passed
// before it, instead of dying with a Playwright stack trace and no table.
process.on('unhandledRejection', async (error) => {
    check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200));
    await finish();
});
// A third answer. "ok" and "FAIL" are the only two the other walks need; this
// one has steps that are honestly neither on a host where payroll is off.
const skip = (step, detail) => results.push([step, 'skip', detail]);

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) {
        console.log(`${ok === 'skip' ? 'skip' : ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

    const failed = results.filter(([, ok]) => ok === false).length;
    const skipped = results.filter(([, ok]) => ok === 'skip').length;
    console.log(`\n${results.length - failed - skipped}/${results.length - skipped} steps passed${skipped ? ` (${skipped} skipped — each says why above)` : ''}.`);

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

// `main`, not `body` — see review.mjs. The nav is ninety links. A page with
// no `main` at all (an error page, a redirect somewhere unexpected) reads as
// its body, so a wrong turn is reported as a failed step and not a stack trace.
const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');

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

// ------------------------------------------------------------------ the staff

const staff = await signIn(STAFF);
check('the staff member signs in', !staff.url().includes('/login'), staff.url());

await staff.goto(`${BASE}/en/portal/staff-check-in`, { waitUntil: 'networkidle' });
// The page names the staff member in bold; every later step looks for it.
const NAME = ((await staff.locator('main strong').first().textContent().catch(() => '')) ?? '').trim();
check('the check-in page names the staff member', NAME !== '', NAME || (await text(staff)).slice(0, 120));

// Is there a clean day to walk? See exams.mjs.
if ((await rowText(staff, today)).includes('self')) {
    check(
        'there is a clean day to walk',
        false,
        'today already has a self check-in — left over from an earlier run. '
            + 'Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder',
    );
    await finish();
}

// ------------------------------------------------------------ 1. check in

const checkIn = staff.locator('button:has-text("Check in today")');
check('self check-in is on for this host (seeded)', (await checkIn.count()) > 0, (await text(staff)).slice(0, 160));
if (await checkIn.count()) {
    await checkIn.click();
    check('the staff member checks in', await settles(staff, 'Checked in.'), (await text(staff)).slice(0, 160));
    const row = await rowText(staff, today);
    check('today is present, from the portal, with a time', /PRESENT/i.test(row) && row.includes('self') && /\d\d:\d\d/.test(row), row);
}

// -------------------------------------------------------- 2. ask for leave

await staff.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
const ask = staff.locator('form', { hasText: 'Submit request' });
await ask.locator('select').nth(0).selectOption('staff_leave');
await ask.locator('input[type=date]').nth(0).fill(leaveDate);
await ask.locator('input[type=date]').nth(1).fill(leaveDate);
await ask.locator('select').nth(1).selectOption({ label: 'Annual' });
await ask.locator('label', { hasText: /^Reason/ }).locator('textarea, input').first().fill(REASON);
await ask.locator('button:has-text("Submit request")').click();
check('a day of annual leave is requested', await settles(staff, 'Request submitted.'), (await text(staff)).slice(0, 160));
const card = staff.locator('section', { hasText: REASON }).first();
check('it is pending', (await card.count()) > 0 && /pending/i.test(await card.innerText()), (await card.count()) ? (await card.innerText()).replace(/\s+/g, ' ') : 'no card');

// ----------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());

// The balance before, read the way the office reads it.
await admin.goto(`${BASE}/en/hr/leave-balances`, { waitUntil: 'networkidle' });
const balanceRow = () => admin.locator('tr', { hasText: 'Annual' }).filter({ hasText: NAME || 'Smoke' }).first();
const cells = async () => (await balanceRow().locator('td').allInnerTexts()).map((c) => c.trim());
const before = Number((await cells())[5]);
check('the staff member has an annual entitlement', Number.isFinite(before), (await cells()).join(' | '));

await admin.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
const pending = admin.locator('section', { hasText: REASON }).first();
check('the office sees the request', (await pending.count()) > 0);
if (await pending.count()) {
    await pending.locator('select').selectOption('approved');
    await pending.locator('button:has-text("Review")').click();
    check('the leave is approved', await settles(admin, 'Request reviewed.'), (await text(admin)).slice(0, 160));
    const after = admin.locator('section', { hasText: REASON }).first();
    check('and shows as approved', /approved/i.test(await after.innerText()), (await after.innerText()).replace(/\s+/g, ' '));
}

await admin.goto(`${BASE}/en/hr/leave-balances`, { waitUntil: 'networkidle' });
const after = Number((await cells())[5]);
check('the balance falls by one day', after === before - 1, `${before} → ${after}`);

await admin.goto(`${BASE}/en/hr/attendance?date=${leaveDate}`, { waitUntil: 'networkidle' });
const onLeave = await rowText(admin, NAME || 'Smoke');
check('the day is on leave in the staff register', /ON_LEAVE/i.test(onLeave) && /Approved leave/.test(onLeave), onLeave || (await text(admin)).slice(0, 160));

// "substitution created" — the half of the DoD line that was silently never
// happening: the approval looked the teacher up by a column nothing sets, so
// no absence reached the cover register and no lesson was ever covered
// (STATUS §5fd). The absence list names the reason; the cover request is the
// date's open row. Both are the older Blade screens the office already uses.
await admin.goto(`${BASE}/en/substitutions/absences`, { waitUntil: 'networkidle' });
const absence = await rowText(admin, REASON);
check('the absence is on the cover register', absence.includes(REASON), absence || (await text(admin)).slice(0, 160));

await admin.goto(`${BASE}/en/substitutions/requests?date=${leaveDate}&status=open`, { waitUntil: 'networkidle' });
const coverDate = leaveDay.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric', timeZone: 'UTC' });
const cover = await rowText(admin, coverDate);
if (cover) {
    check('a cover request is raised for that day\'s lesson', /Open/.test(cover), cover);
} else {
    // The seeded teacher has a lesson every school day; a host's real teacher
    // may not. The screen does not say which, so the step says what it saw.
    skip('a cover request is raised for that day\'s lesson', `no open cover request dated ${coverDate} — the staff member may have no lesson on the timetable that day`);
}

// The policy behind the check-in button the staff member just used, on the
// screen that did not exist before S5 slice 2 (audit D3): read back on, saved
// unchanged, still on.
await admin.goto(`${BASE}/en/hr/settings`, { waitUntil: 'networkidle' });
const selfCheckIn = admin.locator('input[type=checkbox]').first();
check('the HR settings screen shows self check-in on', await selfCheckIn.isChecked(), (await text(admin)).slice(0, 160));
await admin.locator('button:has-text("Save HR settings")').click();
check('and saves the HR policy', await settles(admin, 'HR settings saved.'), (await text(admin)).slice(0, 160));

// --------------------------------------------------- 3. the permit expiry

await admin.goto(`${BASE}/en/hr/compliance`, { waitUntil: 'networkidle' });
const permit = await rowText(admin, PERMIT);
check('the expiring permit is on the compliance list', permit.includes(PERMIT) && /\b2[0-9]\b/.test(permit), permit || (await text(admin)).slice(0, 160));
await admin.locator('button:has-text("Send due notices")').click();
const notices = (await settles(admin, 'expiry notices sent.')) ? (await text(admin)).match(/(\d+) expiry notices sent\./)?.[1] : null;
check('the expiry notices go out', notices !== null && Number(notices) >= 1, notices === null ? (await text(admin)).slice(0, 160) : `${notices} sent`);

await staff.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the staff member is told their permit is expiring', /Document expiring in \d+ days/.test(await text(staff)), (await text(staff)).slice(0, 160));

// ------------------------------------------------------------ 4. appraisal

await admin.goto(`${BASE}/en/hr/appraisals`, { waitUntil: 'networkidle' });
const cycle = admin.locator('form', { hasText: 'Open cycle' });
await cycle.locator('input[placeholder="Cycle name"]').fill(CYCLE);
await cycle.locator('input[type=date]').nth(0).fill(today);
await cycle.locator('input[type=date]').nth(1).fill(leaveDate);
await cycle.locator('button:has-text("Open cycle")').click();
check('an appraisal cycle opens', await settles(admin, 'Appraisal cycle opened.'), (await text(admin)).slice(0, 160));

const appraise = admin.locator('form', { hasText: 'Save appraisal' });
await appraise.locator('select').nth(0).selectOption({ label: CYCLE });
await appraise.locator('select').nth(1).selectOption({ label: NAME || 'Smoke Marker' });
await appraise.locator('input[placeholder="Strengths"]').fill('SMOKE-Strengths');
await appraise.locator('button:has-text("Save appraisal")').click();
check('an appraisal is written for the staff member', await settles(admin, 'Appraisal saved.'), (await text(admin)).slice(0, 160));
check('it is a draft awaiting the staff member', /\bdraft\b|\bsubmitted\b/.test(await rowText(admin, CYCLE)), await rowText(admin, CYCLE));

await staff.goto(`${BASE}/en/portal/appraisals`, { waitUntil: 'networkidle' });
const mine = staff.locator('tr', { hasText: CYCLE }).first();
check('the staff member sees it', (await mine.count()) > 0, (await text(staff)).slice(0, 160));
if (await mine.count()) {
    await mine.locator('input[placeholder="Comment"]').fill('SMOKE-Acknowledged');
    await mine.locator('button:has-text("Acknowledge")').click();
    check('and acknowledges it', await settles(staff, 'Appraisal acknowledged.'), (await text(staff)).slice(0, 160));
    check('which the office sees', /acknowledged/.test(await (await admin.goto(`${BASE}/en/hr/appraisals`, { waitUntil: 'networkidle' }), rowText(admin, CYCLE))), await rowText(admin, CYCLE));
}

// --------------------------------------------------------------- 5. payroll

// The screen must open either way — off, it says so and points at HR
// settings. Until STATUS §5fu the index itself answered 403 with the flag
// down, so this read found no `main` at all and the walk died in Playwright
// instead of skipping; the off-path had never actually been run.
const payrollPage = await admin.goto(`${BASE}/en/hr/payroll`, { waitUntil: 'networkidle' });
check('the payroll screen opens whether or not payroll is on', payrollPage.status() === 200, `HTTP ${payrollPage.status()}`);
const payrollOff = payrollPage.status() === 200 && (await text(admin)).includes('Payroll is disabled');
const payrollSteps = [
    'a payroll period runs',
    'the staff member has a draft payslip with a net figure',
    'the period is approved',
    'the period is marked paid',
    'the bank CSV carries the staff member',
    'the period is locked',
    'the staff member sees the payslip',
    'and can open it',
];

if (payrollOff) {
    for (const step of payrollSteps) {
        skip(step, 'PAYROLL_ENABLED is off on this host — by design until two parallel cycles match (S5 DoD line 64)');
    }
} else {
    const run = admin.locator('form', { hasText: 'Run payroll' });
    await run.locator('input').nth(0).fill(PERIOD.year);
    await run.locator('input').nth(1).fill(PERIOD.month);
    await run.locator('button:has-text("Run payroll")').click();
    check(payrollSteps[0], await settles(admin, 'Draft payslips generated.'), (await text(admin)).slice(0, 160));
    const draft = await rowText(admin, NAME || 'Smoke Marker');
    check(payrollSteps[1], /\d+\.\d\d/.test(draft) && /draft/.test(draft), draft);

    await admin.locator('button:has-text("Approve")').click();
    check(payrollSteps[2], await settles(admin, 'Period approved.'), (await text(admin)).slice(0, 160));
    await admin.locator('button:has-text("Mark paid")').click();
    check(payrollSteps[3], await settles(admin, 'Period marked paid.'), (await text(admin)).slice(0, 160));

    const href = await admin.locator('a:has-text("Bank CSV")').getAttribute('href');
    const csv = await admin.request.get(new URL(href, BASE).href);
    const body = await csv.text();
    check(payrollSteps[4], csv.status() === 200 && body.includes(NAME || 'Smoke Marker'), `HTTP ${csv.status()}, ${body.split('\n').length} lines`);

    await admin.locator('button:has-text("Lock")').click();
    check(payrollSteps[5], await settles(admin, 'Period locked.'), (await text(admin)).slice(0, 160));

    await staff.goto(`${BASE}/en/portal/payslips`, { waitUntil: 'networkidle' });
    const slip = await rowText(staff, `${PERIOD.year}-${PERIOD.month}`);
    check(payrollSteps[6], slip.includes('final') && slip.includes('Open'), slip || (await text(staff)).slice(0, 160));
    const open = staff.locator('tr', { hasText: `${PERIOD.year}-${PERIOD.month}` }).locator('a:has-text("Open")');
    if (await open.count()) {
        const opened = await staff.goto(new URL(await open.getAttribute('href'), BASE).href, { waitUntil: 'domcontentloaded' });
        const html = await staff.content();
        // The staff member's own name and a document language: the generic
        // fallback the payslip used to be had neither (S5 audit D2).
        check(
            payrollSteps[7],
            opened.status() === 200 && /Payslip/.test(html) && html.includes(NAME) && /<html lang="(en|dv|ar)"/.test(html),
            `HTTP ${opened.status()}, ${html.length} bytes, ${html.includes(NAME) ? 'named' : 'unnamed'}`,
        );
    } else {
        check(payrollSteps[7], false, 'no Open link');
    }
}

await finish();
