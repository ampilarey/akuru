/**
 * Can a teacher fill a name-tag list from their own class on a phone, in
 * Dhivehi — and does a parent not get the choice? (PRINT_PRODUCTS_PLAN
 * PP6a, STATUS §5tn.)
 *
 * With the print desk on (set with tinker), a *Plain* name-tag job is
 * started for the teacher and one for a parent. At 390 × 844 under /dv the
 * teacher finds *From a class* with their own class only, fills the list,
 * and the job is saved with one tag per pupil, each with the class. The
 * parent's job page has no *From a class*. Nothing scrolls sideways. The
 * desk and the jobs are put back. Local server only.
 *
 *   node scripts/smoke/print-class.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_TEACHER, SMOKE_CUSTOMER, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PARENT = process.env.SMOKE_CUSTOMER ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const MARK = 'SMOKE PP6a';

if (!LOCAL) {
    console.log('print-class changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo 'ok';`);
const before = tinker(`echo DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value') ?? 'none';`);
const paper = JSON.parse(tinker(`echo json_encode(DB::table('print_paper_options')->where('kind', 'plain')->where('sheet_size', 'a4')->first(['id', 'price_colour', 'price_bw', 'is_active']));`));
const restore = () => tinker(`DB::table('print_jobs')->where('name', '${MARK}')->delete(); DB::table('print_paper_options')->where('id', ${paper.id})->update(['price_colour' => ${paper.price_colour ?? 0}, 'price_bw' => ${paper.price_bw ?? 0}, 'is_active' => ${paper.is_active ? 1 : 0}]); ${before === 'none' ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${before}']);`} Cache::flush(); echo 'ok';`);
tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); DB::table('print_paper_options')->where('id', ${paper.id})->update(['price_colour' => 2.00, 'price_bw' => 1.00, 'is_active' => true]); Cache::flush(); echo 'ok';`);
const start = (email) => tinker(`$u = DB::table('users')->where('email', '${email}')->value('id'); $j = app(\\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintJobAction::class)->start(\\App\\Domains\\Bookshop\\Models\\PrintTemplate::query()->where('title', 'Plain')->firstOrFail(), $u, null, 'dv'); $j->update(['name' => '${MARK}']); echo $j->id;`);
// Started after the paper is priced, so the jobs start on it.
const teacherJob = start(TEACHER);
const parentJob = start(PARENT);
const own = JSON.parse(tinker(`echo json_encode(app(\\App\\Domains\\Academics\\Actions\\ListClassesTaughtByUserAction::class)->execute(DB::table('users')->where('email', '${TEACHER}')->value('id'))->map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'pupils' => count($c['student_ids'])])->values());`));

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const signIn = async (who) => {
    const page = await (await browser.newContext(phone)).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    return page;
};

try {
    check('the teacher has a class to try with', own.length > 0 && own[0].pupils > 0, JSON.stringify(own));
    const teacher = await signIn(TEACHER);
    await teacher.goto(`${BASE}/dv/shop/print/jobs/${teacherJob}`, { waitUntil: 'networkidle' });
    await teacher.locator('[data-testid="print-mode-list"]').check();
    const options = await teacher.locator('[data-testid="print-from-class-select"] option').allTextContents();
    check('/dv the teacher finds From a class, in Dhivehi, with their own classes only', flat(await teacher.locator('[data-testid="print-from-class"]').textContent()).includes(say('print.from_class', 'dv'))
        && options.length === own.length + 1 && own.every((c) => options.includes(c.name)), options.join(' | '));
    await teacher.locator('[data-testid="print-from-class-select"]').selectOption(String(own[0].id));
    await teacher.locator('[data-testid="print-from-class-fill"]').click();
    await teacher.waitForFunction(() => document.querySelector('[data-testid="print-job-saved"]')?.textContent?.length > 0, null, { timeout: 10000 }).catch(() => {});
    await teacher.waitForTimeout(1500);
    const lines = (await teacher.locator('[data-testid="print-list"]').inputValue()).split('\n').filter((l) => l.trim() !== '');
    check(`the list fills with the class's ${own[0].pupils} pupils`, lines.length === own[0].pupils, `${lines.length} lines`);
    const saved = JSON.parse(tinker(`$e = json_decode(DB::table('print_jobs')->where('id', ${teacherJob})->value('entry'), true); echo json_encode(['mode' => $e['mode'], 'count' => count($e['list']), 'classes' => array_values(array_unique(array_column($e['list'], 'class')))]);`));
    check('the job is saved with one tag per pupil, each with the class', saved.mode === 'list' && saved.count === own[0].pupils && saved.classes.length === 1 && saved.classes[0] === own[0].name, JSON.stringify(saved));
    check('/dv the teacher\'s job page does not scroll sideways at 390 px', await sideways(teacher) <= 1, `${await sideways(teacher)} px`);
    await teacher.locator('[data-testid="print-from-class"]').scrollIntoViewIfNeeded();
    if (SHOTS) await teacher.screenshot({ path: `${SHOTS}/pp6a-teacher-dv.png` });

    const parent = await signIn(PARENT);
    await parent.goto(`${BASE}/dv/shop/print/jobs/${parentJob}`, { waitUntil: 'networkidle' });
    await parent.locator('[data-testid="print-mode-list"]').check();
    check('/dv a parent has no From a class', await parent.locator('[data-testid="print-from-class"]').count() === 0);
    const refused = await parent.request.get(`${BASE}/dv/shop/print/jobs/${parentJob}/class-names?class=${own[0].id}`, { headers: { Accept: 'application/json' } });
    check('and asking for the class\'s names anyway is refused', refused.status() === 422, String(refused.status()));
} finally {
    await browser.close();
    restore();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
