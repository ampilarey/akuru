/**
 * The Bookstore's last English words (BACKLOG C21, slice SH1, STATUS §5qw).
 *
 * In Dhivehi (and the gate in Arabic too):
 *   - a seller whose Vendor Agreement is not yet accepted opens their orders,
 *     and is refused in the page's language — it was English;
 *   - somebody applies to open a shop, with their ID card, on a form that
 *     reads right to left with nothing left in English and every field named
 *     by its label (the shop's address was named by an example, *https://*);
 *   - the office reads the application: its ID card links in Dhivehi, where
 *     they read *ID front* and *ID back*, and the chip to the identity cards
 *     by their heading, where it read *ID*; the page has nothing left in
 *     English;
 *   - the office types a customer's tags with the Dhivehi keyboard's comma,
 *     and two tags are saved — the box split on the Latin comma alone, so
 *     they were saved as one; the orders' phone caption reads in Dhivehi.
 *
 * What a page shows of its data (a shop's, a person's or a product's name)
 * is the author's, so a string found among the page's props passes; the
 * phrase books the page is sent do not. Codes the same in every language
 * pass too.
 *
 * The walk's application is removed and the customer's tags put back, last.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/bookstore-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_APPLICANT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const APPLICANT = process.env.SMOKE_APPLICANT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOP = 'SMOKE-Walk SH1 Shop';

// Props that carry the phrase books and codes the page must name.
const CODE_KEYS = new Set(['i18n', 't', 'id_l', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'href', 'status', 'state', 'kind']);
// Codes the same in every language: file formats and sizes, a currency, a product code, a setting's name.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bwww\.\S+/g, /[\w.+*-]+@[\w-]+(\.[\w-]+)+/g, /\bCSV\b/g, /\bCSS\b/g, /\bJPEG\b/g, /\bPNG\b/g, /\bWebP\b/g, /\bPDF\b/g, /\b[KM]B\b/g, /\bMVR\b/g, /\bSKU\b/g, /\bSMS\b/g, /\bTIN\b/g, /\bBOOKSHOP_[A-Z_]+\b/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const says = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

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
        if (walker.currentNode.parentElement?.closest('style, script, textarea, svg, iframe')) continue;
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
    // A field named by an example of what to type in it.
    const byExample = [...main.querySelectorAll('[aria-label]')].map((el) => el.getAttribute('aria-label')).filter((v) => /^(https?:\/\/|www\.)/.test(v));
    return { texts, unnamed, byExample, dir: document.documentElement.getAttribute('dir') };
});

async function readScreen(page, path) {
    const response = await page.goto(`${BASE}/dv${path}`, { waitUntil: 'networkidle' });
    const where = new URL(page.url());
    if (!response || response.status() >= 400 || where.pathname !== `/dv${path}`) {
        check(`dv${path}: the page opens`, false, `HTTP ${response?.status()} ${page.url().replace(BASE, '')}`);
        return;
    }
    const found = await readMain(page);
    const left = english(found.texts, authorsWords(await props(page)));
    check(`dv${path}: right to left, nothing left in English`, found.dir === 'rtl' && left.length === 0, `dir=${found.dir}; ${left.slice(0, 6).join(' | ')}`);
    check(`dv${path}: every field is named, none by an example`, found.unnamed.length === 0 && found.byExample.length === 0, [...found.unnamed, ...found.byExample].slice(0, 3).join(' | '));
}

const removeApplications = () => tinker(`$id = DB::table('users')->where('email', '${APPLICANT}')->value('id'); echo DB::table('vendor_applications')->where('user_id', $id)->whereNull('vendor_id')->delete();`);

// ------------------------------------- the seller's gate, refused in the page's language
const seller = await signIn(VENDOR);
for (const locale of ['dv', 'ar']) {
    const response = await seller.goto(`${BASE}/${locale}/vendor/orders`, { waitUntil: 'networkidle' });
    const body = flat(await seller.locator('body').textContent());
    const refusal = says('shop.error_agreement_first', locale);
    check(`${locale}: a seller who has not accepted the agreement is refused in the page's language`, response?.status() === 403 && body.includes(refusal), `HTTP ${response?.status()}; ${body.slice(0, 120)}`);
}

// ------------------------------------- an application with an ID card, in Dhivehi
const applicant = await signIn(APPLICANT);
await readScreen(applicant, '/vendor/apply');
await applicant.fill('[data-testid="apply-shop-name"]', SHOP);
await applicant.fill('[data-testid="apply-island"]', 'Hulhumalé');
await applicant.fill('[data-testid="apply-email"]', 'smoke-sh1@example.test');
await applicant.fill('[data-testid="apply-phone"]', '7770001');
await applicant.fill('[data-testid="apply-what"]', 'SMOKE-SH1: exercise books and alphabet charts.');
await applicant.check('[data-testid="apply-agreement"]');
const idImage = { name: 'smoke-id.png', mimeType: 'image/png', buffer: await applicant.screenshot({ clip: { x: 0, y: 0, width: 80, height: 80 } }) };
await applicant.setInputFiles('[data-testid="id-front"]', idImage);
await applicant.setInputFiles('[data-testid="id-back"]', idImage);
await applicant.click('[data-testid="apply-submit"]');
await applicant.locator('[data-testid="application-status"]').waitFor({ timeout: 15000 }).catch(() => {});
check('the application is sent, and waits for the office', (await applicant.locator('[data-testid="application-status"]').getAttribute('data-status').catch(() => '')) === 'pending');

// ------------------------------------- the office reads it in Dhivehi
const office = await signIn(ADMIN);
await readScreen(office, '/admin/bookshop');
const row = office.locator('[data-testid^="application-"][data-status="pending"]').filter({ hasText: SHOP }).first();
const appId = ((await row.getAttribute('data-testid').catch(() => '')) || '').replace('application-', '');
const front = flat(await office.locator(`[data-testid="application-id-front-${appId}"]`).textContent().catch(() => ''));
const back = flat(await office.locator(`[data-testid="application-id-back-${appId}"]`).textContent().catch(() => ''));
check('the application’s ID card links read in Dhivehi', appId !== '' && front === says('shop.application_id_front') && back === says('shop.application_id_back'), `#${appId}: ${front} · ${back}`);
const chip = flat(await office.locator('[data-testid="jump-identity"]').first().textContent().catch(() => ''));
check('the chip to the identity cards reads their heading', chip === says('account.id_checks_title'), chip || 'no chip');

// ------------------------------------- a customer's tags, typed with the Dhivehi comma
await office.goto(`${BASE}/dv/admin/bookshop/customers`, { waitUntil: 'networkidle' });
const hrefs = await office.locator('main a[href*="/admin/bookshop/customers/"]').evaluateAll((els) => els.map((e) => e.getAttribute('href')));
const customerPath = new URL(hrefs.find((h) => h && !h.includes('export')) ?? '/', BASE).pathname.replace(/^\/(dv|en|ar)/, '');
const customerId = Number(customerPath.match(/customers\/(\d+)/)?.[1] ?? 0);
// What the customer's profile held, to put back: its tags, or no profile at all.
const before = JSON.parse(tinker(`$row = DB::table('shop_customer_profiles')->where('user_id', ${customerId})->first(); echo json_encode(['exists' => $row !== null, 'tags' => $row?->tags]);`) || '{}');
await readScreen(office, customerPath);
const caption = await office.locator('main [data-testid="customer-orders"] td[data-label]').nth(2).getAttribute('data-label').catch(() => null);
check('a customer’s orders read their phone captions in Dhivehi', caption === null || caption === says('shop.status'), caption ?? 'no orders');
await office.locator('[data-testid="customer-tags"]').fill('ސްކޫލް، ހޯލްސޭލް');
await office.click('[data-testid="customer-save-tags"]');
await office.waitForLoadState('networkidle');
await office.waitForTimeout(500);
const saved = JSON.parse(tinker(`echo json_encode(json_decode((string) DB::table('shop_customer_profiles')->where('user_id', ${customerId})->value('tags'), true));`) || 'null');
check('tags typed with the Dhivehi keyboard’s comma are saved as two', Array.isArray(saved) && saved.length === 2 && saved.includes('ސްކޫލް') && saved.includes('ހޯލްސޭލް'), JSON.stringify(saved));

// ------------------------------------- put back what the walk changed
tinker(before.exists
    ? `DB::table('shop_customer_profiles')->where('user_id', ${customerId})->update(['tags' => base64_decode('${Buffer.from(before.tags ?? '').toString('base64')}') ?: null]); echo 'ok';`
    : `DB::table('shop_customer_profiles')->where('user_id', ${customerId})->delete(); echo 'ok';`);
removeApplications();
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
