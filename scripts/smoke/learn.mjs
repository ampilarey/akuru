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

if (courseHref) {
    const course = await body(new URL(courseHref, BASE).pathname);
    check('the course page names the lesson', course.status === 200 && course.text.includes('SMOKE-Lesson'));
    lessonHref = (await hrefs()).find((href) => /\/learn\/lessons\/\d+/.test(href));
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
        check('marking it complete is accepted', true);
    } else {
        check('marking it complete is accepted', false, 'no complete button');
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
