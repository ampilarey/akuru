/**
 * Does the course registration read in Dhivehi and Arabic? (BACKLOG C21,
 * slice RG1a, STATUS §5qz.)
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
await page.getByRole('button', { name: book('tab_login'), exact: true }).click();
await page.getByRole('link', { name: book('use_code'), exact: true }).click();
await page.locator('#otp_contact_value').pressSequentially(NOBODY);
await Promise.all([page.waitForURL(/\/register\/otp/, { timeout: 15000 }).catch(() => {}), page.locator('#otp_contact_value').press('Enter')]);
await page.waitForLoadState('networkidle');
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

cleanUp();
await browser.close();

for (const problem of problems) results.push([problem, false, '']);
const failed = results.filter(([, ok]) => !ok);
for (const [step, ok, detail] of results) console.log(`${ok ? 'PASS' : 'FAIL'}  ${step}${detail ? ` — ${detail}` : ''}`);
console.log(`\n${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
