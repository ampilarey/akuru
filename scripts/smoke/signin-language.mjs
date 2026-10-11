/**
 * Do signing in and resetting a password read in Dhivehi and Arabic? (BACKLOG
 * C21, slice SI1, STATUS §5rd.)
 *
 * Under /dv and /ar the walk opens the sign-in page, the admins' sign-in by
 * code, the forgot-password page and the reset page the sign-in links to, and
 * lists what is still in Latin letters on the whole page — the layout's brand
 * panel too — leaving out what is typed the same in any language (an address,
 * a number, an ID card's example). Each page must be right to left and every
 * field in it named.
 *
 * Then, in Dhivehi:
 *   - a wrong password is refused in Dhivehi, under the field;
 *   - a planted account resets its password from the sign-in page's
 *     "Forgot your password?": the code page and the new password page read
 *     in Dhivehi, the code sent is said once, and the sign-in page says the
 *     password was reset; the new password signs in;
 *   - a planted admin signs in by code: the code page reads in Dhivehi with
 *     the number left to right, a new code asked for at once is refused on
 *     the page (it was said nowhere), and the code signs the admin in;
 *   - a planted account made by a code uses the checkout's "use a code
 *     instead", and is sent a code and then the password page, where it used
 *     to be sent back to log in with a password it did not have.
 *
 * Codes are read from the log sender, so the parts after the first run on a
 * local server only. The planted accounts are removed first and last, with
 * the address's reset throttle.
 *
 *   node scripts/smoke/signin-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const ALWAYS_FINE = [/https?:\/\/\S*/g, /[\w.*+-]+@[\w-]+(\.[\w-]+)+/g, /\bA\d{6}\b/g];
const RESETTER = '7999211';
const ADMIN = '7999212';
const CODE_ONLY = '7999213';
const NUMBERS = [RESETTER, ADMIN, CODE_ONLY];

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv', args = '[]') => tinker(`echo __('${key}', ${args}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();
const path = (page) => new URL(page.url()).pathname;
const codeFor = (n) => (tinker(`echo (string) (DB::table('sms_receipts')->where('phone', 'like', '%${n}')->latest('id')->value('body') ?? '');`).match(/\b(\d{6})\b/) ?? [])[1];

const numbers = NUMBERS.map((n) => `'+960${n}'`).join(',');
const cleanUp = () => tinker(`$ids = DB::table('user_contacts')->whereIn('value', [${numbers}])->pluck('user_id'); DB::table('user_contact_otps')->whereIn('user_contact_id', DB::table('user_contacts')->whereIn('value', [${numbers}])->pluck('id'))->delete(); DB::table('registration_flows')->whereIn('user_id', $ids)->delete(); DB::table('model_has_roles')->whereIn('model_id', $ids)->where('model_type', 'user')->delete(); DB::table('user_contacts')->whereIn('value', [${numbers}])->delete(); DB::table('users')->whereIn('id', $ids)->delete(); RateLimiter::clear(md5('auth-password-reset127.0.0.1')); RateLimiter::clear(md5('auth-otp-request127.0.0.1')); echo 'ok';`);

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

/**
 * The sign-in layout's whole page, its brand panel being outside its main; on
 * the public site's layout, its main (the header and footer are other walks').
 */
const readPage = (page) => page.evaluate(() => {
    const publicSite = document.querySelector('header nav, footer') !== null;
    const root = publicSite ? (document.querySelector('main') || document.body) : document.body;
    const texts = [];
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        if (walker.currentNode.parentElement?.closest('style, script, textarea, [hidden], noscript')) continue;
        const value = walker.currentNode.nodeValue.trim();
        if (value) texts.push(value);
    }
    root.querySelectorAll('[placeholder],[aria-label],[title]').forEach((el) => {
        ['placeholder', 'aria-label', 'title'].forEach((name) => {
            const value = el.getAttribute(name);
            if (value && value.trim()) texts.push(value.trim());
        });
    });
    const main = document.querySelector('main') || root;
    const unnamed = [...main.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((el) => !el.getAttribute('aria-label')
        && !(el.id && document.querySelector(`label[for="${el.id}"]`)) && !el.closest('label')).map((el) => el.outerHTML.slice(0, 80));
    return { texts, unnamed, dir: document.documentElement.getAttribute('dir') };
});

