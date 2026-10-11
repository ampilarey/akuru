/**
 * Does a reset code reset only the account it was sent for? (STATUS §5rc.)
 *
 * Both reset flows — the pages the login's "Forgot password?" opens, and the
 * forgot-password pages, which also take an ID card number — once kept their
 * progress in loose session keys, and "verified" was a flag of its own. A
 * code proved for one's own number, then a new request for someone else's,
 * reset the other account; and the forgot-password pages' account, found by
 * an ID card number, stayed in the session while the other pages swapped the
 * number under it.
 *
 * Five accounts are planted, each with a verified number of its own. In a
 * browser:
 *   - the first follows "Forgot password?" from the login page, asks for a
 *     code, types it, sets a new password and signs in with it;
 *   - the second proves a code for their own number, then asks again for the
 *     third account's number: the new password page sends them back to start,
 *     and the third account still signs in with its own password;
 *   - the fourth account's ID card number is asked for on the forgot-password
 *     page, then the fifth account's own number on the other pages, and its
 *     code typed on the forgot-password page's code step: the new password
 *     page sends them back, and the fourth account still signs in with its own
 *     password.
 *
 * Codes are read from the log sender, so the walk runs on a local server
 * only. The planted accounts are removed first and last, and the address's
 * reset throttle is cleared with them, so the walk can run again at once.
 *
 *   node scripts/smoke/password-reset.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const NUMBERS = ['7999101', '7999102', '7999103', '7999104', '7999105'];
const ID_CARD = 'A799104';
const PASSWORD = (n) => `smoke-pw1-${n}`;
const NEW_PASSWORD = 'Smoke-new-pass-2026';

if (!LOCAL) {
    console.log('password-reset: codes are read from the log sender, so this walk runs on a local server only — skipped.');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const path = (page) => new URL(page.url()).pathname;

const numbers = NUMBERS.map((n) => `'+960${n}'`).join(',');
const cleanUp = () => tinker(`$ids = DB::table('user_contacts')->whereIn('value', [${numbers}])->pluck('user_id'); DB::table('user_contact_otps')->whereIn('user_contact_id', DB::table('user_contacts')->whereIn('value', [${numbers}])->pluck('id'))->delete(); DB::table('user_contacts')->whereIn('value', [${numbers}])->delete(); DB::table('users')->whereIn('id', $ids)->orWhere('national_id', '${ID_CARD}')->delete(); RateLimiter::clear(md5('auth-password-reset127.0.0.1')); echo 'ok';`);

cleanUp();
NUMBERS.forEach((n, i) => tinker(`$u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'SMOKE PW1 ${i + 1}', 'password' => Hash::make('${PASSWORD(n)}'), 'national_id' => ${n === '7999104' ? `'${ID_CARD}'` : 'null'}]); DB::table('user_contacts')->insert(['user_id' => $u->id, 'type' => 'mobile', 'value' => '+960${n}', 'is_primary' => 1, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]); echo 'ok';`));

const codeFor = (n) => (tinker(`echo (string) (DB::table('sms_receipts')->where('phone', 'like', '%${n}')->latest('id')->value('body') ?? '');`).match(/\b(\d{6})\b/) ?? [])[1];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

async function fresh() {
    const page = await (await browser.newContext()).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

    return page;
}

async function submit(page, selector) {
    await Promise.all([page.waitForLoadState('networkidle'), page.locator(selector).first().click()]);
    await page.waitForLoadState('networkidle');
}

async function signsIn(n, password) {
    const page = await fresh();
    await page.goto(`${BASE}/en/login`, { waitUntil: 'networkidle' });
    await page.locator('#identifier').fill(n);
    await page.locator('#password').fill(password);
    await submit(page, 'form[action$="/login"] button[type="submit"]');
    const ok = !/\/login$/.test(path(page));
    await page.context().close();

    return ok;
}

/** Where a new password page did open, the walk tries it, as someone taking the account would. */
async function tryNewPassword(page, form) {
    if ((await page.locator(`form[action$="${form}"]`).count()) === 0) return;
    await page.locator('input[name="password"]').fill('Smoke-taken-2026');
    await page.locator('input[name="password_confirmation"]').fill('Smoke-taken-2026');
    await submit(page, `form[action$="${form}"] button[type="submit"]`);
}

