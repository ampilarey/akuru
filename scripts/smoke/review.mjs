/**
 * Does work a machine cannot mark ever come back marked?
 *
 * `learn.mjs` walks a student through a `selection` activity — the one pattern
 * the engine scores by itself. This walks the other kind. A `teacher_marked`
 * activity is the only pattern whose attempt lands `submitted` rather than
 * `scored`, which is what puts a row in the review queue, so it is the only
 * path that needs a second person before the student learns anything.
 *
 * Three actors, one loop:
 *
 *   1. the student hands in written work and is told it is submitted,
 *   2. a marker finds it waiting, gives it a score and a sentence of feedback,
 *   3. the student opens the same page and sees the mark and the sentence.
 *
 * ## Why it did not exist
 *
 * Both ends had tests and the loop had never been closed by anybody. §36 lists
 * thirteen things a teacher must be able to do and six of them live on this one
 * screen — "view pending submissions", "open student submissions", "give
 * score", "give written feedback". The queue's year filter was fixed earlier
 * (STATUS §5dw) without anything ever walking the queue it feeds.
 *
 * ## The marker
 *
 * `SMOKE_MARKER` is who does the marking, and it is **not** the teacher login
 * by default. `/catalog/reviews` is gated on `courses.manage`, which the
 * `teacher` role does not hold; step 0 below records that separately rather
 * than hiding it, because a review queue the teacher cannot open is a finding
 * and not a broken script.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/review.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_MARKER, SMOKE_TEACHER,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const MARKER = process.env.SMOKE_MARKER ?? 'admin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const PROMPT = 'SMOKE-Review-Question';
const ACTIVITY = 'SMOKE-Review-Activity';
const ANSWER = 'SMOKE-Answer: the sun rises over the letter seen.';
const FEEDBACK = 'SMOKE-Feedback: well argued, mind the hamza.';
const SCORE = '4';

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

// `main`, not `body`. The shell puts ~90 navigation links above the content
// (OWNER_ACTIONS item 8), so a `body` read spends its first thousand
// characters on the nav — every failure detail printed by the first version of
// this script was nav text, and a substring check against `body` can match a
// menu item rather than the page. `main` is the page.
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

/**
 * Wait, bounded, for the screen to say something — then read it once.
 *
 * These buttons post over XHR: Inertia sends the form, follows the 302 and
 * re-renders. Neither `waitForLoadState('networkidle')` nor waiting on the
 * POST response covers that last step, and both let an earlier version of this
 * script read the page mid-flight and report that submitting "did not update
 * the screen" — three times, for two different screens, when the screen was
 * fine. A timeout here still fails the step, so a real regression is not
 * waited away; it is only given the two seconds a browser actually takes.
 */
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
const hrefs = (page) => page.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || ''));

// ---------------------------------------------------------------- the student

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

// Followed by clicking rather than by a hardcoded id, and the *right* activity
// is found by opening each one and reading its prompt. There are two on this
// course now and picking "the first /learn/activities/N link" would silently
// walk the auto-marked one — a check that passes while testing nothing.
await student.goto(`${BASE}/en/learn`, { waitUntil: 'networkidle' });
const courseHref = (await hrefs(student)).find((href) => /\/learn\/courses\/\d+/.test(href));
check('the course page is reachable from /learn', Boolean(courseHref), courseHref ?? 'no /learn/courses/N link');

let activityUrl = null;

if (courseHref) {
    await student.goto(new URL(courseHref, BASE).href, { waitUntil: 'networkidle' });
    const candidates = [...new Set((await hrefs(student)).filter((href) => /\/learn\/activities\/\d+/.test(href)))];

    for (const href of candidates) {
        const url = new URL(href, BASE).href;
        await student.goto(url, { waitUntil: 'networkidle' });
        if ((await text(student)).includes(PROMPT)) {
            activityUrl = url;
            break;
        }
    }
}

check(
    'the teacher-marked activity is reachable from the course',
    Boolean(activityUrl),
    activityUrl ?? 'no activity on the course page showed ' + PROMPT,
);