const english = (strings) => strings.filter((raw) => {
    let rest = raw;
    for (const pattern of ALWAYS_FINE) rest = rest.replace(pattern, ' ');
    return /[A-Za-z]{2,}/.test(rest);
});

async function readsIn(page, label) {
    const found = await readPage(page);
    const left = english(found.texts);
    check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
    check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
    check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
}

cleanUp();

// ------------------------------------------------ the pages, under /dv and /ar
const reader = await fresh();
for (const locale of ['dv', 'ar']) {
    for (const where of ['login', 'otp/login', 'forgot-password', 'password/otp/request', 'sign-in']) {
        const response = await reader.goto(`${BASE}/${locale}/${where}`, { waitUntil: 'networkidle' });
        if (!response || response.status() >= 400 || path(reader) !== `/${locale}/${where}`) {
            check(`/${locale}/${where}: the page opens`, false, `HTTP ${response?.status()} ${path(reader)}`);
            continue;
        }
        await readsIn(reader, `/${locale}/${where}`);
    }
}

// ------------------------------------------------ a wrong password, in Dhivehi
await reader.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await reader.locator('#identifier').fill('7999219');
await reader.locator('#password').fill('not-the-password');
await submit(reader, 'form[action$="/login"] button[type="submit"]');
const failed = say('auth.failed');
check('a wrong password is refused in Dhivehi, under the field', flat(await reader.locator('#identifier ~ [role=alert]').first().textContent().catch(() => '')) === failed, flat(await reader.locator('body').textContent()).slice(0, 140));

