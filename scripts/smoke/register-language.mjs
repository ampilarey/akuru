/**
 * Does the course registration read in Dhivehi and Arabic? (BACKLOG C21,
 * slices RG1a and RG1b, STATUS §5qz and §5ra.)
 *
 * A visitor opens a course's checkout under /dv and /ar. The walk lists what
 * is still in Latin letters in the page's main — every text node,
 * placeholder, aria-label and title — leaving out the course's own name and
 * category (the author's words), and what is typed the same in any language
 * (an address, a number). The page must be right to left and every field in
 * it named.
 *
 * Then, in Dhivehi, the visitor:
 *   - fills in a new registration with no ID card number, and is told so in
 *     Dhivehi, under the form;
 *   - asks for a code with an email address nobody has, from the checkout's
 *     "use a code instead" box, and is sent a code by email — told in Dhivehi, on a code
 *     page in Dhivehi (the box set its type from the first contact box on the
 *     page, so an email was read as a phone number and refused);
 *   - opens the checkout of a course whose enrolment is closed, planted for
 *     the walk, and is told so in Dhivehi, with no form to start.
 *
 * The planted course, and the unverified account the code request makes for
 * its address, are removed first and last. The refused registration stops
 * before any code.
 *
 * Past the code (slice RG1b), in Dhivehi:
 *   - an account planted for the walk logs in at the checkout and lands on the
 *     details form, read whole — both ways of enrolling, one of them hidden —
 *     for right to left, English and unnamed fields;
 *   - an adult under eighteen is refused there in Dhivehi, told to choose the
 *     parent's way, and the resume link is sent, said once;
 *   - "use a code instead" with a number nobody has makes an account for it;
 *     the code (read from the log sender, so on a local server only) leads to
 *     the password page, read the same way. A learner under eighteen is
 *     refused there in Dhivehi, and the form corrected and sent again makes
 *     the account: the refusal used to come after the account was claimed,
 *     with the code spent, so the next send went back to the code page.
 *
 * The planted account and the one made for the number are removed first and
 * last too.
 *
 *   node scripts/smoke/register-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
// An address nobody has: a verified account's address is sent back to log in with its password instead.
const NOBODY = 'smoke-rg1@akuru.invalid';
const CLOSED = 'smoke-rg1-closed';
const ALWAYS_FINE = [/https?:\/\/\S*/g, /[\w.*+-]+@[\w-]+(\.[\w-]+)+/g, /\bA\d{6}\b/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const dvSays = (key, args = '[]') => tinker(`echo __('${key}', ${args}, 'dv');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

function watch(page) {
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
}

const readMain = (page) => page.evaluate(() => {
    const main = document.querySelector('main') || document.body;
    const texts = [];
    const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        if (walker.currentNode.parentElement?.closest('style, script, textarea, [hidden]')) continue;
        const value = walker.currentNode.nodeValue.trim();
        if (value) texts.push(value);
    }
    main.querySelectorAll('[placeholder],[aria-label],[title]').forEach((el) => {
        ['placeholder', 'aria-label', 'title'].forEach((name) => {
            const value = el.getAttribute(name);
            if (value && value.trim()) texts.push(value.trim());
        });
    });
    const unnamed = [...main.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((el) => !el.getAttribute('aria-label')
        && !(el.id && main.querySelector(`label[for="${el.id}"]`)) && !el.closest('label')).map((el) => el.outerHTML.slice(0, 80));
    return { texts, unnamed, dir: document.documentElement.getAttribute('dir') };
});

const english = (strings, allowed) => strings.filter((raw) => {
    let rest = raw;
    for (const word of allowed) rest = rest.split(word).join(' ');
    for (const pattern of ALWAYS_FINE) rest = rest.replace(pattern, ' ');
    return /[A-Za-z]{2,}/.test(rest);
});

const cleanUp = () => tinker(`\\App\\Domains\\Courses\\Models\\Course::query()->where('slug', '${CLOSED}')->get()->each->forceDelete(); $ids = DB::table('user_contacts')->where('value', '${NOBODY}')->whereNull('verified_at')->pluck('user_id'); DB::table('user_contact_otps')->whereIn('user_contact_id', DB::table('user_contacts')->where('value', '${NOBODY}')->pluck('id'))->delete(); DB::table('user_contacts')->where('value', '${NOBODY}')->delete(); DB::table('users')->whereIn('id', $ids)->delete(); echo 'ok';`);

cleanUp();
const open = JSON.parse(tinker(`$c = \\App\\Domains\\Courses\\Models\\Course::query()->where('status', 'open')->where('workflow_status', 'published')->get()->first(fn ($c) => $c->is_enrollment_open); echo json_encode(['slug' => $c->slug, 'title' => $c->title, 'category' => $c->category?->name]);`));
tinker(`\\App\\Domains\\Courses\\Models\\Course::factory()->create(['slug' => '${CLOSED}', 'title' => 'SMOKE RG1 Closed', 'status' => 'closed']); echo 'ok';`);

const page = await (await browser.newContext()).newPage();
watch(page);
const authors = [open.title, open.category].filter(Boolean);

for (const locale of ['dv', 'ar']) {
    const path = `/${locale}/courses/${open.slug}/checkout`;
    const response = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    if (!response || response.status() >= 400 || new URL(page.url()).pathname !== path) {
        check(`${path}: the page opens`, false, `HTTP ${response?.status()} ${page.url().replace(BASE, '')}`);
        continue;
    }
    for (const tab of ['new', 'login']) {
        if (tab === 'login') await page.getByRole('button', { name: dvOrAr(locale, 'tab_login'), exact: true }).click();
        const found = await readMain(page);
        const left = english(found.texts, authors);
        check(`${path} (${tab}): right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${path} (${tab}): nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
        check(`${path} (${tab}): every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
    }
}

/**
 * Ask for a code from the checkout's "use a code instead" box. Waits for the
 * page's scripts first: one run in four typed into the box before Alpine had
 * started, and the box was sent empty. The value is read back before sending.
 */
async function askForCode(on, contact) {
    await on.waitForFunction(() => window.Alpine !== undefined);
    await on.getByRole('button', { name: dvOrAr('dv', 'tab_login'), exact: true }).click();
    await on.getByRole('link', { name: dvOrAr('dv', 'use_code'), exact: true }).click();
    const box = on.locator('#otp_contact_value');
    await box.pressSequentially(contact);
    if ((await box.inputValue()) !== contact) await box.fill(contact);
    await Promise.all([on.waitForURL(/\/register\/otp/, { timeout: 15000 }).catch(() => {}), on.locator('form:has(#otp_contact_value) button[type=submit]').click()]);
    await on.waitForLoadState('networkidle');
}

function dvOrAr(locale, key) {
    return tinker(`echo __('register.${key}', [], '${locale}');`);
}

// ------------------------------------- a new registration with no ID card number
const book = (key) => dvSays(`register.${key}`);
await page.goto(`${BASE}/dv/courses/${open.slug}/checkout`, { waitUntil: 'networkidle' });
await page.locator('input[type="tel"][name="contact_value"]').fill(`7${Math.floor(100000 + Math.random() * 899999)}`);
await page.getByTestId('flow-adult').click();
await page.locator('#co_first_name').fill('Aishath');
await page.locator('#co_last_name').fill('Smoke');
await page.locator('#co_gender').selectOption('female');
await page.locator('#co_dob').fill('1995-05-05');
await page.locator('#co_password').fill('smoke-pass-1');
await page.locator('#co_password_confirmation').fill('smoke-pass-1');
await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: book('send_code') }).first().click()]);
const idCard = book('error_id_card');
check('a registration with no ID card number is refused in Dhivehi', flat(await page.locator('main').textContent()).includes(idCard), flat(await page.locator('main [role=alert]').first().textContent().catch(() => '')).slice(0, 140));

// ------------------------------------- a code login with an email
await page.goto(`${BASE}/dv/courses/${open.slug}/checkout`, { waitUntil: 'networkidle' });
await askForCode(page, NOBODY);
const sentEmail = book('flash_code_sent_email');
const codePage = flat(await page.locator('main').textContent());
check('a code asked for with an email is sent by email, told in Dhivehi', codePage.includes(sentEmail) && codePage.includes(book('code_sent_email')), `${page.url().replace(BASE, '')} ${codePage.slice(0, 140)}`);
const codeFound = await readMain(page);
check('the code page reads in Dhivehi, every field named', english(codeFound.texts, []).length === 0 && codeFound.unnamed.length === 0, english(codeFound.texts, []).slice(0, 4).join(' | '));

// ------------------------------------- a closed course
await page.goto(`${BASE}/dv/courses/${CLOSED}/checkout`, { waitUntil: 'networkidle' });
const closedPage = flat(await page.locator('main').textContent());
check('a closed course\'s checkout says enrolment is closed, in Dhivehi, with no form',
    closedPage.includes(book('closed_title')) && (await page.locator('main input[name="contact_value"]').count()) === 0,
    closedPage.slice(0, 140));

// ===================================== slice RG1b: the details form and the password page
// A verified account with a password, planted for the walk, logs in at the checkout and lands on the details form.
const PLANTED = 'smoke-rg1b@akuru.invalid';
// A number nobody has: the code box makes an account for it, and its first password is set on the password page.
const NEW_PHONE = '9' + String(Math.floor(Math.random() * 1e6)).padStart(6, '0');
const idNumber = () => `A${String(Math.floor(Math.random() * 1e6)).padStart(6, '0')}`;
const yearsAgo = (years) => { const d = new Date(); d.setFullYear(d.getFullYear() - years); return d.toISOString().slice(0, 10); };
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);

const cleanUpB = () => tinker(`$ids = DB::table('user_contacts')->where('value', '${PLANTED}')->orWhere('value', 'like', '%${NEW_PHONE}')->pluck('user_id'); DB::table('user_contact_otps')->whereIn('user_contact_id', DB::table('user_contacts')->whereIn('user_id', $ids)->pluck('id'))->delete(); DB::table('registration_flows')->whereIn('user_id', $ids)->delete(); DB::table('user_contacts')->whereIn('user_id', $ids)->delete(); DB::table('users')->whereIn('id', $ids)->delete(); echo 'ok';`);
cleanUpB();
tinker(`$u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'Smoke Learner', 'password' => Hash::make('smoke-pass-1'), 'force_password_change' => false]); DB::table('user_contacts')->insert(['user_id' => $u->id, 'type' => 'email', 'value' => '${PLANTED}', 'is_primary' => true, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]); echo 'ok';`);

const details = await (await browser.newContext()).newPage();
watch(details);
await details.goto(`${BASE}/dv/courses/${open.slug}/checkout`, { waitUntil: 'networkidle' });
await details.waitForFunction(() => window.Alpine !== undefined);
await details.getByRole('button', { name: book('tab_login'), exact: true }).click();
await details.locator('#co_login_contact').fill(PLANTED);
await details.locator('#co_login_password').fill('smoke-pass-1');
await Promise.all([details.waitForURL(/\/register\/continue/, { timeout: 15000 }).catch(() => {}), details.getByRole('button', { name: book('log_in'), exact: true }).click()]);
await details.waitForLoadState('networkidle');
check('logging in at the checkout lands on the details form, in Dhivehi', /\/dv\/courses\/register\/continue/.test(details.url()), details.url().replace(BASE, ''));

// Both ways of enrolling are on the page (one hidden), so what is read is the whole form.
const form = await readMain(details);
const formLeft = english(form.texts, authors);
check('the details form: right to left', form.dir === 'rtl', `dir=${form.dir}`);
check('the details form: nothing left in English, either way of enrolling', formLeft.length === 0, formLeft.slice(0, 6).join(' | '));
check('the details form: every field is named', form.unnamed.length === 0, form.unnamed.slice(0, 3).join(' | '));

// An adult under eighteen, the terms ticked: refused in Dhivehi, naming the choice to make instead.
await details.getByTestId('flow-adult').click();
await details.locator('#ad_dob').fill(yearsAgo(15));
await details.locator('input[name="national_id"]:visible').fill(idNumber());
await details.getByTestId('enroll-terms-accept').check();
await Promise.all([details.waitForLoadState('networkidle'), details.getByTestId('enroll-submit').click()]);
await details.waitForLoadState('networkidle');
const tooYoung = tinker(`echo __('register.error_adult_self', ['option' => __('register.how_parent', [], 'dv')], 'dv');`);
const refusal = flat(await details.locator('main [role=alert]').first().textContent().catch(() => ''));
check('an adult under eighteen is refused in Dhivehi, told to choose the parent\'s way', refusal.includes(tooYoung), refusal.slice(0, 160));

// The resume link: sent, said in Dhivehi, and said once.
await Promise.all([details.waitForLoadState('networkidle'), details.getByTestId('resume-link-send').click()]);
await details.waitForLoadState('networkidle');
const linkSent = tinker(`echo __('register.flash_link_sent', ['contact' => '${PLANTED}'], 'dv');`);
const afterLink = flat(await details.locator('main').textContent());
check('the resume link is sent, said in Dhivehi, once', afterLink.split(linkSent).length - 1 === 1, `${afterLink.split(linkSent).length - 1} times: ${afterLink.slice(0, 120)}`);

// ------------------------------------- the password page
const fresh = await (await browser.newContext()).newPage();
watch(fresh);
await fresh.goto(`${BASE}/dv/courses/${open.slug}/checkout`, { waitUntil: 'networkidle' });
await askForCode(fresh, NEW_PHONE);
// The log sender's record of this number's code (STATUS §5ek: the number's own, not the newest receipt).
const sms = LOCAL ? tinker(`echo (string) (DB::table('sms_receipts')->where('phone', 'like', '%${NEW_PHONE}')->latest('id')->value('body') ?? '');`) : '';
const code = sms.match(/\b(\d{6})\b/)?.[1];
check('a code is sent to a number nobody has', Boolean(code), LOCAL ? (code ? 'found' : `no code in “${sms.slice(0, 60)}”`) : 'skipped on a remote host');

if (code) {
    await fresh.locator('#code').fill(code);
    await Promise.all([fresh.waitForURL(/\/register\/set-password/, { timeout: 15000 }).catch(() => {}), fresh.getByRole('button', { name: book('verify'), exact: true }).click()]);
    await fresh.waitForLoadState('networkidle');
    check('the code leads to the password page, in Dhivehi', /\/dv\/courses\/register\/set-password/.test(fresh.url()), fresh.url().replace(BASE, ''));

    const profile = await readMain(fresh);
    const profileLeft = english(profile.texts, []);
    check('the password page: right to left', profile.dir === 'rtl', `dir=${profile.dir}`);
    check('the password page: nothing left in English', profileLeft.length === 0, profileLeft.slice(0, 6).join(' | '));
    check('the password page: every field is named', profile.unnamed.length === 0, profile.unnamed.slice(0, 3).join(' | '));

    // A year of birth typed wrong: refused in Dhivehi, and the form is still the one to send.
    const fillProfile = async (dob) => {
        await fresh.locator('#sp_first_name').fill('Mariyam');
        await fresh.locator('#sp_last_name').fill('Smoke');
        await fresh.locator('#sp_gender').selectOption('female');
        await fresh.locator('#sp_dob').fill(dob);
        await fresh.locator('input[name="national_id"]').fill(idNumber());
        await fresh.locator('#pw').fill('smoke-pass-2');
        await fresh.locator('#pw2').fill('smoke-pass-2');
        await Promise.all([fresh.waitForLoadState('networkidle'), fresh.getByTestId('create-account').click()]);
        await fresh.waitForLoadState('networkidle');
    };
    await fillProfile(yearsAgo(12));
    const adultOnly = book('error_adult_password');
    const refused = flat(await fresh.locator('main [role=alert]').first().textContent().catch(() => ''));
    check('a learner under eighteen is refused on the password page, in Dhivehi', refused.includes(adultOnly) && /set-password/.test(fresh.url()), refused.slice(0, 160));

    // Corrected and sent again: the account is made (before, the refusal had spent the code and the next send went back to the code page).
    await fillProfile(yearsAgo(30));
    const ready = book('flash_account_ready');
    const next = flat(await fresh.locator('main').textContent());
    check('corrected and sent again, the account is made and the next step says so in Dhivehi', /\/register\/enroll\/confirm/.test(fresh.url()) && next.includes(ready), `${fresh.url().replace(BASE, '')} ${next.slice(0, 100)}`);
}

cleanUpB();

cleanUp();
await browser.close();

for (const problem of problems) results.push([problem, false, '']);
const failed = results.filter(([, ok]) => !ok);
for (const [step, ok, detail] of results) console.log(`${ok ? 'PASS' : 'FAIL'}  ${step}${detail ? ` — ${detail}` : ''}`);
console.log(`\n${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
