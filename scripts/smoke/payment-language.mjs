/**
 * Do the payment pages read in Dhivehi and Arabic? (BACKLOG C21, slice PY1,
 * STATUS §5re.)
 *
 * A planted learner signs in, and the walk reads, in Dhivehi and Arabic:
 *   - the course checkout: right to left, nothing in English, its field
 *     named, the agreement's three links inside its own sentence. Under /dv,
 *     the terms left unticked are refused as a list item (the page printed
 *     escaped `<li>` markup), and a payment that cannot start is said once,
 *     in Dhivehi, without the exception's own text;
 *   - the page back from the bank, which lies outside the localised routes:
 *     a confirmed payment reads in the language the family was reading, with
 *     the bank's required lines, and Continue leads to the learner's
 *     enrolments in that language. Then, in Dhivehi, a payment the bank
 *     turned down is said at once, and one still pending is checked and
 *     turns to received on the page when the bank's answer lands;
 *   - a receipt for cash taken at the office: the month in the page's
 *     language, how it was paid and where it was recorded named;
 *   - the page for a return that lost its reference, its link inside its
 *     sentence.
 *
 * What is typed or printed the same in any language is left out: a payment's
 * reference, a currency's code, the institute's web address, a card brand's
 * mark, the planted names. The planted rows are removed first and last. Local server only.
 *
 *   node scripts/smoke/payment-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const ALWAYS_FINE = [
    /https?:\/\/\S*/g, /[\w.*+-]+@[\w-]+(\.[\w-]+)+/g, /\bakuru\.edu\.mv\b/g, /\bAKURU-[A-Z0-9-]+\b/g,
    /\b(MVR|BML|PDF)\b/g, /\bSMOKE PY1( Seerah)?\b/g,
    // The card brand's own mark, drawn in the checkout's logo.
    /\bVISA\b/g,
];
const MOBILE = '7999231';
const PASSWORD = 'smoke-py1-pass';
const SLUG = 'smoke-py1-seerah';
const REF = { ok: 'AKURU-SMOKE-PY1-OK', no: 'AKURU-SMOKE-PY1-NO', wait: 'AKURU-SMOKE-PY1-WAIT', cash: 'AKURU-SMOKE-PY1-CASH' };

if (!LOCAL) {
    console.log('payment-language plants rows with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv', args = '[]') => tinker(`echo __('${key}', ${args}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();
const path = (page) => new URL(page.url()).pathname;

const cleanUp = () => tinker(`$ids = DB::table('user_contacts')->where('value', '+960${MOBILE}')->pluck('user_id'); $course = DB::table('courses')->where('slug', '${SLUG}')->value('id'); DB::table('payments')->where('merchant_reference', 'like', 'AKURU-SMOKE-PY1-%')->orWhereIn('user_id', $ids)->orWhere('course_id', $course ?? 0)->delete(); DB::table('course_enrollments')->where('course_id', $course ?? 0)->delete(); DB::table('courses')->where('slug', '${SLUG}')->delete(); DB::table('user_contacts')->where('value', '+960${MOBILE}')->delete(); DB::table('users')->whereIn('id', $ids)->delete(); echo 'ok';`);

cleanUp();
tinker(`$u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'SMOKE PY1', 'password' => Hash::make('${PASSWORD}'), 'force_password_change' => false]); DB::table('user_contacts')->insert(['user_id' => $u->id, 'type' => 'mobile', 'value' => '+960${MOBILE}', 'is_primary' => 1, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]); \\App\\Domains\\Courses\\Models\\Course::factory()->create(['title' => 'SMOKE PY1 Seerah', 'slug' => '${SLUG}', 'short_desc' => 'ސީރަތު', 'registration_fee_amount' => 150, 'registration_fee_currency' => 'MVR']); foreach ([['${REF.ok}', 'confirmed', 'bml', null], ['${REF.no}', 'failed', 'bml', null], ['${REF.wait}', 'pending', 'bml', null], ['${REF.cash}', 'confirmed', 'manual', 'cash']] as [$ref, $status, $provider, $method]) { \\App\\Domains\\Finance\\Models\\Payment::query()->create(['user_id' => $u->id, 'amount' => 250, 'currency' => 'MVR', 'status' => $status, 'provider' => $provider, 'payment_method' => $method, 'merchant_reference' => $ref, 'paid_at' => $status === 'confirmed' ? '2026-10-11 09:30:00' : null, 'confirmed_at' => $status === 'confirmed' ? now() : null]); } echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

async function submit(selector) {
    await Promise.all([page.waitForLoadState('networkidle'), page.locator(selector).first().click()]);
    await page.waitForLoadState('networkidle');
}

/** The page's main (the header and footer are other walks'): its words, its unnamed fields, its direction. */
const readPage = () => page.evaluate(() => {
    const root = document.querySelector('main') || document.body;
    const texts = [];
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        const parent = walker.currentNode.parentElement;
        if (parent?.closest('style, script, textarea, [hidden], noscript, .hidden')) continue;
        const value = walker.currentNode.nodeValue.trim();
        if (value) texts.push(value);
    }
    root.querySelectorAll('[placeholder],[aria-label],[title],img[alt]').forEach((el) => {
        ['placeholder', 'aria-label', 'title', 'alt'].forEach((name) => {
            const value = el.getAttribute(name);
            if (value && value.trim() && !el.closest('[aria-hidden="true"]')) texts.push(value.trim());
        });
    });
    const unnamed = [...root.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((el) => !el.getAttribute('aria-label')
        && !(el.id && document.querySelector(`label[for="${el.id}"]`)) && !el.closest('label')).map((el) => el.outerHTML.slice(0, 80));
    return { texts, unnamed, dir: document.documentElement.getAttribute('dir') };
});

