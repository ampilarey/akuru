/**
 * Does a mark a teacher types end up on a report card a family can open?
 *
 * S3 is the exam cycle: schedule → marks entry → review → publish → gradebook
 * → report card → parent portal. Every screen on that path had tests and the
 * loop had been closed by people twice, in pilot rehearsal rounds, and by a
 * script never — which is the gap the S3 audit found (STATUS §5ex). Rehearsal
 * rounds 1 and 2 are the lesson behind the definition of done: CI-green
 * slices that still left empty grids. So this asks the whole question, one
 * browser, three logins:
 *
 *   1. an admin saves a weight scheme if the year has none, schedules
 *      `SMOKE-Exam`, moves it into marks entry, types one mark, moves it
 *      through review to published, recomputes the gradebook, generates the
 *      term's report cards, watches the render job finish, publishes them;
 *   2. a parent sees the mark on `/portal/exams` and opens the report card.
 *
 * ## The term is the walk's own
 *
 * `SmokeMarkerSeeder` plants `SMOKE-Term` and empties it on every run — a
 * published report card cannot be regenerated and `report_cards` is unique
 * per student and term, so against a real term the second run would find
 * nothing to publish and fail on a rule that is working. The seeder explains.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/exams.mjs
 *
 * ## It needs a queue worker
 *
 * Report cards render on the queue (`RenderReportCardJob`). Step 12 waits up
 * to `SMOKE_QUEUE_WAIT` seconds (default 30) for the card to leave `draft`,
 * and if it does not, says so rather than timing out inside Playwright: on a
 * host with no worker running that is the finding, and it is the same one
 * `OPERATOR_CHECKLIST` §5 records for the deploy.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PARENT, SMOKE_PASSWORD,
 * SMOKE_CLASS (the label of the class the parent's child is in, as the
 * schedule form shows it — default `Grade 5 A`, the seeded pilot class),
 * SMOKE_QUEUE_WAIT, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const CLASS_LABEL = process.env.SMOKE_CLASS ?? 'Grade 5 A';
const QUEUE_WAIT = Number(process.env.SMOKE_QUEUE_WAIT ?? 30);

const TERM = 'SMOKE-Term';
const EXAM = 'SMOKE-Exam';
// 85 sits exactly on the default scale's A boundary (`min: 85`), so the
// gradebook step also checks that the boundary is inclusive.
const MARK = '85';
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

// Wait, bounded, for the screen to say something — then read it once. These
// buttons post over XHR and Inertia re-renders after the redirect; reading
// straight after the click sees the old page. See review.mjs.
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

// ---------------------------------------------------------------- the parent

// First, only to learn the child's name: every later step looks for it.
const parent = await signIn(PARENT);
check('the parent signs in', !parent.url().includes('/login'), parent.url());

await parent.goto(`${BASE}/en/portal/exams`, { waitUntil: 'networkidle' });
const CHILD = ((await parent.locator('main select option').first().textContent()) ?? '').trim();
check('the portal names a child', CHILD !== '', CHILD || 'no child option on /portal/exams');

// ------------------------------------------------------------------ the admin

const admin = await signIn(ADMIN);
check('the admin signs in', !admin.url().includes('/login'), admin.url());

// The schedule form: the year it opens on is the active one, and the term and
// class are chosen by label — ids differ per host, labels do not.
await admin.goto(`${BASE}/en/exams/schedule`, { waitUntil: 'networkidle' });
const form = admin.locator('form', { hasText: 'Schedule one exam' });
// Anchored at the start: a label's text runs straight into its options
// ("Year2026-2027 Pilot" — no word boundary), and "Year default" is an option
// under Class on the weights screen, so an unanchored match finds two labels.
const labelled = (scope, label) => scope.locator('label', { hasText: new RegExp(`^${label}(?![a-z])`) });
const field = (label) => labelled(form, label).locator('select, input').first();

const yearId = await field('Year').inputValue();
const termOption = labelled(form, 'Term').locator(`option:has-text("${TERM}")`);
const classOption = labelled(form, 'Class').locator(`option:has-text("${CLASS_LABEL}")`);
check(`the form offers ${TERM} (seeded by SmokeMarkerSeeder)`, (await termOption.count()) > 0);
check(`the form offers the class "${CLASS_LABEL}"`, (await classOption.count()) > 0, (await classOption.count()) ? '' : 'set SMOKE_CLASS to the label the schedule form shows');

// A weight scheme for the year, or the gradebook stays blank on purpose.
//
// The seed ships none, and the gradebook says so on screen instead of showing
// zeros — a decision the walk respects by going through the Weights screen
// the way an admin would, rather than around it.
await admin.goto(`${BASE}/en/exams/weights?academic_year_id=${yearId}`, { waitUntil: 'networkidle' });
const resolved = (await text(admin)).match(/Resolved scheme: (\S+)/)?.[1] ?? '';

if (resolved === 'none') {
    const scheme = admin.locator('form', { hasText: 'Create year scheme' });
    await labelled(scheme, 'Year').locator('select').selectOption(yearId);
    await scheme.locator('button:has-text("Save scheme")').click();
    check('a year weight scheme is saved through the form', await settles(admin, 'Weight scheme saved.'), (await text(admin)).slice(0, 160));
} else {
    check('a year weight scheme is saved through the form', resolved !== '', `a scheme already resolves for the year (${resolved})`);
}

// ------------------------------------------------------------ 1. schedule it

await admin.goto(`${BASE}/en/exams/schedule`, { waitUntil: 'networkidle' });

// Is there a clean term to walk?
//
// Run twice without re-seeding, the second run finds last run's exam already
// published and last run's report cards refusing to be generated again, and
// used to spend thirty seconds inside a Playwright click on a disabled
// button before dying with a stack trace that reads like a broken screen.
// Say it plainly instead — the same courtesy review.mjs extends.
if ((await admin.locator('tr', { hasText: EXAM }).count()) > 0) {
    check(
        'there is a clean SMOKE-Term to walk',
        false,
        `${EXAM} is already scheduled — left over from an earlier run. `
            + 'Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder',
    );
    await finish();
}

let termId = '';
let classId = '';
let subjectId = '';

if ((await termOption.count()) && (await classOption.count())) {
    await field('Term').selectOption({ label: TERM });
    await field('Class').selectOption({ label: CLASS_LABEL });
    termId = await field('Term').inputValue();
    classId = await field('Class').inputValue();
    subjectId = await field('Subject').inputValue();
    await field('Name').fill(EXAM);
    await field('Date').fill(today);
    // Today may be a holiday or already carry this class's quota of exams
    // on the host being walked. Both are the admin's own overrides, and
    // neither is the question this walk asks.
    await labelled(form, 'Confirm holiday').locator('input').check();
    await labelled(form, 'Confirm same-day').locator('input').check();
    await form.locator('button:has-text("Schedule")').click();
}

const scheduled = await settles(admin, 'Exam scheduled.');
check('the exam is scheduled', scheduled, scheduled ? '' : (await text(admin)).slice(0, 200));
check('it appears in the table as scheduled', /scheduled/i.test(await rowText(admin, EXAM)), await rowText(admin, EXAM));

// -------------------------------------------------- 2. through the statuses

async function move(status) {
    const row = admin.locator('tr', { hasText: EXAM }).first();
    await row.locator('select').selectOption(status);
    await row.locator('button:has-text("Move")').click();
    const moved = await settles(admin, 'Exam status updated.');
    const after = await rowText(admin, EXAM);
    check(`it moves to ${status}`, moved && new RegExp(status, 'i').test(after), after || (await text(admin)).slice(0, 160));
    // The flash stays on screen; clear it so the next move's wait is honest.
    await admin.reload({ waitUntil: 'networkidle' });
}

await move('marks_entry');

// -------------------------------------------------------- 3. type one mark

await admin.locator('tr', { hasText: EXAM }).first().locator('a:has-text("Marks")').click();
await admin.waitForLoadState('networkidle');
const marks = admin.locator('tr', { hasText: CHILD }).first().locator('input[data-mark-row]');
check('the child is on the roster', (await marks.count()) > 0, (await text(admin)).slice(0, 160));

if (await marks.count()) {
    await marks.fill(MARK);
    await marks.press('Tab');
    const saved = await settles(admin, 'Mark saved.');
    check('the mark saves on blur', saved && /\b[1-9]\d*\/\d+ entered/.test(await text(admin)), (await text(admin)).slice(0, 120));
}

await admin.goto(`${BASE}/en/exams/schedule`, { waitUntil: 'networkidle' });
await move('review');
await move('published');

// ------------------------------------------------------------- 4. gradebook

await admin.goto(`${BASE}/en/exams/gradebook?class_id=${classId}&subject_id=${subjectId}&term_id=${termId}`, { waitUntil: 'networkidle' });
await admin.locator('button:has-text("Recompute")').click();
check('the term grades recompute', await settles(admin, 'Term grades recomputed.'), (await text(admin)).slice(0, 160));

const gradeRow = await rowText(admin, CHILD);
check('the gradebook shows the mark', gradeRow.includes(MARK), gradeRow);
// Renormalised over the one type with a published exam, 85/100 is 85% and an
// A on the default scale. Two separate cells, so two numbers in the row.
check('the term percent and grade are computed', /85(\.0+)?\s+85(\.0+)?\s+A\b/.test(gradeRow), gradeRow);
check('there is no missing-weights notice', !(await text(admin)).includes('stay blank until a weight scheme'));

// ---------------------------------------------------------- 5. report cards

const cardsUrl = `${BASE}/en/exams/report-cards?class_id=${classId}&term_id=${termId}`;
await admin.goto(cardsUrl, { waitUntil: 'networkidle' });
const generate = admin.locator('form', { hasText: 'Generate' }).first();
await generate.locator('select').nth(0).selectOption(classId);
await generate.locator('select').nth(1).selectOption(termId);
await generate.locator('button:has-text("Generate")').click();
check('report cards are queued', await settles(admin, 'Report cards queued.'), (await text(admin)).slice(0, 160));
check('the overview counts them as unpublished', /\d+ unpublished report card/.test(await text(admin)), (await text(admin)).slice(0, 160));

// The render job.
let cardRow = '';
const deadline = Date.now() + QUEUE_WAIT * 1000;
while (Date.now() < deadline) {
    await admin.goto(cardsUrl, { waitUntil: 'networkidle' });
    cardRow = await rowText(admin, CHILD);
    if (/\bready\b/.test(cardRow)) {
        break;
    }
    await admin.waitForTimeout(1000);
}
check(
    `the render job finishes within ${QUEUE_WAIT}s`,
    /\bready\b/.test(cardRow),
    /\bready\b/.test(cardRow) ? '' : `${cardRow || 'no row for ' + CHILD} — is a queue worker running? (php artisan queue:work)`,
);

const publish = admin.locator('form', { hasText: 'Publish ready cards' });
await publish.locator('select').nth(0).selectOption(classId);
await publish.locator('select').nth(1).selectOption(termId);
await publish.locator('button:has-text("Publish ready cards")').click();
check('the ready cards publish', await settles(admin, 'Report cards published.'), (await text(admin)).slice(0, 160));
check('the child\'s card is published', /\bpublished\b/.test(await rowText(admin, CHILD)), await rowText(admin, CHILD));

// ----------------------------------------------------- 6. back to the parent

await parent.goto(`${BASE}/en/portal/exams`, { waitUntil: 'networkidle' });
const portalRow = await rowText(parent, EXAM);
check('the parent sees the published exam', portalRow !== '', portalRow || (await text(parent)).slice(0, 160));
check('with the mark', portalRow.includes(MARK), portalRow);

await parent.goto(`${BASE}/en/portal/report-cards`, { waitUntil: 'networkidle' });
const cardLink = parent.locator('tr', { hasText: TERM }).first().locator('a:has-text("Download HTML")');
check('the parent sees the term\'s report card', (await cardLink.count()) > 0, (await text(parent)).slice(0, 160));

if (await cardLink.count()) {
    const href = await cardLink.getAttribute('href');
    const opened = await parent.goto(new URL(href, BASE).href, { waitUntil: 'domcontentloaded' });
    const body = await parent.content();
    check('and can open it', opened.status() === 200 && body.includes(CHILD), `HTTP ${opened.status()}, ${body.length} bytes`);
}

await finish();
