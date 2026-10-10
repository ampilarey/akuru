/**
 * Do the system screens read in Dhivehi and Arabic? (BACKLOG C21, slice SY1,
 * STATUS §5qu.)
 *
 * The system admin opens the translations editor, the operator checklist and
 * the OTP abuse list under /dv and /ar. The walk lists what is still in Latin
 * letters in each page's main: every text node, placeholder, aria-label,
 * title and phone caption (`data-label`). What a page shows of its data (a
 * checklist item, a string being translated, its key and its book) is the
 * author's, so a string found among the page's props passes; the phrase book
 * the page is sent does not. Each page must be right to left, every field in
 * it named, and it must open where it was asked for.
 *
 * Then, in Dhivehi, the system admin:
 *   - saves a correction to one Dhivehi string, and the editor says it is
 *     active, in Dhivehi; clears it again, the file's string back;
 *   - ticks a checklist item, which is struck through with who ticked it;
 *     unticks it again;
 *   - reads an abuse event planted for the walk, what tripped and its
 *     channel named in Dhivehi.
 *
 * The walk's correction, tick and event are removed first and last.
 *
 *   node scripts/smoke/system-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_SUPER_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
// The string the walk corrects, in one of the books the editor offers.
const GROUP = 'common';
const KEY = 'dashboard';
const MARK = 'SMOKE-SY1';

// Props that carry the phrase books and codes the page must name: their
// values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set(['i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'href', 'kinds', 'channel']);
// A format, a file and a unit, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bOTP\b/g, /\bSMS\b/g, /\bSTATUS\.md\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

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

async function readScreen(page, locale, path, query = '') {
    const label = `${locale}${path}${query}`;
    const response = await page.goto(`${BASE}/${locale}${path}${query}`, { waitUntil: 'networkidle' });
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

const dvSays = (key, args = '[]') => tinker(`echo __('${key}', ${args}, 'dv');`);
const ids = JSON.parse(tinker(`echo json_encode(['admin' => (int) DB::table('users')->where('email', '${SUPER}')->value('id'), 'item' => (string) collect(app(App\\Domains\\Settings\\Actions\\ListOperatorChecklistAction::class)->execute()['sections'])->flatMap(fn ($s) => $s['items'])->first(fn ($i) => ! DB::table('operator_checklist_checks')->where('item_key', $i['key'])->exists())['key']]);`));
const cleanUp = () => tinker(`DB::table('translation_overrides')->where('group', '${GROUP}')->where('key', '${KEY}')->where('locale', 'dv')->delete(); DB::table('operator_checklist_checks')->where('item_key', '${ids.item}')->delete(); DB::table('otp_abuse_events')->where('contact_tail', 'SY1x')->delete(); echo 'ok';`);
check('the checklist has an item nobody has ticked, for the walk to tick', Boolean(ids.item), `item ${ids.item}`);

cleanUp();
// One abuse event, so the list has a row to name.
tinker(`DB::table('otp_abuse_events')->insert(['kind' => 'resend_cooldown', 'purpose' => 'login', 'channel' => 'sms', 'contact_hash' => hash('sha256', '${MARK}'), 'contact_tail' => 'SY1x', 'user_id' => null, 'observed' => 1, 'threshold' => 1, 'occurred_at' => now('Indian/Maldives'), 'created_at' => now(), 'updated_at' => now()]); echo 'ok';`);
const admin = await signIn(SUPER);

for (const locale of ['dv', 'ar']) {
    await readScreen(admin, locale, '/admin/translations', `?locale=dv&group=${GROUP}&q=${KEY}`);
    await readScreen(admin, locale, '/admin/operations');
    await readScreen(admin, locale, '/admin/users/otp-abuse');
}

// ------------------------------------- a correction, saved and cleared, said in Dhivehi
await admin.goto(`${BASE}/dv/admin/translations?locale=dv&group=${GROUP}&q=${KEY}`, { waitUntil: 'networkidle' });
const book = (await props(admin)).t ?? {};
const row = admin.locator('main tbody tr', { hasText: KEY }).first();
await row.locator('textarea').fill(`${MARK} ${KEY}`);
await row.getByRole('button', { name: book.tr_save, exact: true }).click();
const active = await row.getByText(book.tr_override_active, { exact: true }).waitFor({ timeout: 10000 }).then(() => true).catch(() => false);
const stored = tinker(`echo DB::table('translation_overrides')->where('group', '${GROUP}')->where('key', '${KEY}')->where('locale', 'dv')->value('value');`);
check('a correction is saved, and the editor says it is active, in Dhivehi', active && stored === `${MARK} ${KEY}`, `active: ${active}; stored: ${stored || 'nothing'}`);

await row.locator('textarea').fill('');
await row.getByRole('button', { name: book.tr_clear, exact: true }).click();
await row.getByText(book.tr_override_active, { exact: true }).waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});
const cleared = Number(tinker(`echo DB::table('translation_overrides')->where('group', '${GROUP}')->where('key', '${KEY}')->where('locale', 'dv')->count();`)) === 0;
check('it is cleared again, the file’s string back', cleared, `cleared: ${cleared}`);

// ------------------------------------- a tick, and back
await admin.goto(`${BASE}/dv/admin/operations`, { waitUntil: 'networkidle' });
const sections = (await props(admin)).sections ?? [];
const label = sections.flatMap((s) => s.items).find((i) => i.key === ids.item)?.label ?? '';
const box = admin.getByRole('checkbox', { name: label, exact: true }).first();
// The box shows the server's tick, so it turns only when the tick comes back.
await box.click();
await admin.locator('main li', { hasText: label }).first().locator('p.line-through').waitFor({ timeout: 10000 }).catch(() => {});
const ticked = Number(tinker(`echo DB::table('operator_checklist_checks')->where('item_key', '${ids.item}')->count();`)) === 1;
const struck = await admin.locator('main li', { hasText: label }).first().locator('p.line-through').count();
check('a checklist item is ticked, struck through, with who ticked it', ticked && struck === 1, `ticked: ${ticked}; struck: ${struck}`);
await admin.getByRole('checkbox', { name: label, exact: true }).first().click();
await admin.locator('main li', { hasText: label }).first().locator('p.line-through').waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});
check('and unticked again', Number(tinker(`echo DB::table('operator_checklist_checks')->where('item_key', '${ids.item}')->count();`)) === 0);

// ------------------------------------- an abuse event, named in Dhivehi
await admin.goto(`${BASE}/dv/admin/users/otp-abuse`, { waitUntil: 'networkidle' });
const abuseRow = (await admin.locator('main tbody tr', { hasText: 'SY1x' }).first().textContent().catch(() => '')) ?? '';
check('an abuse event’s kind and channel are named in Dhivehi',
    abuseRow.includes(dvSays('admin.otp_kind_resend_cooldown')) && abuseRow.includes(dvSays('admin.otp_channel_sms')) && abuseRow.includes(dvSays('admin.otp_abuse_no_account')),
    abuseRow.replace(/\s+/g, ' ').slice(0, 160));

cleanUp();
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
