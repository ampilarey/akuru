/**
 * Does a rubric mark a piece of work? (Moodle parity slice M2, STATUS §5oi.)
 * The owner, 2026-10-03, on Moodle's course-building tools: "Yes build".
 *
 *   1. the dean opens SMOKE-Course's rubrics from the catalog, builds one with
 *      two criteria (Content: Not yet 0 · Good 2 · Excellent 4; Language:
 *      Weak 0 · Strong 4), and has it mark SMOKE-Review-Activity;
 *   2. the student hands in that activity;
 *   3. the dean, on the review queue, sees the rubric instead of a score box,
 *      picks Excellent and Weak, is told 4 of 8 points so 3 out of 5, and
 *      releases it;
 *   4. the student sees 3/5 and how it was marked, criterion by criterion;
 *   5. the dean deletes the rubric, and the activity goes back to a typed
 *      score — so `review.mjs`, which types one, still walks.
 *
 * The student needs an unsubmitted attempt, so reseed first:
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/rubric.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_MARKER, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const MARKER = process.env.SMOKE_MARKER ?? 'headmaster@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const COURSE = 'SMOKE-Course';
const ACTIVITY = 'SMOKE-Review-Activity';
const RUBRIC = 'SMOKE-Rubric';
const ANSWER = 'SMOKE-Rubric-Answer: a lagoon, a reef, a harbour.';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

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

// ------------------------------------------------------- 1. build the rubric

const dean = await signIn(MARKER);
await dean.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const course = ((await props(dean)).rows || []).find((r) => r.title === COURSE);
const link = course ? dean.locator(`a[href="/catalog/courses/${course.id}/rubrics"]`) : null;
check('the catalog links to the course\'s rubrics', Boolean(link) && (await link.count()) === 1);
await link.click();
await dean.waitForURL(/\/rubrics$/);
await dean.waitForLoadState('networkidle');
await dean.reload({ waitUntil: 'networkidle' });
const page1 = await props(dean);
const activity = (page1.activities || []).find((a) => a.title === ACTIVITY);
check('the rubrics page lists the teacher-marked activity', Boolean(activity?.teacher_marked), JSON.stringify(page1.activities));
// A rubric left by an earlier, broken run would be a second one; clear it.
for (const old of (page1.rubrics || []).filter((r) => r.title === RUBRIC)) {
    dean.once('dialog', (d) => d.accept());
    await dean.locator('[data-testid="rubric-card"]', { hasText: RUBRIC }).getByRole('button', { name: 'Delete' }).click();
    await dean.waitForLoadState('networkidle');
}

await dean.locator('[data-testid="rubric-new"]').click();
const form = dean.locator('[data-testid="rubric-form"]');
await form.locator('input[name="title"]').fill(RUBRIC);
check('a new rubric starts with a criterion of three levels', (await form.locator('[data-testid="rubric-criterion"]').count()) === 1
    && (await form.locator('[data-testid="rubric-criterion"]').first().locator('input[aria-label^="Level"]').count()) === 3);
await form.getByRole('button', { name: '+ Add a criterion' }).click();
const second = form.locator('[data-testid="rubric-criterion"]').nth(1);
await second.locator('input').first().fill('Language');
await second.locator('input[aria-label="Level 1"]').fill('Weak');
await second.locator('input[aria-label="Level 2"]').fill('Strong');
await second.locator('input[aria-label="Points"]').nth(1).fill('4');
check('it adds up the best possible', (await form.innerText()).includes('Best possible: 8 points'));
await form.locator('label', { hasText: ACTIVITY }).locator('input[type=checkbox]').check();
await form.getByRole('button', { name: 'Save rubric' }).click();
await dean.waitForLoadState('networkidle');
await dean.waitForTimeout(500);
const saved = dean.locator('[data-testid="rubric-card"]', { hasText: RUBRIC });
check('the rubric is saved and says what it marks', (await saved.count()) === 1 && (await saved.innerText()).includes(`It marks: ${ACTIVITY}`), (await text(dean)).slice(0, 200));

// ------------------------------------------------------ 2. the student hands in

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/learn/activities/${activity?.id}`, { waitUntil: 'networkidle' });
const editable = await student.locator('textarea').first().isEditable().catch(() => false);
check('the student has an unsubmitted attempt (reseed if not)', editable);
if (editable) {
    await student.fill('textarea', ANSWER);
    await student.click('button:has-text("Submit")');
    await student.waitForLoadState('networkidle');
    await student.waitForTimeout(500);
}

// ------------------------------------------------------- 3. marked by rubric

await dean.goto(`${BASE}/en/catalog/reviews`, { waitUntil: 'networkidle' });
const card = dean.locator('article', { hasText: ANSWER }).first();
check('the submission is in the queue', (await card.count()) === 1);
const marker = card.locator('[data-testid="rubric-marker"]');
check('it shows the rubric, not a score box', (await marker.count()) === 1 && (await card.locator('input[aria-label="Score"]').count()) === 0);
await marker.locator('tr', { hasText: 'Content' }).locator('label', { hasText: 'Excellent' }).click();
await marker.locator('tr', { hasText: 'Language' }).locator('label', { hasText: 'Weak' }).click();
const total = await card.locator('[data-testid="rubric-total"]').innerText();
check('it says the mark before saving', total.includes('Rubric: 4 of 8 points, so 3 out of 5'), total);
await card.locator('input[placeholder="Feedback"]').fill('SMOKE-Rubric-Feedback: strong ideas.');
await card.locator('button:has-text("Score and release")').click();
await dean.waitForLoadState('networkidle');
await dean.waitForTimeout(500);
check('it is released and leaves the queue', (await text(dean)).includes('Review saved.') && !(await text(dean)).includes(ANSWER));

// ------------------------------------------------------- 4. the student sees it

await student.goto(`${BASE}/en/learn/activities/${activity?.id}`, { waitUntil: 'networkidle' });
const seen = await text(student);
const result = student.locator('[data-testid="rubric-result"]');
check('the student sees the mark', seen.includes('3/5'), seen.slice(0, 200));
check('and how it was marked, criterion by criterion', (await result.count()) === 1
    && /Content\s*Excellent\s*4 \/ 4/.test((await result.innerText()).replace(/\s+/g, ' '))
    && /Language\s*Weak\s*0 \/ 4/.test((await result.innerText()).replace(/\s+/g, ' ')), (await result.innerText().catch(() => '')).replace(/\s+/g, ' '));

// -------------------------------------------------------------- 5. clean up

dean.once('dialog', (d) => d.accept());
await dean.goto(`${BASE}/en/catalog/courses/${course?.id}/rubrics`, { waitUntil: 'networkidle' });
await dean.locator('[data-testid="rubric-card"]', { hasText: RUBRIC }).getByRole('button', { name: 'Delete' }).click();
await dean.waitForLoadState('networkidle');
await dean.waitForTimeout(500);
await dean.reload({ waitUntil: 'networkidle' });
const after = await props(dean);
check('deleting it puts the activity back on a typed score', !(after.rubrics || []).some((r) => r.title === RUBRIC));
await student.reload({ waitUntil: 'networkidle' });
check('the mark already given keeps its rubric', (await student.locator('[data-testid="rubric-result"]').count()) === 1);

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(58)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
