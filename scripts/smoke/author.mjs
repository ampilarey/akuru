/**
 * Can a person build a course and get it in front of a student?
 *
 * Phase 1A's definition of done (SPEC §46.2) is a list of sentences, and the
 * first one that names a person is: *"A course creator can create a course,
 * modules, lessons, and Phase 1A content blocks from the dashboard."* The
 * §57.2 slice order is the same path in build order — course → modules and
 * lessons → text blocks → media → publish → enrol → progress. `learn.mjs`
 * proved the student's half against a lesson the seeder had planted through
 * the actions; nobody had walked the author's half through the screens, and
 * the STATUS row said so: *"the outline editor … remain[s] UNVERIFIED"* (1A
 * audit D1, STATUS §5fg). This walks the sentence, three logins:
 *
 *   1. the author (admin: `courses.manage`) creates `SMOKE-Authored`, a
 *      module, a lesson, a text block, an instruction block and an image
 *      block uploaded through the media pipeline, publishes the lesson and
 *      the module, and submits the course for review;
 *   2. the supervisor (§8.4, `courses.publish`) approves it — a different
 *      login, because reviewing is the one job §8.3's creator does not have;
 *   3. the student finds it in the catalog, enrols, opens the lesson, reads
 *      all three blocks — the image through the authorised media route, not
 *      a storage URL — marks it complete, and the course page reads 100%.
 *
 * The course is the walk's own and `SmokeMarkerSeeder::authorCycle()`
 * removes it and everything under it before each run, so this can run
 * twice. The image goes through `ProcessMediaFileJob`, so a queue worker
 * should be running on the host; the player serves the file either way.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/author.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_AUTHOR, SMOKE_REVIEWER, SMOKE_STUDENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const AUTHOR = process.env.SMOKE_AUTHOR ?? 'admin@akuru.edu.mv';
const REVIEWER = process.env.SMOKE_REVIEWER ?? 'supervisor@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const COURSE = 'SMOKE-Authored';
const MODULE = 'SMOKE-Authored-Module';
const LESSON = 'SMOKE-Authored-Lesson';
const BODY = 'SMOKE-Authored-Body';
const NOTE = 'SMOKE-Authored-Instruction';

// A one-pixel PNG, written to a temp file so the browser can pick it.
const PNG = join(mkdtempSync(join(tmpdir(), 'smoke-author-')), 'smoke-authored.png');
writeFileSync(PNG, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64'));

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
// Top-level `await` surfaces a timeout as an uncaught exception, not a
// rejection, so both are caught.
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

// ------------------------------------------------------------- the author

const author = await signIn(AUTHOR);
check('the author signs in', !author.url().includes('/login'), author.url());

await author.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
if ((await text(author)).includes(COURSE)) {
    check('there is a clean catalog to walk', false, `${COURSE} is already in the catalog — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

// 1. the course
const courseForm = author.locator('form', { hasText: 'Save draft' });
await courseForm.locator('input[placeholder="Title"]').fill(COURSE);
await courseForm.locator('button:has-text("Save draft")').click();
check('a course is created as a draft', await settles(author, 'Course saved as draft.'), (await text(author)).slice(0, 160));
const courseRow = await rowText(author, COURSE);
check('and listed as draft with its subject', courseRow.includes('draft'), courseRow);

const outlineHref = await author.locator('tr', { hasText: COURSE }).locator('a', { hasText: COURSE }).first().getAttribute('href');
check('the outline is reachable from the catalog', Boolean(outlineHref), outlineHref ?? 'no outline link');
const courseId = Number((outlineHref ?? '').match(/courses\/(\d+)/)?.[1] ?? 0);
await author.goto(new URL(outlineHref, BASE).href, { waitUntil: 'networkidle' });

// 2. module and lesson
const moduleForm = author.locator('form', { hasText: 'Save module' });
await moduleForm.locator('input[placeholder="Module title"]').fill(MODULE);
await moduleForm.locator('button:has-text("Save module")').click();
check('a module is added', await settles(author, 'Module saved.') && (await text(author)).includes(MODULE), (await text(author)).slice(0, 160));

const lessonForm = author.locator('form', { hasText: 'Save lesson' });
await lessonForm.locator('input[placeholder="Lesson title"]').fill(LESSON);
await lessonForm.locator('button:has-text("Save lesson")').click();
check('a lesson is added to it', await settles(author, 'Lesson saved.') && (await text(author)).includes(LESSON), (await text(author)).slice(0, 160));

// 3. three Phase 1A blocks: text, instruction, image (through the media pipeline)
const blockForm = () => author.locator('form', { hasText: 'Save block' });
const typeSelect = () => blockForm().locator('select').nth(1);

// Waited for by the numbered line the outline adds, not the flash: "Block
// saved." from the previous block is still on the screen when the next
// save is clicked, so the flash alone would pass before the list re-renders.
await typeSelect().selectOption('text');
await blockForm().locator('textarea[placeholder="Block content"]').fill(BODY);
await blockForm().locator('button:has-text("Save block")').click();
check('a text block is saved', await settles(author, '1. text'), (await text(author)).slice(0, 160));

await typeSelect().selectOption('instruction');
await blockForm().locator('textarea[placeholder="Block content"]').fill(NOTE);
await blockForm().locator('button:has-text("Save block")').click();
check('an instruction block is saved', await settles(author, '2. instruction'), (await text(author)).slice(0, 160));

await typeSelect().selectOption('image');
await blockForm().locator('input[type=file]').setInputFiles(PNG);
await blockForm().locator('button:has-text("Save block")').click();
check('an image block is uploaded through the media pipeline', await settles(author, '3. image'), (await text(author)).slice(0, 160));

// 4. publish: the lesson (an immutable revision) and the module
const lessonRow = author.locator('.border-t', { hasText: LESSON }).first();
await lessonRow.locator('button', { hasText: /^Publish$/ }).first().click();
check('the lesson is published as revision 1', await settles(author, 'Lesson published.') && /published r1/i.test(await lessonRow.innerText()), (await lessonRow.innerText()).replace(/\s+/g, ' ').slice(0, 120));

await author.locator(`button[aria-label="Publish module ${MODULE}"]`).click();
check('the module is published', await settles(author, 'Module status updated.'), (await text(author)).slice(0, 160));

// 5. submit for review
await author.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
await author.locator('tr', { hasText: COURSE }).locator('button:has-text("Submit review")').click();
check('the course is submitted for review', await settles(author, 'Course status updated.') && (await rowText(author, COURSE)).includes('in_review'), await rowText(author, COURSE));

// ---------------------------------------------------------- the supervisor

const reviewer = await signIn(REVIEWER);
check('the supervisor signs in', !reviewer.url().includes('/login'), reviewer.url());

await reviewer.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const review = reviewer.locator('tr', { hasText: COURSE });
check('the supervisor sees it waiting for review', (await review.count()) > 0 && (await review.innerText()).includes('in_review'), (await review.count()) ? (await review.innerText()).replace(/\s+/g, ' ') : 'no row');
await review.locator('select[aria-label="Review decision"]').selectOption('approved');
await review.locator('button:has-text("Record review")').click();
check('and approves it — the course is published', await settles(reviewer, 'Review recorded.') && (await rowText(reviewer, COURSE)).includes('published'), await rowText(reviewer, COURSE));

// ------------------------------------------------------------- the student

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

await student.goto(`${BASE}/en/learn/catalog`, { waitUntil: 'networkidle' });
const offer = student.locator('tr', { hasText: COURSE });
check('the course is in the learner catalog', (await offer.count()) > 0, (await text(student)).slice(0, 160));
await offer.locator('button:has-text("Enroll")').click();
// Enrolling lands on the course page itself, saying so.
check('the student enrols (free, self-learning)', await settles(student, 'Enrolled.') && /\/learn\/courses\/\d+/.test(student.url()), `${student.url()} ${(await text(student)).slice(0, 80)}`);

const coursePath = /\/learn\/courses\/\d+/.test(student.url()) ? new URL(student.url()).pathname : `/en/learn/courses/${courseId}`;
check('the course page names the lesson', (await text(student)).includes(LESSON), (await text(student)).slice(0, 160));

const lessonHref = (await student.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || ''))).find((href) => /\/learn\/lessons\/\d+/.test(href));
check('the lesson is reachable from the course', Boolean(lessonHref), lessonHref ?? 'no /learn/lessons/N link');
await student.goto(new URL(lessonHref ?? '/learn', BASE).href, { waitUntil: 'networkidle' });
const played = await text(student);
check('the player renders the text and instruction blocks', played.includes(BODY) && played.includes(NOTE), played.slice(0, 200));

const img = student.locator('main img').first();
const src = (await img.count()) ? await img.getAttribute('src') : null;
const media = src ? await student.request.get(new URL(src, BASE).href) : null;
// The learner's media route, not a storage URL (1A.5: "No public storage/
// URLs"). Staff see the same file through /catalog/media.
check(
    'and the image, through the authorised media route',
    Boolean(src) && /\/(learn|catalog)\/media\/\d+/.test(src) && media?.status() === 200 && /^image\/png/.test(media.headers()['content-type'] ?? ''),
    src ? `${src} → HTTP ${media?.status()} ${media?.headers()['content-type'] ?? ''}` : 'no <img> in the player',
);

await student.locator('button:has-text("Mark complete")').first().click();
await student.waitForLoadState('networkidle');
await student.goto(new URL(coursePath, BASE).href, { waitUntil: 'networkidle' });
const after = await text(student);
check('marking it complete moves the course to 100%', after.includes('100%'), (after.match(/\d+%/) ?? ['no percentage on the course page'])[0]);

await finish();
