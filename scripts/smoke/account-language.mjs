/**
 * Do a person's linked accounts and a teacher's schedule read in Dhivehi and
 * Arabic? (BACKLOG C21, slice AC1, STATUS §5qs.)
 *
 * The seeded teacher opens their linked accounts and their schedule under
 * /dv and /ar. The walk lists what is still in Latin letters in each page's
 * main: every text node, placeholder, aria-label, title and phone caption
 * (`data-label`). What a page shows of its data (a person's name, a
 * session's title, a course's) is the author's, so a string found among the
 * page's props passes; the phrase books the page is sent do not. Each page
 * must be right to left, every field in it named, and it must open where it
 * was asked for.
 *
 * Then, in Dhivehi, the teacher:
 *   - links the parent's account with a wrong password, and is refused under
 *     the form, in Dhivehi; nothing is linked;
 *   - links it with the right one, told in Dhivehi; the parent's account is
 *     listed by its roles in Dhivehi;
 *   - presses Unlink on a page left open while the link was undone
 *     elsewhere, and is refused above the list, in Dhivehi, the account no
 *     longer on it — a refused Unlink was said nowhere, and one whose row the
 *     reload took away would have gone with it;
 *   - links it again, switches to it, and is told in Dhivehi whose account
 *     it now is; switches back the same way;
 *   - unlinks it, told in Dhivehi, and nothing is left linked.
 *
 * The walk's own links are undone first and last, so other walks find the two
 * accounts apart. Rows are looked for in the page's main: the shell's drawer
 * lists the linked accounts too.
 *
 *   node scripts/smoke/account-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_TEACHER, SMOKE_PARENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Props that carry the phrase books and codes the page must name: their
// values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set(['i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'href', 'two_factor']);
const ALWAYS_FINE = [/https?:\/\/\S*/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();

function authorsWords(value, key = '', out = []) {
    if (CODE_KEYS.has(key)) {
        return out;
    }
    if (typeof value === 'string') {
        if (value.trim().length >= 2) {
            out.push(value.trim());
        }
    } else if (Array.isArray(value)) {
        value.forEach((item) => authorsWords(item, key, out));
    } else if (value && typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => authorsWords(v, k, out));
    }
    return out;
}

function english(strings, allowed) {
    const longestFirst = [...new Set(allowed)].sort((a, b) => b.length - a.length);
    return strings.filter((raw) => {
        let rest = raw;
        for (const word of longestFirst) {
            rest = rest.split(word).join(' ');
        }
        for (const pattern of ALWAYS_FINE) {
            rest = rest.replace(pattern, ' ');
        }
        return /[A-Za-z]{2,}/.test(rest);
    });
}

