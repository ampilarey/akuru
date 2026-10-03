/**
 * Can a teacher copy a whole course? (Moodle parity slice M1, STATUS §5oh.)
 * The owner, 2026-10-03, after asking whether Akuru has Moodle's course
 * building tools: "Yes build".
 *
 * Signed in as the dean, the walk:
 *
 *   1. reads the source course's outline — its modules, lessons and blocks;
 *   2. on the course catalog, presses Copy on that course's row, gives the
 *      copy a title and makes it;
 *   3. lands on the copy's outline, which says so, and finds every module,
 *      lesson and block of the source there, as a draft;
 *   4. back on the catalog, sees the copy as a draft next to the original,
 *      which is still published.
 *
 * Each run makes a new copy with its own title, so it can run twice.
 *
 *   node scripts/smoke/course-copy.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_AUTHOR, SMOKE_PASSWORD, SMOKE_SOURCE
 * (the source course's title, default SMOKE-Course), SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const AUTHOR = process.env.SMOKE_AUTHOR ?? 'headmaster@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SOURCE = process.env.SMOKE_SOURCE ?? 'SMOKE-Course';
const COPY = `SMOKE-Copy ${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}`;

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
// The first load's page object; the walk reloads after an Inertia visit.
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const shape = (modules) => (modules || []).map((m) => `${m.title}: ${(m.lessons || []).map((l) => `${l.title} (${(l.blocks || []).length} blocks)`).join(', ')}`).join(' | ');

const page = await browser.newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', AUTHOR);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

// 1. The source's outline.
await page.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const rows = (await props(page)).rows || [];
const source = rows.find((r) => r.title === SOURCE);
check(`the catalog lists ${SOURCE}`, Boolean(source), rows.map((r) => r.title).join(', '));
if (!source) {
    await browser.close();
    console.log(results.map(([s, ok, d]) => `${ok ? 'ok  ' : 'FAIL'}  ${s} ${d}`).join('\n'));
    process.exit(1);
}
await page.goto(`${BASE}/en/catalog/courses/${source.id}/outline`, { waitUntil: 'networkidle' });
const sourceShape = shape((await props(page)).modules);
check('the source has modules and lessons to copy', /\(\d+ blocks\)/.test(sourceShape), sourceShape);

// 2. Copy it from the catalog.
await page.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
await page.locator(`[data-testid="copy-course-${source.id}"]`).click();
const form = page.locator(`[data-testid="copy-course-form-${source.id}"]`);
check('Copy opens a title box, filled with "(copy)"', (await form.locator('input').inputValue()) === `${SOURCE} (copy)`);
check('it says what comes with the copy and what stays', /Enrolments, marks and progress stay with the original/.test(await form.innerText()));
await form.locator('input').fill(COPY);
await Promise.all([page.waitForURL(/\/catalog\/courses\/\d+\/outline/), form.getByRole('button', { name: 'Make the copy' }).click()]);
await page.waitForLoadState('networkidle');

// 3. The copy's outline.
check('the page says it was copied', (await page.locator('body').innerText()).includes(`Copied as a new draft: ${COPY}`));
await page.reload({ waitUntil: 'networkidle' });
const copied = await props(page);
check('it lands on the copy\'s outline', copied.course?.title === COPY && copied.course?.id !== source.id, page.url());
check('every module, lesson and block came with it', shape(copied.modules) === sourceShape, shape(copied.modules));
const lessonStates = (copied.modules || []).flatMap((m) => (m.lessons || []).map((l) => l.status));
check('the copy\'s lessons are drafts', lessonStates.length > 0 && lessonStates.every((s) => s === 'draft'), lessonStates.join(','));

// 4. Both on the catalog.
await page.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const after = (await props(page)).rows || [];
check('the catalog shows the copy as a draft', after.find((r) => r.title === COPY)?.workflow_status === 'draft');
check('the original is still published', after.find((r) => r.id === source.id)?.workflow_status === 'published');

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(56)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