const english = (strings) => strings.filter((raw) => {
    let rest = raw;
    for (const pattern of ALWAYS_FINE) rest = rest.replace(pattern, ' ');
    return /[A-Za-z]{2,}/.test(rest);
});

async function readsIn(label) {
    const found = await readPage();
    const left = english(found.texts);
    check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
    check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
    check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
}

const visible = (selector) => page.locator(selector).first().isVisible().catch(() => false);
const mainText = async () => flat(await page.locator('main').first().textContent().catch(() => ''));

// ------------------------------------------------ signing in
await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('#identifier').fill(MOBILE);
await page.locator('#password').fill(PASSWORD);
await submit('form[action$="/login"] button[type="submit"]');
check('the planted learner signs in', !path(page).endsWith('/login'), path(page));

// ------------------------------------------------ the course checkout, under /dv and /ar
for (const locale of ['dv', 'ar']) {
    const response = await page.goto(`${BASE}/${locale}/checkout/course/${SLUG}`, { waitUntil: 'networkidle' });
    if (!response || response.status() >= 400) {
        check(`/${locale} checkout: the page opens`, false, `HTTP ${response?.status()} ${path(page)}`);
        continue;
    }
    await readsIn(`/${locale} checkout`);
    check(`/${locale} checkout: the agreement's three links sit inside its sentence`, await page.locator('label[for="accept_terms"] a').count() === 3);
}

await page.goto(`${BASE}/dv/checkout/course/${SLUG}`, { waitUntil: 'networkidle' });
// The browser would not send an unticked box it requires; the server's own refusal is what is walked.
await page.locator('input[name="accept_terms"]').evaluate((box) => box.removeAttribute('required'));
await submit('#checkout-form button[type="submit"]');
const terms = say('register.error_terms_all');
check('the terms unticked: refused in Dhivehi, as a list item', flat(await page.locator('[data-testid="checkout-errors"] li').first().textContent().catch(() => '')) === terms, (await mainText()).slice(0, 140));
check('the refusal is not printed as markup', !(await mainText()).includes('<li>'));

await page.locator('input[name="accept_terms"]').check();
await submit('#checkout-form button[type="submit"]');
const notStarted = say('register.error_payment_start');
const afterStart = flat(await page.locator('body').textContent());
check('a payment that cannot start is said once, in Dhivehi', afterStart.split(notStarted).length - 1 === 1, `${afterStart.split(notStarted).length - 1}× on ${path(page)}`);
check("the failure's own text is kept off the page", !afterStart.includes('Payment gateway not configured'));

// ------------------------------------------------ back from the bank, in the language the family was reading
for (const locale of ['dv', 'ar']) {
    await page.goto(`${BASE}/${locale}`, { waitUntil: 'networkidle' });
    await page.goto(`${BASE}/payments/bml/return?ref=${REF.ok}`, { waitUntil: 'networkidle' });
    await readsIn(`/${locale} back from the bank, paid`);
    check(`/${locale} back from the bank: the payment is received`, await visible('#payment-success') && !(await visible('#payment-pending')));
    check(`/${locale} back from the bank: the bank's required lines in the page's language`, (await mainText()).includes(say('payments.secure_ssl', locale)));
}
await page.locator('#payment-success a').last().click({ timeout: 5000 }).catch(() => {});
await page.waitForLoadState('networkidle');
check("Continue leads to the learner's enrolments, in the language", path(page) === '/ar/my-enrollments', path(page));

await page.goto(`${BASE}/dv`, { waitUntil: 'networkidle' });
await page.goto(`${BASE}/payments/bml/return?ref=${REF.no}`, { waitUntil: 'networkidle' });
check('a payment the bank turned down is said at once, in Dhivehi', await visible('#payment-failed') && !(await visible('#payment-pending'))
    && flat(await page.locator('#payment-failed p').first().textContent()) === say('payments.not_paid'), (await mainText()).slice(0, 120));

await page.goto(`${BASE}/payments/bml/return?ref=${REF.wait}`, { waitUntil: 'networkidle' });
check('a payment still pending is checked', await visible('#payment-pending') && !(await visible('#payment-success')));
tinker(`DB::table('payments')->where('merchant_reference', '${REF.wait}')->update(['status' => 'confirmed', 'paid_at' => now(), 'confirmed_at' => now()]); echo 'ok';`);
const landed = await page.locator('#payment-success').waitFor({ state: 'visible', timeout: 12000 }).then(() => true).catch(() => false);
check("it turns to received on the page when the bank's answer lands", landed && !(await visible('#payment-pending')));

// ------------------------------------------------ a receipt for cash taken at the office
const cashId = tinker(`echo DB::table('payments')->where('merchant_reference', '${REF.cash}')->value('id');`);
await page.goto(`${BASE}/dv/payments/${cashId}/receipt`, { waitUntil: 'networkidle' });
await readsIn('/dv receipt');
const receipt = await mainText();
check('the receipt names the month in Dhivehi', receipt.includes('11 އޮކްޓޫބަރު 2026'), receipt.slice(0, 160));
check('the receipt names how it was paid and where it was recorded', receipt.includes(say('payments.method_cash')) && receipt.includes(say('payments.gateway_manual')));

// ------------------------------------------------ a return that lost its reference
await page.goto(`${BASE}/payments/bml/return`, { waitUntil: 'networkidle' });
await readsIn('/dv a lost reference');
const sentence = say('payments.missing_check').replace(':enrollments', say('payments.your_enrollments'));
const withLink = page.locator('main p', { has: page.locator('a[href$="/my-enrollments"]') }).first();
check("a lost reference's link sits inside its sentence", flat(await withLink.textContent().catch(() => '')) === flat(sentence), flat(await withLink.textContent().catch(() => '')).slice(0, 120));

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
