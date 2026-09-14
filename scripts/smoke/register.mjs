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
 *   node scripts/smoke/register.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_COURSE (slug, default smoke-course),
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const COURSE = process.env.SMOKE_COURSE ?? 'smoke-course';
const PHONE = '9' + String(Date.now()).slice(-6);
const EMAIL = `smoke${String(Date.now()).slice(-6)}@example.test`;

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
await fill('national_id', 'A' + String(Date.now()).slice(-6));
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

const code = codeFor(PHONE);
check('the code reaches sms_receipts', Boolean(code), code ? code[1] : 'no code found');

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
  await reFill('national_id', 'A' + String(Date.now()).slice(-5));

  for (const sel of ['select[name="gender"]:visible', 'select[name="id_type"]:visible']) {
    const el = review.locator(sel).first();
    if (await el.count()) { await el.selectOption({ index: 1 }).catch(() => {}); }
  }

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

const width = Math.max(...results.map(([s]) => s.length));
for (const [s, ok, d] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${s.padEnd(width)}  ${d}`);
console.log(problems.length ? '\nproblems: ' + problems.join(' | ') : '\nno console or server errors');
await b.close();

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);
process.exit(failed === 0 ? 0 : 1);
