/**
 * Do the office's HR screens read in Dhivehi and Arabic?
 * (BACKLOG C21, slice HR1, STATUS §5ql.)
 *
 * The office opens the leave types, the expiring documents, the contracts,
 * staff attendance and its reports, the leave balances, payroll and the HR
 * settings under /dv and /ar. The walk lists what is still in Latin letters
 * in each page's main: every text node, placeholder, aria-label, title and
 * phone caption (`data-label`). What a page shows of its data (a member of
 * staff's name, a department, a checklist item, a leave type the school
 * named only in English) is the author's, so a string found among the
 * page's props passes; codes the server sends to be named (a leave type's
 * code, a contract's type and state, a day's attendance and how it was
 * recorded, a document's type, a payslip's state) do not, and printed raw
 * they fail. So do the phrase books the page is sent. Each page must be
 * right to left, every field in it named, and it must open where it was
 * asked for.
 *
 * Then, in Dhivehi:
 *   - the office saves the Annual leave type as the form fills it in, and is
 *     told it was updated — saving it was a 500, the form only ever making a
 *     new type and every code being taken; no type is added, and its days
 *     stay as they were;
 *   - the office marks a day with late minutes that are no number and is
 *     refused under the form, the field named in Dhivehi (Laravel's own
 *     rule, which named it in English); no day is recorded;
 *   - the office imports a file whose second row is no member of staff and
 *     is refused by the row's number; the first row is not kept either (it
 *     was);
 *   - the office adjusts a leave balance with no reason and is refused
 *     under the row, the field named in Dhivehi; the balance stays;
 *   - the office empties the onboarding checklist and is refused under it;
 *     the checklist stays.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/hr-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Props that carry codes the page must name, and the phrase books it is sent:
// their values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'csvUrl', 'href',
    // A leave type's code, a contract's type and state, a day's attendance
    // and how it was recorded, a document's type, a payslip's state.
    'code', 'codes', 'leave_code', 'contract_type', 'status', 'statuses', 'source', 'document_type',
]);
// A list of codes when it is a list of strings — the contract types — and
// the school's own records otherwise: the leave types are `types` too.
const CODE_LISTS = new Set(['types']);
// The names of formats and of the host's switch, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bPAYROLL_ENABLED\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

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
    if (CODE_KEYS.has(key) || (CODE_LISTS.has(key) && Array.isArray(value) && value.every((item) => typeof item === 'string'))) {
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

// The author's words come out first, longest first, so a sentence the
// author wrote is not left half-taken (slice PT1b).
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

// Every piece of text a reader meets in the page's main, and whether it runs
// right to left; and every field with no name.
const readMain = (page) => page.evaluate(() => {
    const main = document.querySelector('main') || document.body;
    const texts = [];
    const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        // A field's value is not one of its labels: an input's is not read,
        // and neither is a textarea's — a checklist is written in one.
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

const inDhivehi = (said) => Boolean(said) && /\p{Script=Thaana}/u.test(said) && !/[A-Za-z]{3,}/.test(said);
const said = async (locator) => (await locator.first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
const office = await signIn(ADMIN);

// ------------------------------------- every screen, in Dhivehi and Arabic

const screens = [
    '/hr/leave-types',
    '/hr/compliance',
    '/hr/contracts',
    '/hr/attendance/reports',
    '/hr/attendance',
    '/hr/leave-balances',
    '/hr/payroll',
    '/hr/settings',
];
for (const locale of ['dv', 'ar']) {
    for (const path of screens) {
        const label = `${locale}${path}`;
        const response = await office.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        if (!response || response.status() >= 400 || new URL(office.url()).pathname !== `/${locale}${path}`) {
            check(`${label}: the page opens`, false, `HTTP ${response?.status()} ${office.url().replace(BASE, '')}`);
            continue;
        }
        const found = await readMain(office);
        const left = english(found.texts, authorsWords(await props(office)));
        check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
        check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
    }
}

// ------------------------------------- a leave type saved, said in Dhivehi

await office.goto(`${BASE}/dv/hr/leave-types`, { waitUntil: 'networkidle' });
const book = (await props(office)).t ?? {};
const typesForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.types_save, exact: true }) }).first();
const typeCount = () => Number(tinker("echo DB::table('leave_types')->count();"));
const annualDays = () => tinker("echo (float) DB::table('leave_types')->where('code', 'annual')->value('days_per_year');");
const [typesBefore, daysBefore] = [typeCount(), annualDays()];
await typesForm.getByLabel(book.types_code, { exact: true }).selectOption('annual');
const filled = await typesForm.getByLabel(book.types_days_year, { exact: true }).inputValue();
await typesForm.getByRole('button', { name: book.types_save, exact: true }).click();
await office.waitForLoadState('networkidle');
const typeSaved = await said(office.getByTestId('flash-success'));
const [typesAfter, daysAfter] = [typeCount(), annualDays()];
check('saving the Annual leave type as the form fills it is said in Dhivehi; no type is added and its days stay', typeSaved === book.flash_leave_type_updated && inDhivehi(typeSaved) && typesAfter === typesBefore && Number(filled) === Number(daysBefore) && daysAfter === daysBefore,
    `said: ${typeSaved ?? 'nothing'}; types ${typesBefore} → ${typesAfter}; days ${daysBefore} (filled ${filled}) → ${daysAfter}`);

// ------------------------------------- late minutes that are no number, refused in Dhivehi

const daysOn = (date) => Number(tinker(`echo DB::table('staff_attendance')->whereDate('date', '${date}')->count();`));
const allDays = () => Number(tinker("echo DB::table('staff_attendance')->count();"));
const notANumber = tinker("echo __('validation.integer', ['attribute' => __('validation.attributes.minutes_late', [], 'dv')], 'dv');");
await office.goto(`${BASE}/dv/hr/attendance`, { waitUntil: 'networkidle' });
const markForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.attendance_mark, exact: true }) }).first();
const daysBeforeMark = allDays();
await markForm.getByLabel(book.attendance_late_minutes, { exact: true }).fill('ten');
await markForm.getByRole('button', { name: book.attendance_mark, exact: true }).click();
await office.waitForLoadState('networkidle');
const lateRefused = await said(markForm.locator('span.text-red-600'));
check('late minutes that are no number are refused under the form, the field named in Dhivehi; no day is recorded', lateRefused === notANumber && inDhivehi(lateRefused) && allDays() === daysBeforeMark, `said: ${lateRefused ?? 'nothing'}`);

// ------------------------------------- an import with an unknown row, refused by its number

const yearStart = tinker("echo DB::table('academic_years')->where('status', 'active')->value('start_date');").slice(0, 10);
const staffId = Number(tinker("echo (int) DB::table('staff_profiles')->where('status', 'active')->orderBy('id')->value('id');"));
await office.goto(`${BASE}/dv/hr/attendance`, { waitUntil: 'networkidle' });
const importForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.attendance_import, exact: true }) }).first();
const importedBefore = daysOn(yearStart);
await importForm.getByLabel(book.attendance_file, { exact: true }).setInputFiles({
    name: 'attendance.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from(`staff_profile_id,date,status\n${staffId},${yearStart},present\n999999,${yearStart},present\n`),
});
await importForm.getByRole('button', { name: book.attendance_import, exact: true }).click();
await office.waitForLoadState('networkidle');
const unknownRow = await said(importForm.locator('p.text-red-600'));
const importedAfter = daysOn(yearStart);
check('an import whose second row is nobody is refused by its number, in Dhivehi; its first row is not kept', staffId > 0 && unknownRow === (book.error_csv_unknown_staff || '').replace(':row', '3') && inDhivehi(unknownRow) && importedAfter === importedBefore,
    `said: ${unknownRow ?? 'nothing'}; days on ${yearStart}: ${importedBefore} → ${importedAfter}`);

// ------------------------------------- a balance adjusted with no reason, refused in Dhivehi

const required = tinker("echo __('validation.required', ['attribute' => __('validation.attributes.reason', [], 'dv')], 'dv');");
await office.goto(`${BASE}/dv/hr/leave-balances`, { waitUntil: 'networkidle' });
const balanceRow = office.locator('tbody tr').filter({ has: office.locator('form') }).first();
if (await balanceRow.count()) {
    const entitlement = (await balanceRow.locator('form').first().evaluate((form) => form.closest('tr')?.textContent ?? '')).trim();
    const adjustedBefore = tinker("echo json_encode(DB::table('leave_entitlements')->orderBy('id')->pluck('adjusted_days'));");
    await balanceRow.locator('input').first().fill('1');
    await balanceRow.getByRole('button', { name: book.save, exact: true }).click();
    await office.waitForLoadState('networkidle');
    const noReason = await said(balanceRow.locator('.text-red-600'));
    const adjustedAfter = tinker("echo json_encode(DB::table('leave_entitlements')->orderBy('id')->pluck('adjusted_days'));");
    check('a balance adjusted with no reason is refused under its row, the field named in Dhivehi; it stays', noReason === required && inDhivehi(noReason) && adjustedAfter === adjustedBefore, `said: ${noReason ?? 'nothing'}; row: ${entitlement.slice(0, 60)}`);
} else {
    check('a balance adjusted with no reason is refused under its row, the field named in Dhivehi; it stays', false, 'no balance on the list');
}

// ------------------------------------- an empty checklist, refused in Dhivehi

const checklist = () => tinker("echo json_encode(app(App\\Domains\\HR\\Actions\\ResolveHrChecklistSettingsAction::class)->execute()['onboarding']);");
await office.goto(`${BASE}/dv/hr/settings`, { waitUntil: 'networkidle' });
const hrForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.settings_save_hr, exact: true }) }).first();
const listBefore = checklist();
await hrForm.locator('textarea').first().fill('');
await hrForm.getByRole('button', { name: book.settings_save_hr, exact: true }).click();
await office.waitForLoadState('networkidle');
const emptyList = await said(hrForm.locator('span.text-red-600'));
const listAfter = checklist();
check('an empty onboarding checklist is refused under it, in Dhivehi; the checklist stays', emptyList === book.error_checklist_empty && inDhivehi(emptyList) && listAfter === listBefore, `said: ${emptyList ?? 'nothing'}`);

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
