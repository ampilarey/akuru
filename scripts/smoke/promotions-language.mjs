/**
 * Do the Library office's promotion campaigns read in Dhivehi and Arabic?
 * (BACKLOG C21, slice PC1, STATUS §5qy.)
 *
 * The system admin opens the promotions page under /dv and /ar. The walk
 * lists what is still in Latin letters in the page's main: every text node,
 * placeholder, aria-label, title and phone caption (`data-label`). What the
 * page shows of its data (a campaign's name and description, a category's,
 * a writer's or an item's name) is the author's, so a string found among
 * the page's props passes; the phrase book the page is sent, and the codes
 * it must name, do not. The page must be right to left, every field in it
 * named, and it must open where it was asked for. A campaign planted for the
 * walk has a cap and covers an item no longer listed, so the list has an
 * amount, a cap and an unlisted target to name.
 *
 * Then, in Dhivehi, the system admin:
 *   - starts a campaign that ends before it starts, one that takes more
 *     than a hundred per cent off, and one with no name — each refused
 *     under the form in Dhivehi, the last by Laravel with the field named;
 *   - starts one, told in Dhivehi, and reads its row: its state, discount,
 *     who funds it and what it covers named;
 *   - ends it, told in Dhivehi, the row saying so; and presses End again on
 *     a page opened before, which the server refuses — said under the
 *     campaign's row, in Dhivehi, where it was said nowhere.
 *
 * Every campaign the walk starts or plants begins a year from now, so none
 * reaches a price, and all are removed first and last.
 *
 *   node scripts/smoke/promotions-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_SUPER_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const NAME = 'SMOKE-PC1';
const PLANTED = 'SMOKE-PC1-Gone';
// An item id nothing has: the planted campaign covers an item no longer listed.
const GONE_ITEM = 999999999;
const PATH = '/admin/library/promotions';

// Props that carry the phrase books and codes the page must name: their
// values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'href',
    'status', 'state', 'discount_type', 'funding_source', 'funding_sources', 'type',
]);
// A format, an amount's currency and an address, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bJPEG\b/g, /\bPNG\b/g, /\bWebP\b/g, /\bMVR\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

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

async function readScreen(page, locale) {
    const label = `${locale}${PATH}`;
    const response = await page.goto(`${BASE}/${locale}${PATH}`, { waitUntil: 'networkidle' });
    const where = new URL(page.url());
    if (!response || response.status() >= 400 || where.pathname !== `/${locale}${PATH}`) {
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
// A campaign's redemptions point at it; one a reader used is never removed.
const cleanUp = () => tinker(`$ids = DB::table('promotion_campaigns')->where('name', 'like', '${NAME}%')->pluck('id'); $used = DB::table('discount_redemptions')->whereIn('promotion_campaign_id', $ids)->pluck('promotion_campaign_id'); DB::table('promotion_campaigns')->whereIn('id', $ids->diff($used))->delete(); echo 'ok';`);
// A year from now, as the form's date and time boxes take it.
const inAYear = (days = 0) => new Date(Date.now() + (365 + days) * 86400000).toISOString().slice(0, 16);

cleanUp();
// Twenty per cent off, at most 30, on an item no longer listed: the list names the cap and the item's kind.
tinker(`app(\\App\\Domains\\Commerce\\Actions\\SavePromotionCampaignAction::class)->execute(['name' => '${PLANTED}', 'discount_type' => 'percentage', 'discount_value' => 20, 'max_discount_amount' => 30, 'funding_source' => 'akuru', 'starts_at' => now()->addYear()->toDateTimeString(), 'ends_at' => now()->addYear()->addDay()->toDateTimeString(), 'targets' => [['type' => 'library_item', 'id' => ${GONE_ITEM}]]]); echo 'ok';`);
const admin = await signIn(SUPER);

for (const locale of ['dv', 'ar']) {
    await readScreen(admin, locale);
}

// ------------------------------------- the planted campaign, read in Dhivehi
await admin.goto(`${BASE}/dv${PATH}`, { waitUntil: 'networkidle' });
const book = (await props(admin)).t ?? {};
const money = (amount) => book.library_promotions_money.replace(':amount', amount);
const rowOf = (page, slug) => page.locator(`main [data-testid="campaigns"] tr[data-campaign="${slug}"]`).first();
const planted = flat(await rowOf(admin, 'smoke-pc1-gone').textContent().catch(() => ''));
const unlisted = book.library_promotions_unlisted.replace(':kind', book.library_promotions_kind_library_item).replace(':id', GONE_ITEM);
check('a campaign’s cap and an item no longer listed are named in Dhivehi',
    planted.includes(`20% ${book.library_promotions_at_most.replace(':amount', money(30))}`) && planted.includes(unlisted) && planted.includes(book.library_promotions_state_scheduled),
    planted.slice(0, 200));

// ------------------------------------- three campaigns refused, under the form, in Dhivehi
const campaignForm = (page) => page.locator('main [data-testid="campaign-form"]');
async function start({ name = NAME, value = '10', starts = inAYear(), ends = inAYear(1) } = {}) {
    await admin.goto(`${BASE}/dv${PATH}`, { waitUntil: 'networkidle' });
    const form = campaignForm(admin);
    await form.locator('input[name="name"]').fill(name);
    const [startsBox, endsBox] = [form.locator('input[type="datetime-local"]').nth(0), form.locator('input[type="datetime-local"]').nth(1)];
    await startsBox.fill(starts);
    await endsBox.fill(ends);
    await form.locator(`select[aria-label="${book.library_promotions_type}"]`).selectOption('percentage');
    await form.locator('input[name="discount_value"]').fill(value);
    await form.getByRole('button', { name: book.library_promotions_start, exact: true }).click();
    await admin.waitForLoadState('networkidle');
    return form;
}
async function refusedWith(attempt, says, step) {
    const form = await start(attempt);
    await form.getByText(says, { exact: true }).waitFor({ timeout: 10000 }).catch(() => {});
    check(step, flat(await form.textContent()).includes(says), flat(await form.textContent()).slice(-160));
}

await refusedWith({ starts: inAYear(1), ends: inAYear() }, dvSays('common.campaign_error_ends'), 'a campaign that ends before it starts is refused under the form, in Dhivehi');
await refusedWith({ value: '150' }, dvSays('common.error_discount_value'), 'one that takes more than a hundred per cent off, in Dhivehi');
const noName = tinker(`echo __('validation.required', ['attribute' => __('admin.library_promotions_attr_name', [], 'dv')], 'dv');`);
await refusedWith({ name: '' }, noName, 'and one with no name, by Laravel, the field named in Dhivehi');
check('none of them was started', tinker(`echo DB::table('promotion_campaigns')->where('name', '${NAME}')->count();`) === '0');

// ------------------------------------- started, read, ended, and refused on a page opened before
await start();
const saved = dvSays('admin.library_promotions_saved');
await admin.getByText(saved, { exact: true }).first().waitFor({ timeout: 10000 }).catch(() => {});
const started = flat(await rowOf(admin, 'smoke-pc1').textContent().catch(() => ''));
check('a campaign is started, told in Dhivehi, its row naming its state, discount, who funds it and what it covers',
    flat(await admin.locator('body').textContent()).includes(saved) && started.includes(book.library_promotions_state_scheduled)
        && started.includes('10%') && started.includes(book.library_promotions_funding_akuru) && started.includes(book.library_promotions_covers_all),
    started.slice(0, 200));

const stale = await admin.context().newPage();
watch(stale);
await stale.goto(`${BASE}/dv${PATH}`, { waitUntil: 'networkidle' });

await rowOf(admin, 'smoke-pc1').locator('[data-testid="end-smoke-pc1"]').click();
const ended = dvSays('admin.library_promotions_ended');
await admin.getByText(ended, { exact: true }).first().waitFor({ timeout: 10000 }).catch(() => {});
const endedRow = flat(await rowOf(admin, 'smoke-pc1').textContent().catch(() => ''));
check('it is ended, told in Dhivehi, the row saying so',
    flat(await admin.locator('body').textContent()).includes(ended) && endedRow.includes(book.library_promotions_state_ended)
        && (await rowOf(admin, 'smoke-pc1').locator('[data-testid="end-smoke-pc1"]').count()) === 0,
    endedRow.slice(0, 160));

await rowOf(stale, 'smoke-pc1').locator('[data-testid="end-smoke-pc1"]').click();
const already = dvSays('common.campaign_error_already_ended');
await rowOf(stale, 'smoke-pc1').getByText(already, { exact: true }).waitFor({ timeout: 10000 }).catch(() => {});
const refusedRow = flat(await rowOf(stale, 'smoke-pc1').textContent().catch(() => ''));
check('End pressed again on a page opened before is refused under the campaign’s row, in Dhivehi', refusedRow.includes(already), refusedRow.slice(0, 160));
await stale.close();

cleanUp();
check('the walk’s campaigns are gone', tinker(`echo DB::table('promotion_campaigns')->where('name', 'like', '${NAME}%')->count();`) === '0');
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