async function signIn(email) {
    const page = await (await browser.newContext()).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

const readMain = (page) => page.evaluate(() => {
    const main = document.querySelector('main') || document.body;
    const texts = [];
    const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        if (walker.currentNode.parentElement?.closest('style, script, textarea')) continue;
        const value = walker.currentNode.nodeValue.trim();
        if (value) texts.push(value);
    }
    main.querySelectorAll('[placeholder],[aria-label],[title],[data-label]').forEach((el) => {
        ['placeholder', 'aria-label', 'title', 'data-label'].forEach((name) => {
            const value = el.getAttribute(name);
            if (value && value.trim()) texts.push(value.trim());
        });
    });
    const unnamed = [...main.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((el) => !el.getAttribute('aria-label')
        && !(el.id && main.querySelector(`label[for="${el.id}"]`)) && !el.closest('label')).map((el) => el.outerHTML.slice(0, 80));
    return { texts, unnamed, dir: document.documentElement.getAttribute('dir') };
});

async function readScreen(page, locale, path) {
    const label = `${locale}${path}`;
    const response = await page.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
    const where = new URL(page.url());
    if (!response || response.status() >= 400 || where.pathname !== `/${locale}${path}`) {
        check(`${label}: the page opens`, false, `HTTP ${response?.status()} ${page.url().replace(BASE, '')}`);
        return;
    }
    const found = await readMain(page);
    const left = english(found.texts, authorsWords(await props(page)));
    check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
    check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
    check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
}

const inDhivehi = (said) => Boolean(said) && /\p{Script=Thaana}/u.test(said) && !/[A-Za-z]{3,}/.test(said);
const dvSays = (key, args = '[]') => tinker(`echo __('${key}', ${args}, 'dv');`);
const ids = JSON.parse(tinker(`echo json_encode(['teacher' => (int) DB::table('users')->where('email', '${TEACHER}')->value('id'), 'parent' => (int) DB::table('users')->where('email', '${PARENT}')->value('id'), 'parentName' => DB::table('users')->where('email', '${PARENT}')->value('name'), 'teacherName' => DB::table('users')->where('email', '${TEACHER}')->value('name')]);`));
const unlinkBoth = () => tinker(`DB::table('linked_accounts')->where(fn ($q) => $q->where('user_id', ${ids.teacher})->where('linked_user_id', ${ids.parent}))->orWhere(fn ($q) => $q->where('user_id', ${ids.parent})->where('linked_user_id', ${ids.teacher}))->delete(); echo 'ok';`);
const links = () => Number(tinker(`echo DB::table('linked_accounts')->whereIn('user_id', [${ids.teacher}, ${ids.parent}])->whereIn('linked_user_id', [${ids.teacher}, ${ids.parent}])->count();`));
// The link form's own limiter, so an earlier run's wrong passwords do not refuse this one.
const clearLimiter = () => tinker(`Illuminate\\Support\\Facades\\RateLimiter::clear('link-account|${ids.teacher}|127.0.0.1'); echo 'ok';`);

unlinkBoth();
clearLimiter();
const teacher = await signIn(TEACHER);

for (const locale of ['dv', 'ar']) {
    await readScreen(teacher, locale, '/account/linked');
    await readScreen(teacher, locale, '/teach/schedule');
}

// ------------------------------------- a wrong password, refused in Dhivehi

await teacher.goto(`${BASE}/dv/account/linked`, { waitUntil: 'networkidle' });
const book = (await props(teacher)).t ?? {};
const linkForm = teacher.locator('form').filter({ has: teacher.getByRole('button', { name: book.linked_link, exact: true }) }).first();
await linkForm.locator('label', { hasText: book.linked_identifier }).locator('input').fill(PARENT);
await linkForm.locator('label', { hasText: book.linked_password }).locator('input').fill('SMOKE-wrong-password');
await linkForm.getByRole('button', { name: book.linked_link, exact: true }).click();
const refusedLink = (await linkForm.locator('ul.text-red-600').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check('a wrong password is refused under the form, in Dhivehi; nothing is linked', refusedLink === dvSays('account.error_link_no_match') && links() === 0, `said: ${refusedLink ?? 'nothing'}`);

// ------------------------------------- linked, said in Dhivehi, listed by its roles

clearLimiter();
await linkForm.locator('label', { hasText: book.linked_identifier }).locator('input').fill(PARENT);
await linkForm.locator('label', { hasText: book.linked_password }).locator('input').fill(PASSWORD);
await linkForm.getByRole('button', { name: book.linked_link, exact: true }).click();
const linked = await teacher.getByText(dvSays('account.flash_linked', `['name' => '${ids.parentName}']`), { exact: true }).first().waitFor({ timeout: 10000 }).then(() => true).catch(() => false);
const row = teacher.locator('main li', { hasText: ids.parentName }).first();
const roles = (await row.locator('p.text-xs').textContent().catch(() => ''))?.trim() ?? '';
check('with the right password it is linked, said in Dhivehi, and listed by its roles in Dhivehi', linked && links() === 2 && inDhivehi(roles), `told: ${linked}; roles: ${roles || 'nothing'}`);

// ------------------------------------- an Unlink on a stale page, refused in Dhivehi

unlinkBoth();
await row.getByRole('button', { name: book.linked_unlink, exact: true }).click();
// The reload takes the account off the list, so its refusal is said above it.
const refusedUnlink = (await teacher.locator('main ul.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
const rowGone = (await teacher.locator('main li', { hasText: ids.parentName }).count()) === 0;
check('an Unlink on a page left open while the link was undone elsewhere is refused above the list, in Dhivehi, the account no longer on it', refusedUnlink === dvSays('account.error_account_not_linked') && rowGone, `said: ${refusedUnlink ?? 'nothing'}; row gone: ${rowGone}`);

// ------------------------------------- switching, said in Dhivehi, and back

clearLimiter();
await teacher.goto(`${BASE}/dv/account/linked`, { waitUntil: 'networkidle' });
await linkForm.locator('label', { hasText: book.linked_identifier }).locator('input').fill(PARENT);
await linkForm.locator('label', { hasText: book.linked_password }).locator('input').fill(PASSWORD);
await linkForm.getByRole('button', { name: book.linked_link, exact: true }).click();
await teacher.getByText(dvSays('account.flash_linked', `['name' => '${ids.parentName}']`), { exact: true }).first().waitFor({ timeout: 10000 }).catch(() => {});
await teacher.locator('main li', { hasText: ids.parentName }).first().getByRole('button', { name: book.linked_switch, exact: true }).click();
const switched = await teacher.getByText(dvSays('account.flash_switched', `['name' => '${ids.parentName}']`), { exact: true }).first().waitFor({ timeout: 15000 }).then(() => true).catch(() => false);
check('switching to the parent’s account says whose it now is, in Dhivehi', switched, `told: ${switched}; at ${teacher.url().replace(BASE, '')}`);

await teacher.goto(`${BASE}/dv/account/linked`, { waitUntil: 'networkidle' });
await teacher.locator('main li', { hasText: ids.teacherName }).first().getByRole('button', { name: book.linked_switch, exact: true }).click();
const back = await teacher.getByText(dvSays('account.flash_switched', `['name' => '${ids.teacherName}']`), { exact: true }).first().waitFor({ timeout: 15000 }).then(() => true).catch(() => false);
check('and back, said the same way', back, `told: ${back}`);

// ------------------------------------- unlinked, said in Dhivehi

await teacher.goto(`${BASE}/dv/account/linked`, { waitUntil: 'networkidle' });
await teacher.locator('main li', { hasText: ids.parentName }).first().getByRole('button', { name: book.linked_unlink, exact: true }).click();
const unlinked = await teacher.getByText(dvSays('account.flash_unlinked'), { exact: true }).first().waitFor({ timeout: 10000 }).then(() => true).catch(() => false);
check('unlinked, said in Dhivehi, and nothing is left linked', unlinked && links() === 0, `told: ${unlinked}; links: ${links()}`);

unlinkBoth();
await browser.close();

for (const problem of problems) {
    results.push([problem, false, '']);
}
const failed = results.filter(([, ok]) => !ok);
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${step}${detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
