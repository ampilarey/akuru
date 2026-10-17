/**
 * Can a student ask to start a blog, a parent agree, and the office approve?
 * (BLOG_PLAN BL1, STATUS §5se.)
 *
 * The seeded pupil (student@) opens *My blog* under /dv and asks, in
 * Dhivehi. A verified parent of theirs sees *Blog — waiting for you* on My
 * children and agrees, letting the school name show but not the photo. The
 * office approves on the Library office's *Blog requests*, where the pupil's
 * real name sits beside the pen name. The pupil sees their blog, badged
 * *Student writer*, and the writer portal shows them the simpler panel.
 *
 * The walk makes the pupil under 18 for the run if the seed did not, and
 * puts everything back: the request, the profile, the consent, the role, the
 * date of birth. Local server only.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('blog-start changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

// The pupil, their record and a verified parent with an account; the walk makes its own parent password known.
const setup = JSON.parse(tinker(`$u = DB::table('users')->where('email', '${STUDENT}')->first(); $s = DB::table('students')->where('user_id', $u->id)->orderBy('id')->first(); $g = app(\\App\\Domains\\People\\Actions\\ListConsentingGuardiansAction::class)->execute($s->id); echo json_encode(['user' => $u->id, 'student' => $s->id, 'dob' => $s->date_of_birth, 'guardian' => $g[0] ?? null, 'guardian_email' => $g ? DB::table('users')->where('id', $g[0])->value('email') : null, 'guardian_hash' => $g ? DB::table('users')->where('id', $g[0])->value('password') : null]);`));
if (!setup.guardian) {
    console.log('blog-start needs a verified parent with an account for the seeded pupil. 0/1 steps passed');
    process.exit(1);
}
const startedAt = tinker(`echo now()->toDateTimeString();`);
tinker(`DB::table('students')->where('id', ${setup.student})->update(['date_of_birth' => '2013-06-01']); DB::table('users')->where('id', ${setup.guardian})->update(['password' => bcrypt('${PASSWORD}')]); echo 'ok';`);
const cleanUp = () => {
    tinker(`DB::table('blog_requests')->where('user_id', ${setup.user})->delete(); DB::table('writer_profiles')->where('user_id', ${setup.user})->where('kind', 'student')->delete(); DB::table('consents')->where('person_type', 'student')->where('person_id', ${setup.student})->where('consent_type', 'blog_publishing')->where('created_at', '>=', '${startedAt}')->delete(); \\App\\Domains\\Identity\\Models\\User::find(${setup.user})?->removeRole('writer'); DB::table('students')->where('id', ${setup.student})->update(['date_of_birth' => ${setup.dob ? `'${setup.dob}'` : 'null'}]); DB::table('users')->where('id', ${setup.guardian})->update(['password' => '${setup.guardian_hash || ''}']); echo 'ok';`);
};
cleanUp();
tinker(`DB::table('students')->where('id', ${setup.student})->update(['date_of_birth' => '2013-06-01']); DB::table('users')->where('id', ${setup.guardian})->update(['password' => bcrypt('${PASSWORD}')]); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const signIn = async (who, locale) => {
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/${locale}/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    return page;
};

try {
    // The pupil asks, in Dhivehi.
    const pupil = await signIn(STUDENT, 'dv');
    await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
    check('My blog opens under /dv right to left, in Dhivehi', (await pupil.locator('html').getAttribute('dir')) === 'rtl' && (await pupil.locator('[data-testid="blog-start-form"] h2').textContent())?.trim() === say('blog.start_heading'));
    check('it says a parent agrees first', (await pupil.locator('[data-testid="blog-under18"]').textContent())?.trim() === say('blog.under18_note'));
    await pupil.fill('[data-testid="blog-pen-name"]', 'ތަރި');
    await pupil.fill('[data-testid="blog-title"]', 'މާލޭގެ ނޯޓްތައް');
    await pupil.fill('[data-testid="blog-about"]', 'ކިޔާ ފޮތްތަކާއި ކުޑަކުޑަ ވާހަކަތައް.');
    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="blog-start-submit"]')]);
    await pupil.waitForSelector('[data-testid="blog-request-status"]');
    check('the request waits for a parent', (await pupil.locator('[data-testid="blog-request-status"]').getAttribute('data-status')) === 'waiting_parent' && flat(await pupil.locator('[data-testid="blog-request-status"]').textContent()) === say('blog.status_waiting_parent'));

    // The parent agrees.
    const parent = await signIn(setup.guardian_email, 'en');
    await parent.goto(`${BASE}/en/portal/children`, { waitUntil: 'networkidle' });
    check('My children shows Blog — waiting for you', (await parent.locator('[data-testid="child-blog-waiting"]').count()) === 1);
    await Promise.all([parent.waitForLoadState('networkidle'), parent.click('[data-testid="child-blog"]')]);
    await parent.waitForSelector('[data-testid="child-blog-consent"]');
    check('the parent reads what the child asked, by pen name', flat(await parent.locator('[data-testid="child-blog-request"] p').first().textContent()).includes('ތަރި'));
    await parent.check('[data-testid="child-blog-school"]');
    await Promise.all([parent.waitForLoadState('networkidle'), parent.click('[data-testid="child-blog-agree"]')]);
    await parent.waitForFunction(() => document.querySelector('[data-testid="child-blog-request"]')?.dataset.status === 'waiting_office');
    check('agreeing passes it to the office', (await parent.locator('[data-testid="child-blog-request"]').getAttribute('data-status')) === 'waiting_office');

    // The office approves.
    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/library/blog-requests`, { waitUntil: 'networkidle' });
    const requestId = tinker(`echo DB::table('blog_requests')->where('user_id', ${setup.user})->latest('id')->value('id');`);
    const row = office.locator(`[data-testid="blog-request-${requestId}"]`);
    check('the office sees the pen name beside the pupil\'s real name', (await row.count()) === 1 && flat(await row.textContent()).includes('ތަރި') && flat(await row.locator('td').nth(2).textContent()).length > 2);
    await Promise.all([office.waitForLoadState('networkidle'), office.click(`[data-testid="blog-request-approve-${requestId}"]`)]);
    await office.waitForFunction((id) => document.querySelector(`[data-testid="blog-request-status-${id}"]`)?.textContent?.trim() === 'Approved', requestId);
    check('approving makes it approved', flat(await office.locator(`[data-testid="blog-request-status-${requestId}"]`).textContent()) === 'Approved');
    const csv = await office.request.get(`${BASE}/en/admin/library/blog-requests/export`);
    check('and the list downloads as CSV', csv.status() === 200 && (await csv.text()).startsWith('id,pen_name,blog_title'), String(csv.status()));

    // The pupil sees the blog.
    await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
    check('My blog shows the blog, badged Student writer in Dhivehi', flat(await pupil.locator('[data-testid="blog-ready-title"]').textContent()) === 'މާލޭގެ ނޯޓްތައް' && flat(await pupil.locator('[data-testid="blog-badge"]').textContent()) === say('blog.badge_student'));
    await pupil.goto(`${BASE}/dv/write`, { waitUntil: 'networkidle' });
    check('the writer portal shows the student the simpler panel, with no bank or ID card', (await pupil.locator('[data-testid="write-student-blog"]').count()) === 1 && (await pupil.locator('input[aria-label="' + say('common.library_bank_name') + '"]').count()) === 0);
    const profile = JSON.parse(tinker(`echo json_encode(DB::table('writer_profiles')->where('user_id', ${setup.user})->first(['kind', 'show_photo', 'show_school']));`));
    check('the profile is a student\'s, school name allowed, photo not', profile.kind === 'student' && !profile.show_photo && profile.show_school, JSON.stringify(profile));
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
