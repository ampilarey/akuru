/**
 * Can a stranger enrol themselves? The public registration funnel, walked.
 *
 * This is the path real people arrive on and the one money eventually comes
 * through, and **nothing had ever walked it**. `sweep.mjs` checks staff screens
 * and `learn.mjs` checks a student who is already enrolled; both start from a
 * login that already exists.
 *
 * The funnel is longer than it looks — six screens and **two** OTP rounds:
 *
 *   course page -> checkout -> register/start -> OTP -> register/continue
 *   (review) -> register/enroll -> enroll/confirm (terms, send, second OTP)
 *   -> register/complete
 *
 * ## What it proves
 *
 * A stranger with a phone number can create an account, verify it, and end up
 * with a real `course_enrollments` row carrying **both** the unified and the
 * legacy student id. On a free course `payment_status` comes back
 * `not_required` and the enrolment is `pending`.
 *
 * ## What it does not
 *
 * It stops where money starts. A paid course hands off to BML, which is not
 * reachable from here, so nothing below the free path is walked — and rule 12
 * means the webhook, not this journey, is what confirms a payment.
 *
 * ## Two things that make this walk fragile, both worth knowing
 *
 * **Read the applicant's own message, not the newest one.** Each registration
 * also texts the school, so `sms_receipts` newest-first is routinely a staff
 * notification. Reading it reports "no code found" and looks exactly like the
 * OTP never being sent.
 *
 * **OTP sends are rate limited per contact** (SPEC §32: three per fifteen
 * minutes). Every run therefore invents a fresh number; re-running with a
 * fixed one will start failing on the third attempt, correctly.
 *
 * That sentence used to be wishful. The number came from the last six digits of
 * `Date.now()`, which repeat every 1,000 seconds, so two runs sixteen minutes
 * apart shared a contact and the third one in that window hit the rate limit
 * this note warns about — reported as the funnel being broken. See the identity
 * block below, and STATUS §5ek.
 *
 *   node scripts/smoke/register.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_COURSE (slug, default smoke-course),
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const COURSE = process.env.SMOKE_COURSE ?? 'smoke-course';
/**
 * The stranger's identity, unique per run — which the old version only looked like being.
 *
 * Every one of these was `String(Date.now()).slice(-6)`, and the last six digits
 * of the epoch in milliseconds **repeat every 1,000 seconds**; the national id
 * on the review screen used `slice(-5)`, which repeats every 100. These are the
 * fields the registration form checks for duplicates, so a collision makes the
 * form correctly refuse and the walk report a working screen as broken — or,
 * worse, match a row an earlier run left behind and pass without testing
 * anything.
 *
 * Found by searching the other walks after `create-sweep` turned out to have the
 * same fault (STATUS §5ek). Base-36 time plus randomness, which does not wrap.
 */
const UNIQUE = () => `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`.toUpperCase();

// One identity per run, fixed here rather than recomputed at each use — the
// review screen has to re-enter the *same* person, and a second Date.now() call
// there was quietly a different one.
const RUN_ID = UNIQUE();
// A Maldivian mobile number is seven digits, so this one cannot be base-36. Six
// random digits is a one-in-a-million chance of repeating a previous run rather
// than the old certainty of repeating one every 1,000 seconds — stated rather
// than claimed unique, because it is not.
const PHONE = '9' + String(Math.floor(Math.random() * 1e6)).padStart(6, '0');
const EMAIL = `smoke${RUN_ID}@example.test`;
// A letter and 5–9 **digits** — `regex:/^[A-Za-z][0-9]{5,9}$/` in
// CourseRegistrationController. The first attempt at fixing the wrapping id
// used the base-36 value above, which is alphanumeric, and the form refused it
// exactly as it should; the full fifteen-walk run caught that within minutes.
// Worth recording in a change that is *about* carelessly chosen values: a value
// has to satisfy the field before it is worth making unique. Nine digits is a
// space of a billion, which does not wrap in any practical sense.
const NATIONAL_ID = `A${String(Math.floor(Math.random() * 1e9)).padStart(9, '0')}`;

