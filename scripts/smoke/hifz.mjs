/**
 * Does what survives of the Hifz app still work for the people who use it?
 *
 * ADR-029 moved the Qur'an dataset and the session, assignment and milestone
 * *recommendation* workflows onto the engine, and kept the rest of the Blade
 * Hifz app — the hub, the five dashboards, programmes, enrolments, the
 * milestone review/approve half, and the reports — because retiring them is
 * an information-architecture decision with its own parity work. The §2 row
 * has said UNVERIFIED for as long as those screens have existed (Hifz audit
 * D1, STATUS §5fn). This walks them, five logins:
 *
 *   1. the dean lands on their dashboard from the hub, finds the seeded
 *      `SMOKE-Halaqa` programme and enrols the smoke pupil through the Blade
 *      form;
 *   2. the programme's supervisor lands on theirs, finds the pupil's pending
 *      milestone (seeded — recommendation lives on the engine board since
 *      F5-P3) and reviews it;
 *   3. the dean sees it waiting, approves it, and reads it on the milestone
 *      report; the reports hub and the CSV open;
 *   4. the teacher, the pupil and the parent each land on their own
 *      dashboard from the same hub: the teacher sees the programme, the
 *      pupil the approved milestone, the parent the child.
 *
 * `SmokeMarkerSeeder::hifzCycle()` gives the programme its supervisor and
 * teacher and plants the pending milestone before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/hifz.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_SUPERVISOR, SMOKE_TEACHER,
 * SMOKE_STUDENT, SMOKE_PARENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const SUPERVISOR = process.env.SMOKE_SUPERVISOR ?? 'supervisor@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const PROGRAM = 'SMOKE-Halaqa';
const MILESTONE = 'SMOKE-Milestone';

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

const rowText = async (page, needle) => {
    const row = page.locator('tr', { hasText: needle }).first();
    // Give the row a moment to be there rather than counting at once: the
    // third staging run counted zero programme rows and, two lines later,
    // found the same row's View link (STATUS §5fz).
    await row.waitFor({ state: 'attached', timeout: 3000 }).catch(() => {});

    return (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : '';
};

// The hub sends each role to its own dashboard. Asserted from where the
// browser ends up, not from a link.
async function hub(page, expected) {
    const response = await page.goto(`${BASE}/en/hifz`, { waitUntil: 'networkidle' });

    return { ok: response.status() === 200 && page.url().includes(`/hifz/${expected}`), detail: `HTTP ${response.status()} → ${page.url().replace(BASE, '')}` };
}

// ---------------------------------------------------------------- the pupil

// Signed in first only to learn their own name, which the office's forms
// show and the walk must pick — the seed data decides it, not this script.
const student = await signIn(STUDENT);
check('the pupil signs in', !student.url().includes('/login'), student.url());
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the pupil has a name the office will see', NAME.length > 0, NAME || 'no h2 on /portal/performance');

// ----------------------------------------------------------------- the dean

const dean = await signIn(ADMIN);
check('the dean signs in', !dean.url().includes('/login'), dean.url());
let landing = await hub(dean, 'dean');
check('the hub sends the dean to the dean dashboard', landing.ok && (await text(dean)).includes('Active Students'), landing.detail);

await dean.goto(`${BASE}/en/hifz/programs`, { waitUntil: 'networkidle' });
const programRow = await rowText(dean, PROGRAM);
check('the programme list shows the seeded halaqa, active, with its supervisor', programRow.includes('active') && !/—\s*active/.test(programRow), programRow || (await text(dean)).slice(0, 160));
const programHref = await dean.locator('tr', { hasText: PROGRAM }).first().locator('a', { hasText: 'View' }).getAttribute('href').catch(() => null);
await dean.goto(new URL(programHref ?? '/hifz/programs', BASE).href, { waitUntil: 'networkidle' });
if ((await rowText(dean, NAME)) !== '') {
    check('there is a clean programme to walk', false, `${NAME} is already enrolled in ${PROGRAM} — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

// 1. an enrolment through the Blade form
await dean.locator('a', { hasText: 'Enroll Student' }).first().click();
await dean.waitForURL(/enrollments\/create/);
const studentOption = await dean.locator('select[name="student_id"] option', { hasText: NAME }).first().getAttribute('value').catch(() => null);
check('the enrolment form offers the pupil', Boolean(studentOption), studentOption ?? `${NAME} not in the student list`);
if (studentOption) {
    await dean.selectOption('select[name="student_id"]', studentOption);
}
await dean.click('button:has-text("Enroll")');
await dean.waitForLoadState('networkidle');
const enrolled = await text(dean);
const enrolRow = await rowText(dean, NAME);
check('the pupil is enrolled, with the programme\'s default teacher', enrolled.includes('Student enrolled successfully.') && enrolRow.includes('active') && !/—\s*active/.test(enrolRow), enrolRow || enrolled.slice(0, 160));
await dean.goto(new URL(`${programHref}/enrollments`, BASE).href, { waitUntil: 'networkidle' });
check('the enrolment list has them', (await rowText(dean, NAME)) !== '', (await rowText(dean, NAME)) || (await text(dean)).slice(0, 160));

// ----------------------------------------------------------- the supervisor

const supervisor = await signIn(SUPERVISOR);
check('the supervisor signs in', !supervisor.url().includes('/login'), supervisor.url());
landing = await hub(supervisor, 'supervisor');
check('the hub sends the supervisor to the supervisor dashboard, with the pending milestone', landing.ok && (await text(supervisor)).includes(NAME), landing.detail);

// 2. review
await supervisor.goto(`${BASE}/en/hifz/milestones`, { waitUntil: 'networkidle' });
const pending = supervisor.locator('tr', { hasText: NAME }).filter({ hasText: 'pending' }).first();
check('the pending milestone is listed with a Review action', (await pending.count()) > 0 && (await pending.locator('button:has-text("Review")').count()) > 0, (await pending.count()) ? (await pending.innerText()).replace(/\s+/g, ' ') : (await text(supervisor)).slice(0, 160));
if (await pending.count()) {
    await pending.locator('button:has-text("Review")').click();
    await supervisor.waitForLoadState('networkidle');
}
check('the supervisor reviews it and it goes to the dean', (await text(supervisor)).includes('Milestone reviewed and sent to dean.'), (await text(supervisor)).slice(0, 160));

// ---------------------------------------------------------- the dean, again

// 3. approve
await dean.goto(`${BASE}/en/hifz/dean`, { waitUntil: 'networkidle' });
check('the dean dashboard shows it waiting for approval', (await text(dean)).includes(`${NAME} — surah completed`), (await text(dean)).match(new RegExp(`${NAME} — surah completed`))?.[0] ?? (await text(dean)).slice(0, 160));
await dean.goto(`${BASE}/en/hifz/milestones`, { waitUntil: 'networkidle' });
const reviewed = dean.locator('tr', { hasText: NAME }).filter({ hasText: 'supervisor_reviewed' }).first();
if (await reviewed.count()) {
    await reviewed.locator('button:has-text("Approve")').click();
    await dean.waitForLoadState('networkidle');
}
check('the dean approves it', (await text(dean)).includes('Milestone approved.') && (await dean.locator('tr', { hasText: NAME }).filter({ hasText: 'approved' }).count()) > 0, (await text(dean)).slice(0, 160));

const reports = await dean.goto(`${BASE}/en/hifz/reports`, { waitUntil: 'networkidle' });
check('the reports hub opens with its five reports', reports.status() === 200 && (await text(dean)).includes('Milestone Approval'), `HTTP ${reports.status()}`);
await dean.goto(`${BASE}/en/hifz/reports/milestones`, { waitUntil: 'networkidle' });
const reported = await rowText(dean, PROGRAM);
check('the milestone report lists it approved under the programme', reported.includes(NAME) && reported.includes('approved'), reported || (await text(dean)).slice(0, 160));
const csv = await dean.request.get(`${BASE}/en/hifz/reports/export?type=sessions`);
check('the sessions CSV exports', csv.status() === 200 && /csv/i.test(csv.headers()['content-type'] ?? '') && (await csv.text()).includes(PROGRAM), `HTTP ${csv.status()} ${csv.headers()['content-type'] ?? ''}`);

// -------------------------------------------------- the teacher, pupil, parent

const teacher = await signIn(TEACHER);
landing = await hub(teacher, 'teacher');
check('the hub sends the teacher to the teacher dashboard, with the programme', landing.ok && (await text(teacher)).includes(PROGRAM), landing.detail);

landing = await hub(student, 'student');
check('the hub sends the pupil to their progress, with the approved milestone', landing.ok && (await text(student)).includes(MILESTONE), landing.detail + ' ' + ((await text(student)).match(/Approved Milestones.{0,80}/)?.[0] ?? ''));

const parent = await signIn(PARENT);
landing = await hub(parent, 'parent');
check('the hub sends the parent to the child\'s progress', landing.ok && (await text(parent)).includes(NAME), landing.detail);

await finish();
