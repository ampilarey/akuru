/**
 * Do the office's finance screens read in Dhivehi and Arabic?
 * (BACKLOG C21, slice FN1, STATUS §5qn.)
 *
 * The office opens the fee items, the fee structures, the invoices, the fee
 * adjustments, the payment plans and the finance settings under /dv and /ar.
 * The walk lists what is still in Latin letters in each page's main: every
 * text node, placeholder, aria-label, title and phone caption
 * (`data-label`). What a page shows of its data (a fee item the school named
 * only in English, a structure's name, a pupil's name, an invoice's number)
 * is the author's, so a string found among the page's props passes; codes
 * the server sends to be named (a fee's kind and how often it falls due, a
 * structure's reach and state, an invoice's, a plan's and an adjustment's
 * state, an adjustment's kind, basis and reach, an invoice's period) do
 * not, and printed raw they fail. So do the phrase books the page is sent.
 * Each page must be right to left, every field in it named, and it must open
 * where it was asked for.
 *
 * Then, in Dhivehi:
 *   - the office makes a fee item whose amount is no number and is refused
 *     under the form, the field named in Dhivehi; no item is made;
 *   - the office makes a structure for selected classes and ticks none, and
 *     is refused under the form; no structure is made;
 *   - the office copies last year's structures where there is no last year,
 *     and is refused beside the button — it was said nowhere;
 *   - the office generates drafts for a period that ends before it starts,
 *     and is refused under the form — it was said nowhere; no invoice is
 *     made;
 *   - the office saves an adjustment for some kinds of fee and ticks none,
 *     and is refused under the form — it was saved, and came off nothing;
 *   - the office opens a plan for `SMOKE-INV-OPEN` (planted by
 *     `SmokeMarkerSeeder` on somebody else's child, with no plan): the
 *     invoice says whose it is, its balance is split across the
 *     installments, due on the first of the next two months (they were
 *     February and March 2026); a first installment of 1 does not add up and
 *     is refused under the form, the balance in the sentence; no plan is
 *     made;
 *   - the office sets the reminder to 200 days, which its box holds (it
 *     allows 0 to 90); then saves the settings as they were, and is told
 *     so in Dhivehi.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/finance-language.mjs
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
    // A fee's kind and how often it falls due, a structure's reach and state,
    // an invoice's, a plan's and an adjustment's state, an adjustment's kind,
    // basis and reach, an invoice's period, the monthly mode.
    'type', 'types', 'frequency', 'frequencies', 'status', 'statuses', 'applies_to', 'appliesTo',
    'basis', 'bases', 'item_types', 'itemTypes', 'period_key', 'monthlyMode', 'invoice_monthly_mode',
]);
// The names of formats and of the currency, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bMVR\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const count = (table) => Number(tinker(`echo DB::table('${table}')->count();`));

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
// The school's own day (Indian/Maldives), not the walker's.
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Indian/Maldives' }).format(new Date());
const daysFromToday = (days) => new Intl.DateTimeFormat('en-CA', { timeZone: 'Indian/Maldives' }).format(new Date(Date.now() + days * 86400000));
const office = await signIn(ADMIN);

// ------------------------------------- every screen, in Dhivehi and Arabic

const screens = [
    '/finance/fee-items',
    '/finance/fee-structures',
    '/finance/invoices',
    '/finance/adjustments',
    '/finance/payment-plans',
    '/finance/settings',
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

// ------------------------------------- a fee item whose amount is no number

await office.goto(`${BASE}/dv/finance/fee-items`, { waitUntil: 'networkidle' });
const book = (await props(office)).t ?? {};
const notANumber = tinker("echo __('validation.numeric', ['attribute' => __('validation.attributes.default_amount', [], 'dv')], 'dv');");
const itemForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.fee_items_create, exact: true }) }).first();
const itemsBefore = count('fee_items');
await itemForm.getByLabel(book.name_en, { exact: true }).fill('SMOKE-Lang-Fee');
await itemForm.getByLabel(book.amount, { exact: true }).fill('lots');
await itemForm.getByRole('button', { name: book.fee_items_create, exact: true }).click();
await office.waitForLoadState('networkidle');
const noAmount = await said(itemForm.locator('ul.text-red-600'));
check('a fee item whose amount is no number is refused under the form, the field named in Dhivehi; no item is made', noAmount === notANumber && inDhivehi(noAmount) && count('fee_items') === itemsBefore, `said: ${noAmount ?? 'nothing'}`);

// ------------------------------------- a structure for selected classes, none ticked

await office.goto(`${BASE}/dv/finance/fee-structures`, { waitUntil: 'networkidle' });
const structureForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.structures_create, exact: true }) }).first();
const structuresBefore = count('fee_structures');
await structureForm.getByLabel(book.structures_name, { exact: true }).fill('SMOKE-Lang-Structure');
await structureForm.getByLabel(book.structures_applies, { exact: true }).selectOption('class');
await structureForm.getByRole('button', { name: book.structures_create, exact: true }).click();
await office.waitForLoadState('networkidle');
const noClass = await said(structureForm.locator('ul.text-red-600'));
check('a structure for selected classes with none ticked is refused under the form, in Dhivehi; none is made', noClass === book.error_select_class && inDhivehi(noClass) && count('fee_structures') === structuresBefore, `said: ${noClass ?? 'nothing'}`);

// ------------------------------------- last year copied where there is none

const yearId = Number(tinker("echo (int) DB::table('academic_years')->where('is_current', 1)->value('id');"));
const previous = tinker(`echo json_encode(app(App\\Domains\\Academics\\Actions\\ResolvePreviousAcademicYearAction::class)->execute(${yearId}));`);
if (previous === 'null') {
    await office.goto(`${BASE}/dv/finance/fee-structures`, { waitUntil: 'networkidle' });
    const copy = office.getByRole('button', { name: book.structures_copy, exact: true });
    await copy.click();
    await office.waitForLoadState('networkidle');
    const noLastYear = await said(copy.locator('xpath=..').locator('ul.text-red-600'));
    check('copying last year where there is none is refused beside the button, in Dhivehi; nothing is made', noLastYear === book.error_no_previous_year && inDhivehi(noLastYear) && count('fee_structures') === structuresBefore, `said: ${noLastYear ?? 'nothing'}`);
} else {
    // Copying would make drafts of last year's structures: not a walk's to do.
    check('copying last year where there is none is refused beside the button (skipped: this host has a last year)', true, previous.slice(0, 80));
}

// ------------------------------------- a period that ends before it starts

await office.goto(`${BASE}/dv/finance/invoices`, { waitUntil: 'networkidle' });
const runForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.invoices_generate, exact: true }) }).first();
const invoicesBefore = count('invoices');
await runForm.getByLabel(book.invoices_period_start, { exact: true }).fill(today());
await runForm.getByLabel(book.invoices_period_end, { exact: true }).fill(daysFromToday(-10));
await runForm.getByRole('button', { name: book.invoices_generate, exact: true }).click();
await office.waitForLoadState('networkidle');
const backwards = await said(runForm.locator('ul.text-red-600'));
check('a period that ends before it starts is refused under the form, in Dhivehi; no invoice is made', backwards === book.error_period_order && inDhivehi(backwards) && count('invoices') === invoicesBefore, `said: ${backwards ?? 'nothing'}`);

// ------------------------------------- an adjustment for some kinds of fee, none ticked

const studentId = Number(tinker("echo (int) DB::table('fee_adjustments')->where('value', 7.77)->value('student_id');"));
await office.goto(`${BASE}/dv/finance/adjustments`, { waitUntil: 'networkidle' });
const adjustForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.adjustments_save, exact: true }) }).first();
const adjustmentsBefore = count('fee_adjustments');
await adjustForm.getByLabel(book.adjustments_student_id, { exact: true }).fill(String(studentId));
await adjustForm.getByLabel(book.adjustments_applies, { exact: true }).selectOption('item_types');
const offered = await adjustForm.locator('fieldset input[type=checkbox]').count();
await adjustForm.getByRole('button', { name: book.adjustments_save, exact: true }).click();
await office.waitForLoadState('networkidle');
const noKinds = await said(adjustForm.locator('ul.text-red-600'));
check('an adjustment for some kinds of fee offers the kinds, and with none ticked is refused under the form, in Dhivehi; none is saved', studentId > 0 && offered > 0 && noKinds === book.error_adjustment_item_types && inDhivehi(noKinds) && count('fee_adjustments') === adjustmentsBefore,
    `kinds offered: ${offered}; said: ${noKinds ?? 'nothing'}`);

// ------------------------------------- a plan that does not add up

await office.goto(`${BASE}/dv/finance/payment-plans`, { waitUntil: 'networkidle' });
const planForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.plans_create, exact: true }) }).first();
const openOption = planForm.locator('option', { hasText: 'SMOKE-INV-OPEN' }).first();
if (await openOption.count()) {
    const optionText = (await openOption.textContent()) ?? '';
    await planForm.getByLabel(book.plans_invoice, { exact: true }).selectOption(await openOption.getAttribute('value'));
    const first = (book.plans_installment_amount || '').replace(':n', '1');
    const firstDue = (book.plans_installment_due || '').replace(':n', '1');
    const secondDue = (book.plans_installment_due || '').replace(':n', '2');
    const proposed = [
        await planForm.getByLabel(first, { exact: true }).inputValue(),
        await planForm.getByLabel((book.plans_installment_amount || '').replace(':n', '2'), { exact: true }).inputValue(),
        await planForm.getByLabel(firstDue, { exact: true }).inputValue(),
        await planForm.getByLabel(secondDue, { exact: true }).inputValue(),
    ];
    const [y, m] = today().split('-').map(Number);
    const firstOf = (offset) => { const d = new Date(Date.UTC(y, m - 1 + offset, 1)); return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-01`; };
    check('the open invoice says whose it is, and its balance is split across the installments, due on the first of the next two months', optionText.split('—').length === 3 && proposed[0] === '75.00' && proposed[1] === '75.00' && proposed[2] === firstOf(1) && proposed[3] === firstOf(2),
        `option: ${optionText}; proposed: ${proposed.join(' / ')}`);
    const plansBefore = count('payment_plans');
    await planForm.getByLabel(first, { exact: true }).fill('1');
    await planForm.getByRole('button', { name: book.plans_create, exact: true }).click();
    await office.waitForLoadState('networkidle');
    const notTheBalance = await said(planForm.locator('ul.text-red-600'));
    check('a plan that does not add up to the balance is refused under the form, in Dhivehi, the balance in the sentence; no plan is made', notTheBalance === (book.error_installments_sum || '').replace(':balance', '150.00') && inDhivehi(notTheBalance) && count('payment_plans') === plansBefore, `said: ${notTheBalance ?? 'nothing'}`);
} else {
    check('the open invoice says whose it is, and its balance is split across the installments, due on the first of the next two months', false, 'no SMOKE-INV-OPEN — re-seed first');
    check('a plan that does not add up to the balance is refused under the form, in Dhivehi, the balance in the sentence; no plan is made', false, 'no SMOKE-INV-OPEN — re-seed first');
}

// ------------------------------------- a reminder out of bounds, then the settings as they were

const reminder = () => tinker("echo json_encode(app(App\\Domains\\Finance\\Actions\\ResolveFinanceSettingsAction::class)->execute()['reminder_days']);");
await office.goto(`${BASE}/dv/finance/settings`, { waitUntil: 'networkidle' });
const settingsForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.settings_save, exact: true }) }).first();
const reminderBox = settingsForm.locator('input[type=number]').first();
const reminderBefore = reminder();
const typed = await reminderBox.inputValue();
// The box allows 0 to 90 days, so the browser holds 200 itself and nothing
// is sent; the server's refusal behind it is the test's to hold
// (FinanceSpeaksThreeLanguagesTest).
await reminderBox.fill('200');
await settingsForm.getByRole('button', { name: book.settings_save, exact: true }).click();
await office.waitForLoadState('networkidle');
const held = await reminderBox.evaluate((el) => el.validity.rangeOverflow);
check('a reminder 200 days after the due date is held by its box, which allows 0 to 90; the setting stays', held && reminder() === reminderBefore, `held: ${held}; reminder ${reminderBefore} → ${reminder()}`);
await reminderBox.fill(typed);
await settingsForm.getByRole('button', { name: book.settings_save, exact: true }).click();
await office.waitForLoadState('networkidle');
const saved = await said(office.getByTestId('flash-success'));
check('the settings saved as they were are said in Dhivehi, and nothing moves', saved === book.flash_settings_saved && inDhivehi(saved) && reminder() === reminderBefore, `said: ${saved ?? 'nothing'}`);

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