if (LOCAL) {
    tinker(`foreach ([['${RESETTER}', false], ['${ADMIN}', false], ['${CODE_ONLY}', true]] as [$n, $codeOnly]) { $u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'SMOKE SI1 '.$n, 'password' => Hash::make('smoke-si1-'.$n), 'force_password_change' => $codeOnly]); DB::table('user_contacts')->insert(['user_id' => $u->id, 'type' => 'mobile', 'value' => '+960'.$n, 'is_primary' => 1, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]); if ($n === '${ADMIN}') { $u->assignRole('admin'); } } echo 'ok';`);

    // -------------------------------------------- a password reset by code, in Dhivehi
    const resetter = await fresh();
    await resetter.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await submit(resetter, 'a[href$="/password/otp/request"]');
    await resetter.locator('input[name="contact"]').fill(RESETTER);
    await submit(resetter, 'form[action$="/password/otp/send"] button[type="submit"]');
    check('the code is asked for, and the code page opens', /\/dv\/password\/otp\/verify$/.test(path(resetter)), path(resetter));
    await readsIn(resetter, 'the code page');
    const sent = say('signin.flash_code_if_registered');
    const codePage = flat(await resetter.locator('body').textContent());
    check('the code sent is said once, in Dhivehi', codePage.split(sent).length - 1 === 1, `${codePage.split(sent).length - 1} times`);
    await resetter.locator('#code').fill(codeFor(RESETTER) ?? '');
    await submit(resetter, 'form[action$="/password/otp/verify"] button[type="submit"]');
    check('the code opens the new password page', /\/dv\/password\/otp\/reset$/.test(path(resetter)), path(resetter));
    await readsIn(resetter, 'the new password page');
    await resetter.locator('input[name="password"]').fill('Smoke-si1-new-2026');
    await resetter.locator('input[name="password_confirmation"]').fill('Smoke-si1-new-2026');
    await submit(resetter, 'form[action$="/password/otp/reset"] button[type="submit"]');
    check('the sign-in page says the password was reset, in Dhivehi', flat(await resetter.locator('[data-testid="login-status"]').textContent().catch(() => '')) === say('signin.flash_password_reset'), path(resetter));
    await resetter.locator('#identifier').fill(RESETTER);
    await resetter.locator('#password').fill('Smoke-si1-new-2026');
    await submit(resetter, 'form[action$="/login"] button[type="submit"]');
    check('the new password signs in', !/\/login$/.test(path(resetter)), path(resetter));

    // -------------------------------------------- an admin's sign-in by code, in Dhivehi
    const admin = await fresh();
    await admin.goto(`${BASE}/dv/otp/login`, { waitUntil: 'networkidle' });
    await admin.locator('#identifier').fill(ADMIN);
    await submit(admin, 'form[action$="/otp/request"] button[type="submit"]');
    check('the admin is sent a code, and the code page opens', /\/dv\/otp\/verify$/.test(path(admin)), path(admin));
    await readsIn(admin, 'the admin code page');
    check('the code page sets the number left to right', (await admin.locator(`strong[dir="ltr"]:text-is("${ADMIN}")`).count()) === 1);
    // The button waits out the minute; the form sent at once is refused, and the page says so now.
    await Promise.all([admin.waitForLoadState('networkidle'), admin.evaluate(() => document.querySelector('[data-testid="otp-resend-form"]').submit())]);
    await admin.waitForLoadState('networkidle');
    const refused = flat(await admin.locator('[data-testid="otp-resend-refused"]').textContent().catch(() => ''));
    check('a new code asked for at once is refused on the page, in Dhivehi', /\p{Script=Thaana}/u.test(refused), refused || 'nothing said');
    await admin.locator('#code').fill(codeFor(ADMIN) ?? '');
    await admin.waitForLoadState('networkidle');
    await admin.waitForURL((url) => !/\/otp\/verify$/.test(url.pathname), { timeout: 15000 }).catch(() => {});
    check('the code signs the admin in', !/\/otp\/(verify|login)$/.test(path(admin)), path(admin));

    // -------------------------------------------- the checkout's code box, an account made by a code
    const open = JSON.parse(tinker(`$c = \\App\\Domains\\Courses\\Models\\Course::query()->where('status', 'open')->where('workflow_status', 'published')->get()->first(fn ($c) => $c->is_enrollment_open); echo json_encode(['slug' => $c?->slug]);`));
    if (open.slug) {
        const learner = await fresh();
        await learner.goto(`${BASE}/dv/courses/${open.slug}/checkout`, { waitUntil: 'networkidle' });
        await learner.waitForFunction(() => window.Alpine !== undefined);
        await learner.getByRole('button', { name: say('register.tab_login'), exact: true }).click();
        await learner.getByRole('link', { name: say('register.use_code'), exact: true }).click();
        const box = learner.locator('#otp_contact_value');
        await box.pressSequentially(CODE_ONLY);
        if ((await box.inputValue()) !== CODE_ONLY) await box.fill(CODE_ONLY);
        await submit(learner, 'form:has(#otp_contact_value) button[type=submit]');
        const sentCode = /\/register\/otp/.test(path(learner));
        check('an account made by a code is sent a code from the checkout', sentCode, path(learner));
        if (sentCode) {
            await learner.locator('#code').fill(codeFor(CODE_ONLY) ?? '');
            await Promise.all([learner.waitForURL(/\/register\/set-password/, { timeout: 15000 }).catch(() => {}), learner.getByRole('button', { name: say('register.verify'), exact: true }).click()]);
            await learner.waitForLoadState('networkidle');
        }
        check('and its code leads to the password page', /\/dv\/courses\/register\/set-password$/.test(path(learner)), path(learner));
    } else {
        check('an open course for the checkout', false, 'none seeded');
    }
} else {
    check('the walks that need a code', true, 'skipped on a remote host');
}

await browser.close();
cleanUp();

for (const p of problems) check('no server error or page error', false, p);
const failedSteps = results.filter(([, ok]) => !ok);
for (const [step, ok, detail] of results) console.log(`${ok ? '✓' : '✗'} ${step}${detail && !ok ? ` — ${detail}` : ''}`);
console.log(`\nsignin-language: ${results.length - failedSteps.length}/${results.length} passed`);
process.exit(failedSteps.length ? 1 : 0);
