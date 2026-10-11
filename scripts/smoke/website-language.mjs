/**
 * Do the website's public forms and its office say their messages in Dhivehi,
 * and once? (BACKLOG C21, slice WB1, STATUS §5rf.)
 *
 * A visitor, under /dv:
 *   - joins a full course's waiting list: thanked once (the form sits twice
 *     on the page and printed its own thanks under the layout's). Then, on a
 *     page loaded while the course was full, a seat opens and the form is
 *     sent: the refusal is said once, in Dhivehi, on the page that no longer
 *     offers the list (it was said under a form that had gone);
 *   - writes to the institute, and registers for an event: thanked once each.
 * A planted learner, signed in, subscribes to the daily reminders with no
 * type ticked (refused in Dhivehi), then with one (thanked once).
 * The office, under /dv:
 *   - asks for a batch of no days, and approves a reminder with no Dhivehi
 *     text: both refused in Dhivehi;
 *   - saves news with no body: refused in Dhivehi;
 *   - reads the funnel's decision for the planted course in Dhivehi.
 *
 * The planted rows are removed first and last. Local server only.
 *
 *   node scripts/smoke/website-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const STAFF_PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const LEARNER = 'smoke-wb1@akuru.test';
const PASSWORD = 'smoke-wb1-pass';
const SLUG = 'smoke-wb1-tajweed';
const EVENT = 'smoke-wb1-open-day';
const NAME = 'SMOKE WB1';

if (!LOCAL) {
    console.log('website-language plants rows with tinker: local server only. 0/0 steps passed');
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

const M = '\\App\\Domains\\Website\\Models';
const cleanUp = () => tinker(`$course = DB::table('courses')->where('slug', '${SLUG}')->value('id') ?? 0; $event = DB::table('events')->where('slug', '${EVENT}')->value('id') ?? 0; $user = DB::table('users')->where('email', '${LEARNER}')->value('id') ?? 0; ${M}\\ContactInquiry::query()->where('name', '${NAME}')->delete(); ${M}\\Lead::query()->where('course_id', $course)->delete(); ${M}\\FunnelEvent::query()->where('course_id', $course)->delete(); ${M}\\ContactMessage::query()->where('name', '${NAME}')->delete(); ${M}\\EventRegistration::query()->where('event_id', $event)->delete(); ${M}\\DailyContentSubscription::query()->where('user_id', $user)->delete(); ${M}\\DailyContent::query()->where('attribution', '${NAME}')->delete(); ${M}\\Post::query()->where('title', '${NAME} news')->delete(); DB::table('events')->where('id', $event)->delete(); DB::table('courses')->where('id', $course)->delete(); DB::table('users')->where('id', $user)->delete(); echo 'ok';`);

cleanUp();
tinker(`$u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => '${NAME}', 'email' => '${LEARNER}', 'password' => Hash::make('${PASSWORD}'), 'force_password_change' => false]); \\App\\Domains\\Courses\\Models\\Course::factory()->create(['title' => '${NAME} Tajweed', 'slug' => '${SLUG}', 'short_desc' => 'ތަޖްވީދު', 'status' => 'open', 'seats' => 0]); ${M}\\Event::create(['title' => '${NAME} Open Day', 'slug' => '${EVENT}', 'description' => 'ހުޅުވާލާ ދުވަސް', 'location' => 'މާލެ', 'status' => 'published', 'is_public' => true, 'registration_type' => 'required', 'start_date' => now()->addMonth(), 'end_date' => now()->addMonth()]); ${M}\\DailyContent::query()->create(['content_type' => 'reminder', 'publish_date' => now()->addYears(3)->toDateString(), 'status' => 'draft', 'text_en' => '${NAME}: seek knowledge.', 'attribution' => '${NAME}', 'created_by' => $u->id]); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

async function submit(locator) {
    await Promise.all([page.waitForLoadState('networkidle'), locator.click()]);
    await page.waitForLoadState('networkidle');
}
const times = async (sentence) => flat(await page.locator('body').textContent()).split(flat(sentence)).length - 1;
const text = async (selector) => flat(await page.locator(selector).first().textContent({ timeout: 5000 }).catch(() => ''));

async function signIn(identifier, password) {
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(identifier);
    await page.locator('input[name="password"]').fill(password);
    await submit(page.locator('form[action$="/login"] button[type="submit"]').first());
    return !path(page).endsWith('/login');
}

// ------------------------------------------------ a full course's waiting list
const course = `${BASE}/dv/courses/${SLUG}`;
await page.goto(course, { waitUntil: 'networkidle' });
const form = page.locator(`form[action*="/courses/${SLUG}/waitlist"]`).first();
check('a full course offers its waiting list', await form.isVisible().catch(() => false), path(page));
await form.locator('input[name="name"]').fill(NAME);
await form.locator('input[name="phone"]').fill('7999241');
await submit(form.locator('button[type="submit"]'));
const joined = say('public.You are on the waiting list. We will contact you if a seat opens.');
check('the waiting list thanks the visitor once, in Dhivehi', await times(joined) === 1, `${await times(joined)}× on ${path(page)}`);

// A page loaded while the course was full; a seat opens before the form is sent.
await page.goto(course, { waitUntil: 'networkidle' });
tinker(`DB::table('courses')->where('slug', '${SLUG}')->update(['seats' => null]); echo 'ok';`);
const stale = page.locator(`form[action*="/courses/${SLUG}/waitlist"]`).first();
await stale.locator('input[name="name"]').fill(NAME);
await stale.locator('input[name="phone"]').fill('7999241');
await submit(stale.locator('button[type="submit"]'));
const refused = say('public.Waiting list is only open when the course is full.');
check('a seat opened: the waiting list is refused once, in Dhivehi, on the page without it', await times(refused) === 1
    && await page.locator(`form[action*="/courses/${SLUG}/waitlist"]`).count() === 0, `${await times(refused)}× on ${path(page)}`);

// ------------------------------------------------ writing to the institute, and an event's registration
await page.goto(`${BASE}/dv/contact`, { waitUntil: 'networkidle' });
const contact = page.locator('form[action$="/contact"]').first();
await contact.locator('input[name="name"]').fill(NAME);
await contact.locator('textarea[name="message"]').fill('ސަލާމް');
await submit(contact.locator('button[type="submit"]'));
const sent = say('public.contact_message_sent');
check('the contact form thanks the visitor once, in Dhivehi', await times(sent) === 1, `${await times(sent)}× on ${path(page)}`);

await page.goto(`${BASE}/dv/events/${EVENT}`, { waitUntil: 'networkidle' });
const register = page.locator('form[action*="/register"]').first();
await register.locator('input[name="name"]').fill(NAME);
await register.locator('input[name="email"]').fill('smoke-wb1-event@akuru.test');
await submit(register.locator('button[type="submit"]'));
const registered = say('public.Registration submitted successfully!');
check("an event's registration thanks the visitor once, in Dhivehi", await times(registered) === 1, `${await times(registered)}× on ${path(page)}`);

// ------------------------------------------------ the daily reminders' subscription
check('the planted learner signs in', await signIn(LEARNER, PASSWORD), path(page));
await page.goto(`${BASE}/dv/daily/subscribe`, { waitUntil: 'networkidle' });
const subscribe = page.locator('form[action$="/daily/subscribe"]').first();
for (const box of await subscribe.locator('input[name="content_types[]"]').all()) await box.uncheck();
await submit(subscribe.locator('button[type="submit"]'));
const noType = say('public.Choose at least one content type.');
check('a subscription with no type is refused once, in Dhivehi', await times(noType) === 1, `${await times(noType)}× on ${path(page)}`);

await page.locator('form[action$="/daily/subscribe"] select[name="channel"]').selectOption('email');
await submit(page.locator('form[action$="/daily/subscribe"] button[type="submit"]').first());
const saved = say('public.Subscription saved. You will only receive messages you opted into.');
check('a subscription saved is said once, in Dhivehi', await times(saved) === 1, `${await times(saved)}× on ${path(page)}`);

// ------------------------------------------------ the office
await page.context().clearCookies();
check('the office signs in', await signIn(STAFF, STAFF_PASSWORD), path(page));

await page.goto(`${BASE}/dv/admin/public-site/daily-content`, { waitUntil: 'networkidle' });
const batch = page.locator('[data-testid="daily-batch"] form');
await batch.locator('input[name="publish_date"]').fill('2029-11-01');
// The browser would not send a number under its minimum; the server's own refusal is what is walked.
await batch.locator('input[name="days"]').evaluate((input) => input.removeAttribute('min'));
await batch.locator('input[name="days"]').fill('0');
await batch.locator('[data-testid="daily-batch-submit"]').click();
await page.locator('[data-testid="daily-error"]').waitFor({ timeout: 8000 }).catch(() => {});
const noDays = await text('[data-testid="daily-error"]');
check('a batch of no days is refused in Dhivehi', noDays.includes(say('admin.daily_error_batch_days')), noDays.slice(0, 120));

await page.goto(`${BASE}/dv/admin/public-site/daily-content/queue`, { waitUntil: 'networkidle' });
const row = page.locator('[data-testid="queue-row"]', { hasText: NAME }).first();
await row.locator('[data-testid="queue-publish"]').click({ timeout: 5000 }).catch(() => {});
await page.locator('[data-testid="queue-error"]').waitFor({ timeout: 8000 }).catch(() => {});
const incomplete = await text('[data-testid="queue-error"]');
check('a reminder with no Dhivehi text is refused approval in Dhivehi', incomplete.includes(say('admin.daily_error_text_incomplete')), incomplete.slice(0, 120));

await page.goto(`${BASE}/dv/admin/public-site/news/create`, { waitUntil: 'networkidle' });
await page.locator('[data-testid="news-form"] input[name="title"]').fill(`${NAME} news`);
await page.locator('[data-testid="news-save"]').click();
await page.locator('[data-testid="news-error"]').waitFor({ timeout: 8000 }).catch(() => {});
const noBody = await text('[data-testid="news-error"]');
// The form's own rule answers an empty editor, before the action's (which reads the body without its markup).
check('news with no body is refused in Dhivehi', thaana(noBody.replace('✗', '')), noBody.slice(0, 120));

const courseId = tinker(`echo DB::table('courses')->where('slug', '${SLUG}')->value('id');`);
await page.goto(`${BASE}/dv/admin/public-site/funnel?course_id=${courseId}`, { waitUntil: 'networkidle' });
const decision = await text('[data-testid="funnel-decision"]');
check("the funnel's decision for the course is in Dhivehi", thaana(decision), decision.slice(0, 120));

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
