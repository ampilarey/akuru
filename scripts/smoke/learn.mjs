/**
 * Can a student actually take a lesson?
 *
 * `sweep.mjs` asks whether a screen shows a planted row, signed in as an
 * administrator. Every one of its checks is a screen that *administers* the
 * school. This one is the other side: a **student** signs in, finds the course
 * they are enrolled on, opens a lesson, reads what the teacher published, and
 * marks it complete — and the run then checks the database recorded it.
 *
 * ## Why it did not exist before
 *
 * Because there was nothing to walk. Before `SmokeMarkerSeeder::learner()` the
 * local database held **zero** course modules, zero lessons, zero content
 * blocks, zero published revisions and zero enrolments — and **zero of fifteen
 * students were linked to a login**, so `ResolveStudentForUserAction` answered
 * null for the seeded student and the entire learner path was unreachable to
 * anybody walking a seeded app. That includes the operator walking staging.
 *
 * ## What it proves, and what it does not
 *
 * It proves the round trip: a lesson published through `PublishLessonAction`
 * reaches an enrolled student's screen, and `learn.lessons.complete` writes a
 * `student_lesson_progress` row against the revision they actually read.
 *
 * It does **not** prove the authoring screens, the outline editor, activities,
 * assessments, unlock rules, seats, pinning or the PWA. One lesson, one block,
 * one student.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/learn.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const USER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const COURSE = 'SMOKE-Course';
const BODY = 'SMOKE-Lesson-Body';

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

const context = await browser.newContext();
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));

const page = await context.newPage();
const problems = [];
page.on('pageerror', (error) => problems.push('page error: ' + String(error).slice(0, 140)));
page.on('response', (response) => {
    if (response.status() >= 500) {
        problems.push('HTTP ' + response.status() + ' ' + response.url());
    }
});

const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', USER);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
check('signs in', !page.url().includes('/login'), page.url());

const body = async (path) => {
    const response = await page.goto(BASE + path, { waitUntil: 'networkidle' });
    return { status: response.status(), text: (await page.innerText('body')).replace(/\s+/g, ' ') };
};

const dashboard = await body('/en/learn');
check('their course is on /learn', dashboard.status === 200 && dashboard.text.includes(COURSE));

const catalog = await body('/en/learn/catalog');
check('the course is in the learner catalog', catalog.status === 200 && catalog.text.includes(COURSE));

// Followed by clicking, not by a hardcoded id, so the run also says whether a
// student can *get* to the lesson from where they land. The route is
// /learn → the course → the lesson; the dashboard itself links to the course,
// not to a lesson, which is why an earlier version of this script reported a
// failure that was only its own shortcut.
const hrefs = async () => page.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || ''));

await page.goto(`${BASE}/en/learn`, { waitUntil: 'networkidle' });
const courseHref = (await hrefs()).find((href) => /\/learn\/courses\/\d+/.test(href));
check('the course page is reachable from /learn', Boolean(courseHref), courseHref ?? 'no /learn/courses/N link');

let lessonHref = null;
let activityHref = null;

if (courseHref) {
    const course = await body(new URL(courseHref, BASE).pathname);
    check('the course page names the lesson', course.status === 200 && course.text.includes('SMOKE-Lesson'));

    // Both are collected here because both hang off the **course** page.
    // An earlier version looked for the activity on the lesson and reported a
    // failure that was only its own assumption — the second time this script
    // has guessed the route wrong, which is itself worth knowing about the
    // information architecture.
    const links = await hrefs();
    lessonHref = links.find((href) => /\/learn\/lessons\/\d+/.test(href));
    activityHref = links.find((href) => /\/learn\/activities\/\d+/.test(href));
}

if (!lessonHref) {
    check('a lesson is reachable from the course', false, 'no /learn/lessons/N link on the course page');
} else {
    const lesson = await body(new URL(lessonHref, BASE).pathname);
    check('the lesson shows what was published', lesson.status === 200 && lesson.text.includes(BODY), lessonHref);

    const complete = page.locator('button:has-text("Complete"), button:has-text("complete")').first();
    if (await complete.count()) {
        await complete.click();
        await page.waitForLoadState('networkidle');

        // Assert the consequence, not the click. This was a literal
        // `check(..., true)` — it passed whenever a Complete button existed,
        // which is to say it tested nothing. The course page prints the
        // enrolment's progress, and one completed lesson out of one is 100%.
        const after = await body(new URL(courseHref, BASE).pathname);
        check(
            'marking it complete moves the progress bar',
            after.text.includes('100%'),
            (after.text.match(/\d+%/) ?? ['no percentage on the course page'])[0],
        );
    } else {
        check('marking it complete moves the progress bar', false, 'no complete button');
    }
}

// The activity. Reading a lesson is the passive half; this is the half where a
// student does something and the system marks it.
if (!activityHref) {
    check('an activity is reachable from the course', false, 'no /learn/activities/N link');
} else {
    const activity = await body(new URL(activityHref, BASE).pathname);
    check('the activity shows its question', activity.status === 200 && activity.text.includes('SMOKE-Question'), activityHref);

    // Answer it the way a student does: pick the option, press submit.
    const option = page.locator('label:has-text("SMOKE-Right"), input[value="a"]').first();
    // The player disables every control once an attempt is submitted and offers
    // no way back, so a second run without a re-seed used to spend thirty
    // seconds inside click() and then die with a Playwright stack trace that
    // reads like a broken browser. Say what it is, in a second.
    //
    // (That there is no way back is a defect in its own right: authors set
    // `retakes_allowed` and `retake_limit`, the server creates attempt two
    // happily, and the teacher's revision report advises retrying. No player
    // offers a button.)
    const enabled = (await option.count()) > 0 && await option.isEnabled().catch(() => false);

    if (!enabled) {
        check(
            'the answer is accepted',
            false,
            (await option.count()) === 0
                ? 'no option to choose'
                : 'the option is disabled — this activity was already submitted. '
                    + 'Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder',
        );
    } else {
        await option.click();
        const submit = page.locator('button:has-text("Submit"), button[type=submit]').first();
        if (await submit.count()) {
            await submit.click();

            // Assert the mark, not the click. This step used to be a literal
            // `check(..., true)` — it passed whenever a submit button existed,
            // which is to say it tested nothing at all. `selection` is the one
            // pattern the engine scores itself, so the right answer must come
            // back scored 1/1.
            const deadline = Date.now() + 5000;
            let marked = '';
            while (Date.now() < deadline) {
                marked = (await page.innerText('main')).replace(/\s+/g, ' ');
                if (marked.includes('scored')) {
                    break;
                }
                await page.waitForTimeout(100);
            }

            check(
                'the answer is accepted and marked right',
                marked.includes('scored') && marked.includes('1/1'),
                marked.slice(0, 160),
            );
        } else {
            check('the answer is accepted', false, 'no submit button');
        }
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
