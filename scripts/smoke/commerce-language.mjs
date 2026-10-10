/**
 * Does the Commerce office read in Dhivehi and Arabic? (BACKLOG C21, slice
 * CO1, STATUS §5qv.)
 *
 * The system admin opens the Commerce office under /dv and /ar. The walk
 * lists what is still in Latin letters in the page's main: every text node,
 * placeholder, aria-label, title and phone caption (`data-label`). What the
 * page shows of its data (a recipient's or a buyer's name, a reason the
 * office typed, a discount code) is the author's, so a string found among
 * the page's props passes; the phrase book the page is sent, and the codes
 * it must name, do not. The page must be right to left, every field in it
 * named, and it must open where it was asked for.
 *
 * Then, in Dhivehi, the system admin:
 *   - issues a gift card, its code shown once with the note in Dhivehi, and
 *     reads its row: its state, where it came from and its amount named;
 *   - deactivates it, told in Dhivehi, the row saying so with the reason;
 *     and presses Deactivate again on a page opened before, which the
 *     server refuses — said under the card's row, in Dhivehi, where it was
 *     said nowhere;
 *   - saves a discount code, told in Dhivehi, its type and state named; is
 *     refused a percentage over a hundred and the same code again, under the
 *     form, in Dhivehi;
 *   - credits an account nobody has, and is refused by Laravel, the field
 *     named in Dhivehi — no money moves;
 *   - reads a gift card purchase planted for the walk, its state and how its
 *     code went out named.
 *
 * The walk's discount code and purchase are removed first and last. The
 * card it issues is money and stays, with its ledger row (rule 12), as the
 * cards `reader.mjs` issues do.
 *
 *   node scripts/smoke/commerce-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_SUPER_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const CARD = 'SMOKE-CO1-Card';
const ORDER = 'SMOKE-CO1-Order';
const CODE = 'SMOKE-CO1';

// Props that carry the phrase books and codes the page must name: their
// values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set(['i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'href', 'status', 'source', 'discount_type', 'delivered_via', 'currency']);
// A format, a gift card's code and an address, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bAKG-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}\b/g, /[\w.+*-]+@[\w-]+(\.[\w-]+)+/g];

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
    watch(page);
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

function watch(page) {
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
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

const dvSays = (key, args = '[]') => tinker(`echo __('${key}', ${args}, 'dv');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();
const cleanUp = () => tinker(`$ids = DB::table('discount_codes')->where('code', '${CODE}')->pluck('id'); DB::table('discount_redemptions')->whereIn('discount_code_id', $ids)->delete(); DB::table('discount_codes')->whereIn('id', $ids)->delete(); DB::table('gift_card_orders')->where('recipient_name', '${ORDER}')->delete(); echo 'ok';`);

cleanUp();
// One purchase, so the purchases list has a row to name: paid, its code sent by email and SMS.
tinker(`DB::table('gift_card_orders')->insert(['user_id' => (int) DB::table('users')->where('email', '${STUDENT}')->value('id'), 'amount' => 50, 'currency' => 'MVR', 'recipient_name' => '${ORDER}', 'status' => 'paid', 'delivered_via' => 'email+sms', 'delivered_to' => 'h***@example.test, 960***0000', 'delivered_at' => now(), 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]); echo 'ok';`);
const admin = await signIn(SUPER);

for (const locale of ['dv', 'ar']) {
    await readScreen(admin, locale, '/admin/commerce');
}

// ------------------------------------- a gift card, issued and read, in Dhivehi
await admin.goto(`${BASE}/dv/admin/commerce`, { waitUntil: 'networkidle' });
const book = (await props(admin)).t ?? {};
const giftForm = admin.locator('main form', { has: admin.locator(`input[aria-label="${book.commerce_recipient_name}"]`) }).first();
await giftForm.locator(`input[aria-label="${book.commerce_amount_mvr}"]`).fill('1');
await giftForm.locator(`input[aria-label="${book.commerce_recipient_name}"]`).fill(CARD);
await giftForm.getByRole('button', { name: book.commerce_issue, exact: true }).click();
await admin.getByText(book.commerce_code_once, { exact: true }).waitFor({ timeout: 10000 }).catch(() => {});
const shown = flat(await admin.locator('main').textContent());
const code = shown.match(/AKG-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/)?.[0] ?? null;
check('a gift card is issued, its code shown once with the note in Dhivehi', Boolean(code) && shown.includes(book.commerce_code_once), code ?? shown.slice(0, 160));

const cardRow = (page) => page.locator('main [data-testid="gift-card-row"]', { hasText: CARD }).first();
const issued = flat(await cardRow(admin).textContent().catch(() => ''));
const oneRufiyaa = book.commerce_money.replace(':amount', '1.00');
check('its row names its state, where it came from and its amount, in Dhivehi',
    issued.includes(book.commerce_card_status_active) && issued.includes(book.commerce_source_office) && issued.includes(oneRufiyaa),
    issued.slice(0, 160));

// ------------------------------------- deactivated, then refused on a page opened before
const stale = await admin.context().newPage();
watch(stale);
await stale.goto(`${BASE}/dv/admin/commerce`, { waitUntil: 'networkidle' });

admin.once('dialog', (d) => d.accept('SMOKE-CO1 reason'));
await cardRow(admin).locator('[data-testid="deactivate-gift-card"]').click();
await admin.getByText(dvSays('admin.commerce_flash_deactivated'), { exact: true }).first().waitFor({ timeout: 10000 }).catch(() => {});
const told = flat(await admin.locator('body').textContent()).includes(dvSays('admin.commerce_flash_deactivated'));
const deactivated = flat(await cardRow(admin).textContent().catch(() => ''));
check('it is deactivated, told in Dhivehi, the row saying so with the reason',
    told && deactivated.includes(book.commerce_card_status_deactivated) && deactivated.includes('SMOKE-CO1 reason') && (await cardRow(admin).locator('[data-testid="deactivate-gift-card"]').count()) === 0,
    `told: ${told}; ${deactivated.slice(0, 140)}`);

stale.once('dialog', (d) => d.accept('SMOKE-CO1 again'));
await cardRow(stale).locator('[data-testid="deactivate-gift-card"]').click();
const nothingLeft = dvSays('common.gift_card_error_nothing_left');
await cardRow(stale).getByText(nothingLeft, { exact: true }).waitFor({ timeout: 10000 }).catch(() => {});
const refusedRow = flat(await cardRow(stale).textContent().catch(() => ''));
check('Deactivate pressed again on a page opened before is refused under the card’s row, in Dhivehi', refusedRow.includes(nothingLeft), refusedRow.slice(0, 160));
await stale.close();

// ------------------------------------- a discount code, saved and refused, in Dhivehi
const discountForm = (page) => page.locator('main form', { has: page.locator(`input[aria-label="${book.commerce_code}"]`) }).first();
async function saveCode(value) {
    await admin.goto(`${BASE}/dv/admin/commerce`, { waitUntil: 'networkidle' });
    const form = discountForm(admin);
    await form.locator(`input[aria-label="${book.commerce_code}"]`).fill(CODE);
    await form.locator(`select[aria-label="${book.commerce_discount_type}"]`).selectOption('percentage');
    await form.locator(`input[aria-label="${book.commerce_value}"]`).fill(value);
    await form.locator(`input[aria-label="${book.commerce_name}"]`).fill('SMOKE CO1 ten');
    await form.getByRole('button', { name: book.commerce_save, exact: true }).click();
    await admin.waitForLoadState('networkidle');
    return form;
}

await saveCode('10');
const saved = dvSays('admin.commerce_flash_discount_saved');
await admin.getByText(saved, { exact: true }).first().waitFor({ timeout: 10000 }).catch(() => {});
// The code's own cell: the purchase planted for the walk carries the same mark.
const codeRow = flat(await admin.locator('main tbody tr', { has: admin.locator('td.font-mono', { hasText: new RegExp(`^${CODE}$`) }) }).first().textContent().catch(() => ''));
check('a discount code is saved, told in Dhivehi, its type and state named',
    flat(await admin.locator('body').textContent()).includes(saved) && codeRow.includes(book.commerce_type_percentage) && codeRow.includes(book.commerce_discount_status_active),
    codeRow.slice(0, 160));

const overHundred = dvSays('common.error_discount_value');
let form = await saveCode('150');
await form.getByText(overHundred, { exact: true }).waitFor({ timeout: 10000 }).catch(() => {});
check('a percentage over a hundred is refused under the form, in Dhivehi', flat(await form.textContent()).includes(overHundred), flat(await form.textContent()).slice(-160));

const exists = dvSays('common.error_discount_code_exists');
form = await saveCode('10');
await form.getByText(exists, { exact: true }).waitFor({ timeout: 10000 }).catch(() => {});
check('and the same code again, in Dhivehi', flat(await form.textContent()).includes(exists), flat(await form.textContent()).slice(-160));

// ------------------------------------- a credit to an account nobody has
await admin.goto(`${BASE}/dv/admin/commerce`, { waitUntil: 'networkidle' });
const creditForm = admin.locator('main form', { has: admin.locator(`input[aria-label="${book.commerce_user_id}"]`) }).first();
await creditForm.locator(`input[aria-label="${book.commerce_user_id}"]`).fill('999999999');
await creditForm.locator(`input[aria-label="${book.commerce_amount_mvr}"]`).fill('5');
await creditForm.getByRole('button', { name: book.commerce_credit, exact: true }).click();
const noAccount = tinker(`echo __('validation.exists', ['attribute' => __('admin.commerce_attr_user_id', [], 'dv')], 'dv');`);
await creditForm.getByText(noAccount, { exact: true }).waitFor({ timeout: 10000 }).catch(() => {});
check('a credit to an account nobody has is refused, the field named in Dhivehi', flat(await creditForm.textContent()).includes(noAccount), flat(await creditForm.textContent()).slice(-160));

// ------------------------------------- the planted purchase, named in Dhivehi
const purchase = flat(await admin.locator('main [data-testid="gift-card-orders"] tr', { hasText: ORDER }).first().textContent().catch(() => ''));
check('a gift card purchase’s state and how its code went out are named in Dhivehi',
    purchase.includes(book.commerce_order_status_paid) && purchase.includes(`${book.commerce_via_email} + ${book.commerce_via_sms}`) && purchase.includes(book.commerce_money.replace(':amount', '50.00')),
    purchase.slice(0, 160));

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
