/**
 * Can a class talk about its course? (Moodle parity slice M3, STATUS §5oj.)
 * The owner, 2026-10-03, on Moodle's course-building tools: "Yes build".
 *
 *   1. the student opens SMOKE-Course from /learn, follows *Discussion* and
 *      starts a topic;
 *   2. the teacher (assigned to SMOKE-Course by the seeder) finds the
 *      course's forum from Teacher review, reads the topic, replies, pins it
 *      and locks it;
 *   3. the student sees the reply marked *Teacher*, the topic pinned, no reply
 *      box and a note saying why, and a notice of the reply;
 *   4. the teacher hides the topic, and the student's list no longer has it —
 *      so a second run starts clean.
 *
 *   node scripts/smoke/forum.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_TEACHER, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const COURSE = 'SMOKE-Course';
const STAMP = new Date().toISOString().slice(11, 19);
const TOPIC = `SMOKE-Forum-Topic ${STAMP}`;
const BODY = 'SMOKE-Forum-Body: which lesson covers the sun letters?';
const REPLY = 'SMOKE-Forum-Reply: lesson two, with the drills.';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');
async function shows(page, needle, ms = 5000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await text(page)).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(200);
    }
    return false;
}
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});

async function signIn(email) {
    const page = await (await browser.newContext()).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

// ------------------------------------------------------- 1. the student starts

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/learn`, { waitUntil: 'networkidle' });
// Every course link on /learn, opened until one is SMOKE-Course (other walks
// enrol this student on courses of their own; see review.mjs).
const courseHrefs = [...new Set((await student.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || '')))
    .filter((href) => /\/learn\/courses\/\d+$/.test(href)))];
let found = false;
for (const href of courseHrefs) {
    await student.goto(new URL(href, BASE).href, { waitUntil: 'networkidle' });
    if ((await props(student)).course?.title === COURSE) {
        found = true;
        break;
    }
}
check('the student finds SMOKE-Course from /learn', found, courseHrefs.join(', '));
const discussion = student.locator('[data-testid="course-forum-link"]');
check('the course page offers Discussion', (await discussion.count()) === 1, student.url());
await discussion.click();
await student.waitForLoadState('networkidle');
check('it opens the course forum', /\/learn\/courses\/\d+\/forum$/.test(student.url()), student.url());
const forumUrl = student.url();
const newTopic = student.locator('[data-testid="forum-new-topic"]');
await newTopic.locator('input[name="title"]').fill(TOPIC);
await newTopic.locator('textarea[name="body"]').fill(BODY);
await newTopic.getByRole('button', { name: 'Post' }).click();
await student.waitForURL(/\/forum\/\d+$/);
await student.waitForLoadState('networkidle');
check('starting a topic opens it', (await text(student)).includes(TOPIC) && (await text(student)).includes(BODY));
const topicUrl = student.url();

// ------------------------------------------------------- 2. the teacher answers

const teacher = await signIn(TEACHER);
await teacher.goto(`${BASE}/en/catalog/reviews`, { waitUntil: 'networkidle' });
const forums = teacher.locator('[data-testid="review-forums"]');
check('Teacher review links the teacher\'s course forums', (await forums.count()) === 1 && (await forums.innerText()).includes(COURSE));
await forums.locator('a', { hasText: COURSE }).click();
await teacher.waitForLoadState('networkidle');
check('the teacher is told they moderate it', (await teacher.locator('[data-testid="forum-moderator"]').count()) === 1);
await teacher.locator('a', { hasText: TOPIC }).click();
await teacher.waitForLoadState('networkidle');
check('the teacher reads the topic', (await text(teacher)).includes(BODY));
const reply = teacher.locator('[data-testid="forum-reply"]');
await reply.locator('textarea[name="body"]').fill(REPLY);
await reply.getByRole('button', { name: 'Reply' }).click();
check('the reply is posted', await shows(teacher, REPLY));
await teacher.locator('[data-testid="forum-moderate"]').getByRole('button', { name: 'Pin' }).click();
await teacher.waitForLoadState('networkidle');
await teacher.waitForTimeout(400);
await teacher.locator('[data-testid="forum-moderate"]').getByRole('button', { name: 'Lock' }).click();
await teacher.waitForLoadState('networkidle');
await teacher.waitForTimeout(400);
const moderated = await teacher.locator('[data-testid="forum-moderate"]').innerText();
check('pinned and locked', moderated.includes('Unpin') && moderated.includes('Unlock'), moderated);

// ------------------------------------------------------- 3. the student sees it

await student.goto(topicUrl, { waitUntil: 'networkidle' });
const post = student.locator('[data-testid="forum-posts"] li', { hasText: REPLY });
check('the student sees the reply, marked Teacher', (await post.count()) === 1 && (await post.innerText()).includes('Teacher'));
check('a locked topic has no reply box, and says why', (await student.locator('[data-testid="forum-reply"]').count()) === 0
    && (await student.locator('[data-testid="forum-locked"]').count()) === 1);
await student.goto(forumUrl, { waitUntil: 'networkidle' });
const first = student.locator('[data-testid="forum-topics"] li').first();
check('the topic is pinned to the top', (await first.innerText()).includes(TOPIC) && (await first.innerText()).includes('Pinned'));
const notices = (await (await student.request.get(`${BASE}/en/portal/notifications`)).text());
check('the student was told of the reply', notices.includes(`New reply: ${TOPIC}`));

// ------------------------------------------------------- 4. cleaned up

await teacher.locator('[data-testid="forum-moderate"]').getByRole('button', { name: 'Hide' }).click();
await teacher.waitForLoadState('networkidle');
await teacher.waitForTimeout(400);
await student.reload({ waitUntil: 'networkidle' });
check('a hidden topic leaves the student\'s list', !(await text(student)).includes(TOPIC));
const hidden = await props(teacher);
check('the teacher still sees it, marked hidden', hidden.topic?.hidden === true || (await teacher.reload({ waitUntil: 'networkidle' }).then(() => props(teacher))).topic?.hidden === true);

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(56)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
