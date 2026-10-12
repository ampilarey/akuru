/**
 * Does the office choose a person by name, not by a typed id? (BACKLOG C22,
 * slice BH1, STATUS §5rw.)
 *
 * The system admin opens the behaviour form under /dv, types two letters of a
 * seeded pupil's name, chooses the pupil from the matches and records a
 * compliment, which lands against that pupil. The plan form offers the
 * teachers by name, the fee adjustment form finds the pupil by name, and the
 * wallet hand credit finds an account by email. Nothing is credited and no
 * adjustment saved (a credit is append-only, rule 12); the behaviour record
 * the walk makes is removed after. Local server only.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('pick-by-name reads and removes rows with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);

const pupil = JSON.parse(tinker(`$s = DB::table('students')->whereNotNull('first_name')->where('first_name', '!=', '')->orderBy('id')->first(['id', 'first_name']); echo json_encode($s);`));
const startedAt = tinker(`echo now()->toDateTimeString();`);
const cleanUp = () => {
    tinker(`DB::table('behavior_records')->where('description', 'Smoke: helped a classmate')->where('created_at', '>=', '${startedAt}')->delete(); echo 'ok';`);
};

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

try {
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(OFFICE);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);

    // The behaviour form, in Dhivehi.
    await page.goto(`${BASE}/dv/academics/behavior`, { waitUntil: 'networkidle' });
    const picker = page.locator('[data-testid="behavior-pupil"]');
    check('the behaviour form asks for the student by name, in Dhivehi', (await picker.count()) === 1 && (await picker.locator('span').first().textContent())?.trim() === say('academics.behavior_student'));
    await picker.locator('[data-testid="behavior-pupil-search"]').fill(pupil.first_name.slice(0, 3));
    await page.waitForFunction(() => document.querySelectorAll('[data-testid="behavior-pupil-matches"] button').length > 0, null, { timeout: 8000 });
    const matches = picker.locator('[data-testid="behavior-pupil-matches"] button');
    check('typing three letters lists the pupils whose names match', (await matches.count()) > 0, String(await matches.count()));
    const target = matches.filter({ hasText: pupil.first_name }).first();
    await target.click();
    check('choosing one shows whom the record is for', ((await picker.locator('[data-testid="behavior-pupil-chosen"]').textContent()) ?? '').includes(pupil.first_name));
    await page.locator('input[aria-label="' + say('academics.behavior_description') + '"]').fill('Smoke: helped a classmate');
    await page.locator('input[type="date"]').first().fill(new Date().toISOString().slice(0, 10));
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form button[type="submit"]').first().click()]);
    await page.waitForTimeout(500);
    const saved = JSON.parse(tinker(`echo json_encode(DB::table('behavior_records')->where('description', 'Smoke: helped a classmate')->where('created_at', '>=', '${startedAt}')->pluck('student_id'));`));
    const chosenFirstName = saved.length === 1 ? tinker(`echo DB::table('students')->where('id', ${saved[0]})->value('first_name');`) : '';
    check('the record is filed against the pupil chosen', saved.length === 1 && chosenFirstName === pupil.first_name, JSON.stringify(saved));

    // The plan form offers the teachers by name.
    await page.goto(`${BASE}/en/academics/plans`, { waitUntil: 'networkidle' });
    const teacher = page.locator('[data-testid="plan-teacher"]');
    const teacherCount = await teacher.locator('option').count();
    check('the plan form offers the teachers by name, not a box for an id', (await teacher.count()) === 1 && teacherCount > 1, String(teacherCount));

    // The fee adjustment form finds the pupil by name.
    await page.goto(`${BASE}/en/finance/adjustments`, { waitUntil: 'networkidle' });
    await page.locator('[data-testid="adjustment-pupil-search"]').fill(pupil.first_name.slice(0, 3));
    await page.waitForFunction(() => document.querySelectorAll('[data-testid="adjustment-pupil-matches"] button').length > 0, null, { timeout: 8000 });
    check('the fee adjustment form finds the pupil by name', (await page.locator('[data-testid="adjustment-pupil-matches"] button').filter({ hasText: pupil.first_name }).count()) > 0);

    // The wallet hand credit finds the account by email; nothing is credited.
    await page.goto(`${BASE}/en/admin/commerce`, { waitUntil: 'networkidle' });
    await page.locator('[data-testid="credit-account-search"]').fill(CUSTOMER.split('@')[0]);
    await page.waitForFunction(() => document.querySelectorAll('[data-testid="credit-account-matches"] button').length > 0, null, { timeout: 8000 });
    const account = page.locator('[data-testid="credit-account-matches"] button').filter({ hasText: CUSTOMER }).first();
    check('the wallet credit finds the account by its email', (await account.count()) === 1);
    await account.click();
    check('and choosing it names the account, with no id typed', ((await page.locator('[data-testid="credit-account-chosen"]').textContent()) ?? '').includes(CUSTOMER));
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
    cleanUp();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
