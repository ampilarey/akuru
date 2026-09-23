/**
 * Can the office run a scheduled intake, and can a student get a seat in it?
 *
 * Phase 1B's definition of done (SPEC §46.4): *"Admin can create at least one
 * offering for a course … select delivery mode … Course sessions can be
 * stored … Student enrollment can link to an offering … Seat limits are
 * enforced safely."* Every clause had a test and the offerings screen showed a
 * planted row (§5ds); the STATUS row said the rest was UNVERIFIED, and the
 * 1B audit found the reason a walk had never been written: **no screen let a
 * learner choose an offering** (D1, STATUS §5fh). This walks the loop, two
 * logins:
 *
 *   1. the office creates `SMOKE-Intake` — face-to-face, open, one seat — for
 *      the seeded `SMOKE-Intake-Course`, pins it, and adds a session;
 *   2. the student finds the intake in the learner catalog with its one seat
 *      and its next session, enrols into it, and sees the intake named on
 *      the course page with the session under "Upcoming sessions";
 *   3. back in the catalog the intake reads Full;
 *   4. the office opens the session's attendance, finds the student on the
 *      roster and marks them present.
 *
 * The offering is the walk's own; `SmokeMarkerSeeder::intakeCycle()` keeps
 * the course and clears the offering, its session, its attendance and the
 * enrolment before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/intake.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const COURSE = 'SMOKE-Intake-Course';
const INTAKE = 'SMOKE-Intake';
const SESSION = 'SMOKE-Session';

// Tomorrow at ten, as the datetime-local input wants it.
const tomorrow = new Date(Date.now() + 86400000);
const startsAt = `${tomorrow.toISOString().slice(0, 10)}T10:00`;
const endsAt = `${tomorrow.toISOString().slice(0, 10)}T11:00`;

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

const rowText = async (page, needle) => {
    const row = page.locator('tr', { hasText: needle }).first();

    return (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : '';
};

// -------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());

await admin.goto(`${BASE}/en/catalog/offerings`, { waitUntil: 'networkidle' });
// The offering's own row — its title is the first cell's button. Matching on
// text alone would find the course, whose name the intake's begins with.
const intakeRow = () => admin.locator('tr', { has: admin.locator('td:first-child button', { hasText: /^SMOKE-Intake$/ }) }).first();
const intakeRowText = async () => ((await intakeRow().count()) ? (await intakeRow().innerText()).replace(/\s+/g, ' ') : '');
if (await intakeRow().count()) {
    check('there is a clean catalog to walk', false, `${INTAKE} already exists — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

// 1. the offering: face-to-face, open, one seat
const form = admin.locator('form', { has: admin.locator('select[aria-label="Course"]') }).first();
await form.locator('select[aria-label="Course"]').selectOption({ label: COURSE });
await form.locator('input[placeholder="Offering title"]').fill(INTAKE);
await form.locator('select[aria-label="Delivery mode"]').selectOption('face_to_face');
await form.locator('select[aria-label="Status"]').selectOption('open');
await form.locator('input[placeholder="Seat limit"]').fill('1');
await form.locator('button[type=submit]').click();
// Inertia re-renders after the redirect; wait for the row, not the network.
const until = async (probe, ms = 6000) => {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if (await probe()) {
            return true;
        }
        await admin.waitForTimeout(100);
    }

    return false;
};
await until(async () => (await intakeRow().count()) > 0);
check('a face-to-face intake with one seat is created', /face_to_face/.test(await intakeRowText()) && /\bopen\b/.test(await intakeRowText()), (await intakeRowText()) || (await text(admin)).slice(0, 160));

// 2. pin it to the current published revisions (the prompt asks why)
admin.once('dialog', (dialog) => dialog.accept('SMOKE-Pin'));
await intakeRow().locator('button:has-text("Pin now")').click();
check('and pinned, with a reason', await settles(admin, 'Offering pinned to current revisions.') && /pinned/.test(await intakeRowText()), await intakeRowText());

// 3. a session tomorrow
const sessionsHref = await intakeRow().locator('a:has-text("Sessions")').getAttribute('href');
await admin.goto(new URL(sessionsHref, BASE).href, { waitUntil: 'networkidle' });
const sessionForm = admin.locator('form', { hasText: 'Save session' });
await sessionForm.locator('input[placeholder="Session title"]').fill(SESSION);
await sessionForm.locator('input[type="datetime-local"]').nth(0).fill(startsAt);
await sessionForm.locator('input[type="datetime-local"]').nth(1).fill(endsAt);
await sessionForm.locator('input[placeholder="Location"]').fill('SMOKE-Room');
await sessionForm.locator('button:has-text("Save session")').click();
check('a session is scheduled for tomorrow', await settles(admin, SESSION) && (await rowText(admin, SESSION)).includes(`${startsAt.slice(0, 10)}T10:00`), await rowText(admin, SESSION));

// ------------------------------------------------------------- the student

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

await student.goto(`${BASE}/en/learn/catalog`, { waitUntil: 'networkidle' });
const courseRow = student.locator('tr', { hasText: COURSE }).first();
const intake = courseRow.locator('[data-testid=intakes] li', { hasText: INTAKE }).first();
check(
    'the intake is offered in the learner catalog with its seat and next session',
    (await intake.count()) > 0 && /1 seat left/.test(await intake.innerText()) && (await intake.innerText()).includes(SESSION),
    (await intake.count()) ? (await intake.innerText()).replace(/\s+/g, ' ') : (await text(student)).slice(0, 160),
);

await intake.locator('button:has-text("Enroll in this intake")').click();
check('the student enrols into it', await settles(student, 'Enrolled.') && /\/learn\/courses\/\d+/.test(student.url()), `${student.url()} ${(await text(student)).slice(0, 80)}`);
const coursePage = await text(student);
check('the course page names the intake and its mode', coursePage.includes(INTAKE) && /Face[- ]to[- ]face/i.test(coursePage), coursePage.slice(0, 200));
check('and lists the session as upcoming', /Upcoming sessions/.test(coursePage) && coursePage.includes(SESSION), coursePage.slice(0, 200));

await student.goto(`${BASE}/en/learn`, { waitUntil: 'networkidle' });
check('the session is on the learner dashboard too', (await text(student)).includes(SESSION), (await text(student)).slice(0, 160));

// 4. the one seat is gone
await student.goto(`${BASE}/en/learn/catalog`, { waitUntil: 'networkidle' });
const after = student.locator('tr', { hasText: COURSE }).locator('[data-testid=intakes] li', { hasText: INTAKE }).first();
check('the catalog now shows the intake full, and the student\'s own', /Full/.test(await after.innerText()) && /Your intake/.test(await after.innerText()), (await after.innerText()).replace(/\s+/g, ' '));

// --------------------------------------------------------- attendance

await admin.goto(new URL(sessionsHref, BASE).href, { waitUntil: 'networkidle' });
await admin.locator('tr', { hasText: SESSION }).locator('button:has-text("Attendance")').click();
// The button navigates through Inertia, so wait for the URL, not the network.
await admin.waitForURL(/\/attendance/, { timeout: 10000 });
await admin.waitForLoadState('networkidle');
const rosterNames = await admin.locator('main form select').first().locator('option').allInnerTexts();
check('the student is on the session roster', rosterNames.length === 1 && !/Enrollment \d+/.test(rosterNames[0]), rosterNames.join(' | ') || 'empty roster');
await admin.locator('main form button[type=submit]').first().click();
const present = await until(async () => /enrollment \d+ PRESENT/i.test(await text(admin)));
check('and is marked present', present, (await text(admin)).match(/enrollment \d+ \w+/i)?.[0] ?? (await text(admin)).slice(0, 160));

await finish();
