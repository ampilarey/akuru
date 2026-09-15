/**
 * A family books a parent-teacher meeting. Does the teacher find out?
 *
 * Three actors, and the third is the question:
 *
 *   1. the office generates and publishes slots for a named teacher,
 *   2. a guardian books one for their child,
 *   3. **the teacher** looks for it.
 *
 * `meeting_slots.teacher_id` names the teacher, and the family screen shows
 * them that name before they book — so both sides know whose meeting it is.
 * What the walk asks is whether that teacher has anywhere to see it.
 *
 * The teacher's surfaces are checked one at a time and reported separately:
 * `/teach/schedule`, the E1 teacher portal at `/portal/teacher`, and
 * `/academics/meetings` itself, which is gated on `meetings.manage` — a
 * permission held by `admin`, `headmaster`, `supervisor` and `super_admin`,
 * and not by `teacher`.
 *
 * Reported rather than asserted green, in the same shape as the review-queue
 * step in `review.mjs`: if the answer is "nowhere", that is a finding about the
 * product and not a broken script, and it should read as one.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/meetings.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PARENT, SMOKE_STAFF, SMOKE_TEACHER,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// The **date**, not a title. Neither the office table nor the family table has
// a Title column — they show When / Teacher / Class / Seats — so the first
// version of this walk marked the slots with `SMOKE-Meeting` and then looked
// for a string no screen renders. Four failures, all of them its own. A slot
// generated for tomorrow only is unambiguous and is what both screens print.
const NOTES = 'SMOKE-Meeting-Note: I would like to talk about reading.';

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
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

// `main`, not `body`: the shell puts ~90 links above the content, so a body
// read can match a menu item rather than the page (OWNER_ACTIONS item 8).
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

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

const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

// ------------------------------------------ the office publishes some slots

const staff = await signIn(STAFF);
await staff.goto(`${BASE}/en/academics/meetings`, { waitUntil: 'networkidle' });

const teacherName = await staff
    .$eval('select >> nth=1', (el) => el.options[el.selectedIndex]?.text || '')
    .catch(() => '');

check('the office has a teacher to make slots for', teacherName !== '', teacherName || 'the Teacher select is empty');

await staff.fill('input[type=date]', tomorrow);
await staff.click('button:has-text("Generate slots")');

const published = await settles(staff, 'Meeting slots saved.');
check('the office generates and publishes slots', published, (await text(staff)).slice(0, 160));
check('and they appear in the office table', (await text(staff)).includes(tomorrow), tomorrow);

// ------------------------------------------------- the family books one

const parent = await signIn(PARENT);
await parent.goto(`${BASE}/en/portal/meetings`, { waitUntil: 'networkidle' });

const offered = await text(parent);
check('the family is offered the slot', offered.includes(tomorrow), offered.slice(0, 200));
check('and is told which teacher it is with', teacherName !== '' && offered.includes(teacherName), teacherName);

let booked = false;
let childName = '';
const card = parent.locator('tr').filter({ hasText: tomorrow }).first();
const bookButton = card.locator('button:has-text("Book")').first();

if (await bookButton.count()) {
    const childSelect = card.locator('select').first();
    childName = (await childSelect.count())
        ? await childSelect.evaluate((el) => el.options[el.selectedIndex]?.text || '')
        : ((await card.locator('td').allInnerTexts())[4] ?? '').trim();

    const notes = card.locator('input[type=text], textarea').first();
    if (await notes.count()) {
        await notes.fill(NOTES);
    }
    await bookButton.click();
    booked = await settles(parent, 'Booked');
    check('the family books it', booked, (await text(parent)).slice(0, 200));
} else {
    check('the family books it', false, 'no Book button on the slot');
}

// -------------------------------------- the office can see who booked what

if (booked) {
    await staff.goto(`${BASE}/en/academics/meetings`, { waitUntil: 'networkidle' });

    // The **row**, and the child's name in its Booked cell. A `/Booked/i` over
    // the page matched the table's own column heading, so it passed before the
    // family had booked anything — one more check that passed on silence.
    const row = staff.locator('tr').filter({ hasText: tomorrow }).first();
    const cells = await row.locator('td').allInnerTexts().catch(() => []);

    check(
        'the office sees who booked it',
        (cells[3] ?? '').includes(childName),
        cells.join(' | ').slice(0, 200) || 'no row for ' + tomorrow,
    );
}

// ---------------------------------------------- and the teacher? the question

const teacher = await signIn(TEACHER);

// `/teach/meetings` is the answer this walk was written to ask for and now
// gets. The other three are kept and reported: they are where a teacher would
// reasonably look, and the walk should say plainly that the booking is not
// there — `/academics/meetings` in particular still answers 403, correctly,
// because it is the office's screen for everybody's meetings.
for (const [label, path, expected] of [
    ['/teach/meetings', '/en/teach/meetings', true],
    ['/teach/schedule', '/en/teach/schedule', false],
    ['/portal/teacher', '/en/portal/teacher', false],
    ['/academics/meetings', '/en/academics/meetings', false],
]) {
    const response = await teacher.goto(BASE + path, { waitUntil: 'networkidle' });
    const status = response.status();
    const body = status === 200 ? await text(teacher) : '';

    const sees = status === 200 && body.includes(tomorrow) && body.includes(childName);

    check(
        expected
            ? `the teacher sees the booking on ${label}`
            : `${label} is not where it shows up (recorded, not required)`,
        sees === expected,
        status === 200
            ? (sees ? body.slice(0, 200) : `HTTP 200, nothing for ${tomorrow}`)
            : `HTTP ${status}`,
    );
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