// Is there a fresh attempt to make?
//
// The player disables everything once an attempt is submitted, and offers no
// way back — **which is a defect in its own right**, because authors configure
// `retakes_allowed` / `retake_limit`, the server enforces them, and the
// teacher's own revision report advises "retry the weak item when retakes
// remain". There is no button.
//
// The consequence here is that this walk needs a clean attempt. Running it
// twice without reseeding used to spend thirty seconds inside
// `page.fill('textarea')` waiting for a disabled control and then die with a
// Playwright stack trace, which reads like a broken browser rather than what it
// is. Say it plainly and quickly instead.
const editable = activityUrl
    ? await student.locator('textarea').first().isEditable().catch(() => false)
    : false;

if (activityUrl && !editable) {
    check(
        'there is an unsubmitted attempt to walk',
        false,
        'the textarea is disabled — this activity was already submitted. '
            + 'Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder',
    );
}

if (activityUrl && editable) {
    await student.fill('textarea', ANSWER);
    await student.click('button:has-text("Submit")');
    const landed = await settles(student, 'submitted');

    const after = await text(student);
    check('handing it in is accepted and says so', landed, after.slice(0, 140));

    // The point of the pattern: the engine must **not** have marked it. If a
    // score appears here the queue would be empty and the rest of this walk
    // would pass by never having anything to do.
    check('the engine does not mark it itself', !/\b0\/5\b|\b\d+\/5\b/.test(after), after.slice(0, 160));
    check('there is no feedback yet', !after.includes('Teacher feedback'));
}

// ------------------------------------------------- the teacher, who may not

// The question the walk exists to ask, recorded as the **current** answer
// rather than as a failure.
//
// It used to assert a green 200 and therefore always failed, deliberately — and
// that made this walk permanently red, which is fine for a person reading it
// once and useless for `all.mjs`, where a walk that can never pass makes the
// whole run a false alarm for ever. A gate nobody can ever satisfy gets
// ignored, and then the real failures go with it.
//
// So it asserts the 403 that is true today. When the owner settles
// `OWNER_ACTIONS` item 16 and teachers gain the queue, **this step fails** and
// has to be changed on purpose — exactly like the Pest test beside it
// (`TeacherReviewLoopTest`), and exactly the behaviour a pinned decision wants.
const teacher = await signIn(TEACHER);
const teacherQueue = await teacher.goto(`${BASE}/en/catalog/reviews`, { waitUntil: 'networkidle' });
check(
    'a teacher is still shut out of the review queue (OWNER_ACTIONS item 16)',
    teacherQueue.status() === 403,
    `HTTP ${teacherQueue.status()} for ${TEACHER} — /catalog/reviews is gated on courses.manage, `
        + 'which the teacher role does not hold. Change this step when that is decided.',
);

// ----------------------------------------------------------------- the marker

const marker = await signIn(MARKER);
check('the marker signs in', !marker.url().includes('/login'), marker.url());

const queue = await marker.goto(`${BASE}/en/catalog/reviews`, { waitUntil: 'networkidle' });
const queueText = await text(marker);
check('the queue opens', queue.status() === 200, `HTTP ${queue.status()}`);
check('the submission is waiting in it', queueText.includes(ACTIVITY), queueText.slice(0, 160));
check('the marker can read what the student wrote', queueText.includes(ANSWER));

const card = marker.locator('article', { hasText: ACTIVITY }).first();

if (await card.count()) {
    await card.locator('input[aria-label="Score"]').fill(SCORE);
    await card.locator('input[aria-label="Max score"]').fill('5');
    await card.locator('input[placeholder="Feedback"]').fill(FEEDBACK);
    await card.locator('button:has-text("Score and release")').click();
    const saved = await settles(marker, 'Review saved.');

    const marked = await text(marker);
    check('marking it is accepted', saved, marked.slice(0, 140));
    check('it leaves the pending queue', !marked.includes(ANSWER), marked.slice(0, 140));
} else {
    check('marking it is accepted', false, 'no review card for ' + ACTIVITY);
    check('it leaves the pending queue', false, 'not marked');
}

// ------------------------------------------------------- back to the student

if (activityUrl) {
    await student.goto(activityUrl, { waitUntil: 'networkidle' });
    const returned = await text(student);
    check('the student sees the mark', returned.includes(`${SCORE}/5`), returned.slice(0, 160));
    check('the student sees the written feedback', returned.includes(FEEDBACK));
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