async function askOnTheLoginsPages(page, n) {
    await page.goto(`${BASE}/en/password/otp/request`, { waitUntil: 'networkidle' });
    await page.locator('input[name="contact"]').fill(n);
    await submit(page, 'form[action$="/password/otp/send"] button[type="submit"]');
}

// ------------------------------------------------ a reset that still works
const first = await fresh();
await first.goto(`${BASE}/en/login`, { waitUntil: 'networkidle' });
await submit(first, 'a[href$="/password/otp/request"]');
check('the login page\'s "Forgot password?" opens the reset page', /\/password\/otp\/request$/.test(path(first)), path(first));
await first.locator('input[name="contact"]').fill(NUMBERS[0]);
await submit(first, 'form[action$="/password/otp/send"] button[type="submit"]');
const firstCode = codeFor(NUMBERS[0]);
check('a code is sent to the first account\'s number', Boolean(firstCode), firstCode ? 'found' : 'no code in the log sender');
await first.locator('#code').fill(firstCode ?? '');
await submit(first, 'form[action$="/password/otp/verify"] button[type="submit"]');
check('the code opens the new password page', /\/password\/otp\/reset$/.test(path(first)), path(first));
await first.locator('input[name="password"]').fill(NEW_PASSWORD);
await first.locator('input[name="password_confirmation"]').fill(NEW_PASSWORD);
await submit(first, 'form[action$="/password/otp/reset"] button[type="submit"]');
check('the new password is saved, and the login page says so', /\/login$/.test(path(first)), path(first));
check('the first account signs in with its new password', await signsIn(NUMBERS[0], NEW_PASSWORD));

// ------------------------------------- a proved code, then someone else's number
const second = await fresh();
await askOnTheLoginsPages(second, NUMBERS[1]);
const secondCode = codeFor(NUMBERS[1]);
await second.locator('#code').fill(secondCode ?? '');
await submit(second, 'form[action$="/password/otp/verify"] button[type="submit"]');
check('the second account\'s own code opens the new password page', /\/password\/otp\/reset$/.test(path(second)), path(second));
await askOnTheLoginsPages(second, NUMBERS[2]);
await second.goto(`${BASE}/en/password/otp/reset`, { waitUntil: 'networkidle' });
check('asked again for the third account\'s number, the new password page sends them back to start', /\/password\/otp\/request$/.test(path(second)), path(second));
await tryNewPassword(second, '/password/otp/reset');
check('the third account still signs in with its own password', await signsIn(NUMBERS[2], PASSWORD(NUMBERS[2])));

// ------------------------------- an ID card number on one flow, a code on the other
const third = await fresh();
await third.goto(`${BASE}/en/forgot-password`, { waitUntil: 'networkidle' });
await third.locator('#identifier').fill(ID_CARD);
await submit(third, 'form[action$="/forgot-password"] button[type="submit"]');
check('the fourth account\'s ID card number is taken on the forgot-password page', /\/reset-password\/verify$/.test(path(third)), path(third));
await askOnTheLoginsPages(third, NUMBERS[4]);
const fifthCode = codeFor(NUMBERS[4]);
await third.goto(`${BASE}/en/reset-password/verify`, { waitUntil: 'networkidle' });
await third.locator('#code').fill(fifthCode ?? '');
await submit(third, 'form[action$="/reset-password/verify"] button[type="submit"]');
await third.goto(`${BASE}/en/reset-password`, { waitUntil: 'networkidle' });
check('the fifth account\'s code does not open the fourth account\'s new password page', /\/forgot-password$/.test(path(third)), path(third));
await tryNewPassword(third, '/reset-password');
check('the fourth account still signs in with its own password', await signsIn(NUMBERS[3], PASSWORD(NUMBERS[3])));

await browser.close();
cleanUp();

for (const p of problems) check('no server error or page error', false, p);
const failed = results.filter(([, ok]) => !ok);
for (const [step, ok, detail] of results) console.log(`${ok ? '✓' : '✗'} ${step}${detail && !ok ? ` — ${detail}` : ''}`);
console.log(`\npassword-reset: ${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
