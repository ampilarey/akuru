/**
 * Is an upload refused in the page's language? (BACKLOG C21, slice MD1,
 * STATUS §5ri.)
 *
 * Every upload form stores its file through the Media actions, and the
 * course outline is where a person meets their refusal: a block's type
 * allows fewer kinds of file than the form's outer rule. The smoke seed's
 * head teacher opens a planted course's outline under /dv and /ar, adds an
 * image block, chooses a PDF, and is told under the form that the kind of
 * file cannot be uploaded there, in the page's language and without the
 * file's MIME type (*File type application/pdf is not allowed for this
 * block.*). An image is then accepted.
 *
 * The planted course is removed first and last. Local server only.
 *
 *   node scripts/smoke/upload-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_AUTHOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const AUTHOR = process.env.SMOKE_AUTHOR ?? 'headmaster@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const TITLE = 'SMOKE MD1 Course';
const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');

if (!LOCAL) {
    console.log('upload-language plants a course with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const C = '\\App\\Domains\\Courses';
const cleanUp = () => tinker(`foreach (${C}\\Models\\Course::withTrashed()->where('title', '${TITLE}')->get() as $course) { $lessons = DB::table('lessons')->whereIn('course_module_id', DB::table('course_modules')->where('course_id', $course->id)->pluck('id'))->pluck('id'); DB::table('content_blocks')->whereIn('lesson_id', $lessons)->delete(); DB::table('lessons')->whereIn('id', $lessons)->delete(); DB::table('course_modules')->where('course_id', $course->id)->delete(); $course->forceDelete(); } echo 'ok';`);

cleanUp();
const courseId = tinker(`$by = \\App\\Domains\\Identity\\Models\\User::where('email', '${AUTHOR}')->value('id'); $course = app(${C}\\Actions\\SaveEngineCourseAction::class)->execute(['title' => '${TITLE}', 'subject_id' => ${C}\\Models\\CourseSubject::query()->where('slug', 'arabic')->value('id'), 'created_by' => $by]); $module = app(${C}\\Actions\\SaveCourseModuleAction::class)->execute(['course_id' => $course->id, 'title' => 'SMOKE MD1 Module', 'created_by' => $by]); app(${C}\\Actions\\SaveLessonAction::class)->execute(['course_module_id' => $module->id, 'title' => 'SMOKE MD1 Lesson', 'created_by' => $by]); echo $course->id;`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(AUTHOR);
await page.locator('input[name="password"]').fill(PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
check('the head teacher signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

for (const locale of ['dv', 'ar']) {
    await page.goto(`${BASE}/${locale}/catalog/courses/${courseId}/outline`, { waitUntil: 'networkidle' });
    const typeLabel = say('teach.outline_block_type', locale);
    const blockType = page.locator(`select[aria-label="${typeLabel}"]`);
    const blockForm = page.locator('form', { has: blockType });
    check(`/${locale} the outline offers a block form`, await blockType.count() === 1, typeLabel);
    await blockType.selectOption('image');
    await blockForm.locator('input[type=file]').setInputFiles({ name: 'notes.pdf', mimeType: 'application/pdf', buffer: PDF });
    await blockForm.locator('button[type="submit"]').click();
    const refusal = say('common.upload_error_type', locale);
    const said = await blockForm.locator('p.text-red-600', { hasText: refusal }).first().waitFor({ timeout: 8000 }).then(() => true).catch(() => false);
    const form = flat(await blockForm.textContent());
    check(`/${locale} a PDF for an image block is refused in the page's language`, said, form.slice(-200));
    check(`/${locale} the refusal names no MIME type`, !form.includes('application/pdf') && !/File type/.test(form));
}

// The right kind of file is still taken.
const blockType = page.locator(`select[aria-label="${say('teach.outline_block_type', 'ar')}"]`);
const blockForm = page.locator('form', { has: blockType });
await blockType.selectOption('image');
await blockForm.locator('input[type=file]').setInputFiles({ name: 'dot.png', mimeType: 'image/png', buffer: Buffer.from(ONE_PIXEL_PNG, 'base64') });
await blockForm.locator('button[type="submit"]').click();
await page.waitForLoadState('networkidle');
await page.waitForTimeout(500);
check('an image is accepted', tinker(`echo DB::table('content_blocks')->whereIn('lesson_id', DB::table('lessons')->whereIn('course_module_id', DB::table('course_modules')->where('course_id', ${courseId})->pluck('id'))->pluck('id'))->where('type', 'image')->count();`) === '1');

await browser.close();
cleanUp();

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
