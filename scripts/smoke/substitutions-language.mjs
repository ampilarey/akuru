/**
 * Do the substitution requests and the teacher absences work, and read in
 * Dhivehi? (BACKLOG C21, slice TS1, STATUS §5rh.)
 *
 * Under /dv, with two planted open requests for tomorrow:
 *   - the office opens the list: right to left, its heading, columns and a
 *     request's state in Dhivehi, with New request and the absences offered
 *     (the button showed to nobody, and nothing linked the absences);
 *   - the office changes the first request and is told so once, in Dhivehi
 *     (a save was always refused as "assigned or closed");
 *   - the smoke seed's teacher takes it from the list and is told so in
 *     Dhivehi (Take was always refused);
 *   - the office assigns the second from its page (Assign had no form);
 *   - the office records a teacher's absence: the list's heading and its
 *     Record button are there, and the absence is said once, with its state
 *     and month in Dhivehi.
 *
 * The planted rows are removed first and last. Local server only.
 *
 *   node scripts/smoke/substitutions-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_TEACHER, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const MARK = 'SMOKE TS1';

if (!LOCAL) {
    console.log('substitutions-language plants rows with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();
const path = (page) => new URL(page.url()).pathname;
const thaana = (s) => /[ހ-޿]/.test(s ?? '') && !/[A-Za-z]{3,}/.test(s ?? '');

const M = '\\App\\Domains\\Academics\\Models';
const cleanUp = () => tinker(`$ids = ${M}\\SubstitutionRequest::query()->where('notes', 'like', '${MARK}%')->pluck('id'); ${M}\\SubstitutionAssignment::query()->whereIn('substitution_request_id', $ids)->get()->each->delete(); ${M}\\SubstitutionRequest::query()->whereIn('id', $ids)->delete(); ${M}\\TeacherAbsence::query()->where('reason', 'like', '${MARK}%')->delete(); echo 'ok';`);

cleanUp();
const planted = tinker(`$mine = \\App\\Domains\\Identity\\Models\\User::where('email', '${TEACHER}')->first()->teacher->id; $absent = \\App\\Domains\\People\\Models\\Teacher::where('id', '!=', $mine)->first(); $ids = []; foreach (['A', 'B'] as $n) { $ids[] = ${M}\\SubstitutionRequest::query()->create(['date' => now()->addDay()->toDateString(), 'absent_teacher_id' => $absent->id, 'subject_id' => ${M}\\Subject::first()->id, 'classroom_id' => ${M}\\ClassRoom::first()->id, 'period_id' => ${M}\\Period::orderBy('start_time')->first()->id, 'status' => 'open', 'notes' => '${MARK} '.$n])->id; } echo implode(',', $ids);`);
const [first, second] = planted.split(',');

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const context = await browser.newContext();
const page = await context.newPage();
page.on('dialog', (dialog) => dialog.accept());
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

async function submit(locator) {
    await Promise.all([page.waitForLoadState('networkidle'), locator.click({ timeout: 5000 }).catch(() => {})]);
    await page.waitForLoadState('networkidle');
}
const text = async (selector) => flat(await page.locator(selector).first().textContent({ timeout: 4000 }).catch(() => ''));
const times = async (sentence) => flat(await page.locator('body').textContent()).split(flat(sentence)).length - 1;

async function signIn(identifier) {
    await context.clearCookies();
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(identifier);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await submit(page.locator('form[action$="/login"] button[type="submit"]').first());
    return !path(page).endsWith('/login');
}

// ------------------------------------------------ the office's list
check('the office signs in', await signIn(STAFF), path(page));
await page.goto(`${BASE}/dv/substitutions/requests`, { waitUntil: 'networkidle' });
check('the list reads right to left', await page.locator('html').getAttribute('dir') === 'rtl');
const headers = (await page.locator('thead th').allTextContents()).map(flat).filter(Boolean);
check('its heading and columns are in Dhivehi', thaana(await text('h1')) && headers.length === 6 && headers.every(thaana), [await text('h1'), ...headers].join(' | '));
check('New request and the absences are offered', await page.locator('[data-testid="new-request"]').isVisible() && await page.locator('[data-testid="absences-link"]').isVisible());
const openRow = page.locator('[data-testid="request-row"][data-status="open"]').first();
check("a request's state is named in Dhivehi", flat(await openRow.locator('span.rounded-full').first().textContent().catch(() => '')) === say('substitutions.status_open'));

// ------------------------------------------------ the office changes the first request
await page.goto(`${BASE}/dv/substitutions/requests/${first}/edit`, { waitUntil: 'networkidle' });
await page.locator('textarea[name="notes"]').fill(`${MARK} A ޕޭޖު 12`);
await submit(page.locator('[data-testid="request-save"], form[action*="/substitutions/requests/"] button[type="submit"]').first());
const updated = say('substitutions.flash_updated');
check('a change is saved, and said once in Dhivehi', path(page).endsWith(`/substitutions/requests/${first}`) && await times(updated) === 1, `${await times(updated)}× on ${path(page)}`);

// ------------------------------------------------ the teacher takes it
check('the teacher signs in', await signIn(TEACHER), path(page));
await page.goto(`${BASE}/dv/substitutions/requests?status=open`, { waitUntil: 'networkidle' });
const take = page.locator(`form[action$="/substitutions/requests/${first}/take"] button`);
check('the teacher is offered Take', await take.count() === 1);
await submit(take);
const taken = say('substitutions.flash_taken');
check('the lesson is taken, and said in Dhivehi', await text('[data-testid="flash-success"]') === flat(taken)
    && tinker(`echo ${M}\\SubstitutionRequest::find(${first})->status;`) === 'assigned', await text('[role="alert"]'));

// ------------------------------------------------ the office assigns the second from its page
check('the office signs in again', await signIn(STAFF), path(page));
await page.goto(`${BASE}/dv/substitutions/requests/${second}`, { waitUntil: 'networkidle' });
const assign = page.locator('[data-testid="assign-form"]');
check('the request\'s page offers Assign', await assign.isVisible().catch(() => false));
if (await assign.count() > 0) {
    await assign.locator('select[name="substitute_teacher_id"]').selectOption({ index: 1 });
    await submit(assign.locator('[data-testid="assign"]'));
}
const assigned = say('substitutions.flash_assigned');
check('the substitute is assigned, and said in Dhivehi', await text('[data-testid="flash-success"]') === flat(assigned)
    && await text('[data-testid="request-status"]') === flat(say('substitutions.status_assigned')), await text('[role="alert"]'));

// ------------------------------------------------ a teacher's absence
await page.goto(`${BASE}/dv/substitutions/absences`, { waitUntil: 'networkidle' });
check('the absences list has its heading and Record button', thaana(await text('h1')) && await page.locator('[data-testid="record-absence"]').isVisible(), await text('h1'));
// Over a page that hides its Record button, the form is still at its address.
if (await page.locator('[data-testid="record-absence"]').count() > 0) await submit(page.locator('[data-testid="record-absence"]'));
else await page.goto(`${BASE}/dv/substitutions/absences/create`, { waitUntil: 'networkidle' });
const form = page.locator('form[action$="/substitutions/absences"]').first();
await form.locator('select[name="teacher_id"]').selectOption({ index: 1 });
await form.locator('input[name="from_date"]').fill('2026-11-02');
await form.locator('input[name="to_date"]').fill('2026-11-03');
await form.locator('textarea[name="reason"]').fill(`${MARK} ބަލި`);
await submit(form.locator('button[type="submit"]').first());
const recorded = say('substitutions.flash_absence_recorded');
const row = page.locator('[data-testid="absence-row"]', { hasText: MARK }).first();
check('the absence is recorded, and said once in Dhivehi', path(page).endsWith('/substitutions/absences') && await times(recorded) === 1, `${await times(recorded)}× on ${path(page)}`);
check('its state and month are in Dhivehi', (await row.textContent().catch(() => '')).includes(say('substitutions.absence_status_pending'))
    && (await row.textContent().catch(() => '')).includes('ނޮވެންބަރު'), flat(await row.textContent().catch(() => '')).slice(0, 120));

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