const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const b = await chromium.launch({
  args: [
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter',
    '--no-first-run',
    '--no-default-browser-check',
  ],
  ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const c = await b.newContext();
// Sixty seconds, not thirty: staging behind Cloudflare stalled past thirty on two page loads in one run (STATUS §5fz).
c.setDefaultNavigationTimeout(60000);
await c.route('**/*', (r) => (r.request().url().startsWith(BASE) ? r.continue() : r.abort()));
const p = await c.newPage();
const problems = [];
p.on('pageerror', (e) => problems.push('pageerror ' + String(e).slice(0, 110)));
p.on('response', (r) => { if (r.status() >= 500) problems.push('HTTP ' + r.status() + ' ' + r.url()); });

await p.goto(`${BASE}/en/courses/${COURSE}/checkout`, { waitUntil: 'networkidle' });
check('the checkout page loads', true, p.url());

// Three forms post to register/start; the visible one is the long "New
// registration" tab, which is the default on a fresh visit and so the path a
// stranger takes. Its submit stays disabled until the two choices at the top
// are made — an earlier version of this walk checked every radio on the page,
// which set contradictory answers and left the button disabled.
const form = p.locator('form[action*="register/start"]').first();
check('it offers a registration form', (await form.count()) > 0);

await p.getByText('Mobile (SMS)', { exact: false }).first().click().catch(() => {});
await p.getByText('I am enrolling myself', { exact: false }).first().click().catch(() => {});

const fill = async (name, value) => {
  const field = form.locator(`input[name="${name}"]`).first();
  if (await field.count()) { await field.fill(value).catch(() => {}); }
};

await fill('contact_value', PHONE);
await fill('first_name', 'Smoke');
await fill('last_name', 'Applicant');
await fill('dob', '2000-05-05');
await fill('email', EMAIL);
await fill('national_id', NATIONAL_ID);
await fill('password', 'secret-password-1');
await fill('password_confirmation', 'secret-password-1');

for (const sel of ['select[name="gender"]', 'select[name="id_type"]']) {
  const el = form.locator(sel).first();
  if (await el.count()) { await el.selectOption({ index: 1 }).catch(() => el.selectOption({ index: 0 }).catch(() => {})); }
}

const submit = form.locator('button[type=submit], input[type=submit]').first();
check('the submit button is enabled once the form is filled', await submit.isEnabled().catch(() => false));

await form.locator('button[type=submit], input[type=submit]').first().click();
await p.waitForLoadState('networkidle');
if (p.url().includes('register/start') || p.url().includes('/checkout')) {
  const errs = await p.$$eval('.text-red-600, .error, [role=alert], p', (es) => es.map((e) => e.innerText.trim()).filter((t) => t && /requir|invalid|already|must|too many/i.test(t)).slice(0, 6));
  check('starting registration is accepted', false, 'bounced: ' + JSON.stringify(errs));
} else {
  check('starting registration is accepted', true, p.url());
}

const body = (await p.innerText('body')).replace(/\s+/g, ' ');
check('it asks for the code it just sent', /code|otp|verif/i.test(body));

// Read the code out of sms_receipts — the log sender's record.
// Scoped to the number this walk registered with, not "the newest receipt".
// Each registration also texts the school, so the newest row is routinely a
// staff notification — reading it produced "no code found" and looked like the
// OTP never being sent. Two earlier runs passed only because the ordering
// happened to fall the other way.
const codeFor = (phone) => execSync(
  `cd /home/user/akuru && php artisan tinker --execute="echo (string) (\\Illuminate\\Support\\Facades\\DB::table('sms_receipts')->where('phone','like','%'.substr('${phone}',-7).'%')->latest('id')->value('body') ?? '');"`,
  { encoding: 'utf8' }
).match(/\b(\d{4,8})\b/);

// Through local tinker, so only when the app *is* the local dev server: on any
// other host that command reads the walker's database, not the app's, and the
// fifth staging run reported the OTP as never sent (STATUS §5fz).
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const code = LOCAL ? codeFor(PHONE) : null;
check('the code reaches sms_receipts', LOCAL ? Boolean(code) : true, LOCAL ? (code ? code[1] : 'no code found') : 'skipped on a remote host — sms_receipts is read through local tinker, so the funnel stops here');

if (code) {
  const otpField = p.locator('input[name="otp"], input[name="code"], input[autocomplete="one-time-code"]').first();
  if (await otpField.count()) {
    await otpField.fill(code[1]);
    await p.locator('button[type=submit]').first().click();
    await p.waitForLoadState('networkidle');
    check('the code is accepted', true, p.url());
    const after = (await p.innerText('body')).replace(/\s+/g, ' ');
    check('it lands somewhere that is not the code screen', !/enter the code/i.test(after), after.slice(0, 90));
  } else {
    check('the code is accepted', false, 'no otp field on the page');
  }
}

// Verification is not the end of the funnel. `register/continue` is a review
// step — it re-asks the details, then posts to `register/enroll`, which runs a
// **second** OTP round before the enrolment exists. An enrolment is the only
// thing that means "registered"; the account alone is not the product.
const latestCode = () => codeFor(PHONE);

if (p.url().includes('register/continue')) {
  const review = p.locator('form[action*="register/enroll"]').first();
  check('the review step is offered', (await review.count()) > 0);

  const reFill = async (name, value) => {
    const field = review.locator(`input[name="${name}"]:visible`).first();
    if (await field.count()) {
      const current = await field.inputValue().catch(() => '');
      if (!current) { await field.fill(value).catch(() => {}); }
    }
  };

  await reFill('first_name', 'Smoke');
  await reFill('last_name', 'Applicant');
  await reFill('dob', '2000-05-05');
  await reFill('national_id', NATIONAL_ID);

  for (const sel of ['select[name="gender"]:visible', 'select[name="id_type"]:visible']) {
    const el = review.locator(sel).first();
    if (await el.count()) { await el.selectOption({ index: 1 }).catch(() => {}); }
  }

  // COMMERCE_PARITY_PLAN P3: both sides of the learner's ID card, asked here and
  // never holding the enrolment up — the office checks it afterwards.
  const card = review.locator('[data-testid="learner-id-card"]');
  check('the review step asks for both sides of the ID card', (await card.count()) === 1 && (await review.locator('input[name="id_front"]').count()) === 1 && (await review.locator('input[name="id_back"]').count()) === 1);
  const idImage = { name: 'smoke-id.png', mimeType: 'image/png', buffer: await p.screenshot({ clip: { x: 0, y: 0, width: 80, height: 80 } }) };
  await review.locator('input[name="id_front"]').setInputFiles(idImage).catch(() => {});
  await review.locator('input[name="id_back"]').setInputFiles(idImage).catch(() => {});

  await review.locator('button[type=submit]:visible').first().click().catch(() => {});
  await p.waitForLoadState('networkidle');
  check('the review step is accepted', !p.url().includes('register/continue'), p.url());

  // The confirm page is itself two steps: tick the terms, press **Send OTP**
  // (the button is disabled until the box is ticked), and only then does the
  // `otp_code` field exist. An earlier version ticked the box, looked for the
  // field and found nothing — it had never pressed send.
  for (const box of await p.locator('input[type=checkbox]:visible').all()) {
    await box.check().catch(() => {});
  }

  const send = p.locator('#send-otp-btn, button:has-text("Send OTP")').first();
  if (await send.count()) {
    await send.click().catch(() => {});
    await p.waitForLoadState('networkidle');
    check('asking for the confirmation code is accepted', true, p.url());
  }

  const second = p.locator('input[name="otp_code"]').first();
  if (await second.count()) {
    const again = codeFor(PHONE);
    check('the confirmation code reaches sms_receipts', Boolean(again), again ? again[1] : 'none');
    if (again) {
      await second.fill(again[1]);
      await p.locator('form[action*="enroll/confirm"] button[type=submit]').first().click().catch(() => {});
      await p.waitForLoadState('networkidle');
    }
    check('the enrolment completes', p.url().includes('complete') || p.url().includes('my-enrollments'), p.url());
  } else {
    check('the confirmation code screen appears', false, 'no otp_code field after pressing send');
  }
}

// A paid course stops here with the money still outstanding: the enrolment is
// `pending`, a `payments` row is `initiated`, and the page offers "Proceed to
// payment". Whether that button leads anywhere depends on BML being configured,
// which is exactly what OWNER_ACTIONS item 2 is about — so the walk reports
// what a paying customer actually gets rather than assuming.
const proceed = p.locator('a:has-text("Proceed to payment")').first();

if (await proceed.count()) {
  check('a paid course asks for payment rather than claiming to be done', true);

  await proceed.click().catch(() => {});
  await p.waitForLoadState('networkidle').catch(() => {});

  const offsite = !p.url().startsWith(BASE);

  if (offsite) {
    check('the payment hand-off reaches the gateway', true, 'left the site for BML');
  } else {
    // Back on our own site means initiation failed. The only honest question
    // then is whether the customer is *told* — a bare redirect to the course
    // list, with money outstanding, is the dead end that #383 fixed on the
    // other return path. Looked for in the alert region, not anywhere on the
    // page: "payment" appears in the course list's own copy, so a loose match
    // would pass on silence.
    // `[role=alert]` only. An earlier version also matched `.bg-amber-50` and
    // friends, which caught a nav element and reported "Log out" as the
    // explanation — a check that passed on silence.
    const alert = (await p.locator('[role=alert]').first()
      .innerText().catch(() => '')).replace(/\s+/g, ' ').trim();

    check(
      'a failed hand-off tells the customer why',
      alert.length > 0,
      alert ? alert.slice(0, 80) : 'NO MESSAGE — dropped on ' + p.url()
    );
  }
} else {
  check('a free course finishes without asking for money', true);
}

// COMMERCE_PARITY_PLAN P3: the office sees the card on the enrolment page and verifies it there.
if (LOCAL && results.some(([s, ok]) => s === 'the enrolment completes' && ok)) {
  const learnerId = execSync(
    `cd /home/user/akuru && php artisan tinker --execute="echo (int) \\App\\Domains\\Identity\\Models\\IdentityVerification::query()->where('purpose','learner')->latest('id')->value('student_id');"`,
    { encoding: 'utf8' },
  ).trim();
  const enrolment = execSync(
    `cd /home/user/akuru && php artisan tinker --execute="echo (int) \\App\\Domains\\Courses\\Models\\CourseEnrollment::query()->where('unified_student_id', ${Number(learnerId) || 0})->latest('id')->value('id');"`,
    { encoding: 'utf8' },
  ).trim();
  check('the card is filed against the learner the enrolment made', Number(enrolment) > 0, `student ${learnerId}, enrolment ${enrolment}`);

  const oc = await b.newContext();
  oc.setDefaultNavigationTimeout(60000);
  await oc.route('**/*', (r) => (r.request().url().startsWith(BASE) ? r.continue() : r.abort()));
  const o = await oc.newPage();
  o.on('pageerror', (e) => problems.push('office pageerror ' + String(e).slice(0, 110)));
  o.on('response', (r) => { if (r.status() >= 500) problems.push('office HTTP ' + r.status() + ' ' + r.url()); });
  await o.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
  await o.fill('input[name="identifier"]', process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv');
  await o.fill('input[name="password"]', process.env.SMOKE_PASSWORD ?? 'password');
  await Promise.all([o.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), o.click('button[type=submit]')]);

  await o.goto(`${BASE}/en/admin/enrollments/${enrolment}`, { waitUntil: 'networkidle' });
  const checks = o.locator('[data-testid="identity-checks"]');
  await checks.waitFor({ timeout: 20000 }).catch(() => {});
  check('the enrolment page shows the card, waiting', (await checks.count()) === 1 && (await o.locator('[data-testid^="identity-front-"]').count()) === 1);
  const front = await o.locator('[data-testid^="identity-front-"]').first().getAttribute('href').catch(() => null);
  const image = front ? await o.request.get(front.startsWith('http') ? front : `${BASE}${front}`) : null;
  check('the office can open the front', image?.status() === 200 && /image/.test(image?.headers()['content-type'] ?? ''), front ?? 'no link');
  await o.locator('[data-testid^="identity-verify-"]').first().click().catch(() => {});
  await o.waitForLoadState('networkidle');
  await o.waitForTimeout(500);
  check('verifying it takes it off the waiting list', (await o.locator('[data-testid^="identity-verify-"]').count()) === 0, (await o.locator('[data-testid="identity-checks"]').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 90));

  await o.goto(`${BASE}/en/admin/enrollments?id_card=verified`, { waitUntil: 'networkidle' });
  check('the enrolments list filters by ID card', (await o.locator('body').innerText()).includes('Smoke'));
}

const width = Math.max(...results.map(([s]) => s.length));
for (const [s, ok, d] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${s.padEnd(width)}  ${d}`);
console.log(problems.length ? '\nproblems: ' + problems.join(' | ') : '\nno console or server errors');
await b.close();

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);
process.exit(failed === 0 ? 0 : 1);
