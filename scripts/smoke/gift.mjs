/**
 * Can a signed-in reader find the Library, and buy someone a gift card?
 *
 * Two gaps the Library completion audit found (STATUS §5gn): nothing a
 * signed-in person could see linked to `/library`, `/my-library` or
 * `/my-wallet` — they were addresses to type — and LIBRARY_PLAN §15.3's
 * purchase flow did not exist: every gift card was issued by the office.
 *
 *   1. the reader signs in; the public site's account menu offers My
 *      Library and My Wallet, and the app shell's More menu offers Library,
 *      My library and My wallet — each one opens;
 *   2. the wallet offers to buy a gift card; the page shows the presets;
 *   3. the reader fills the form and pays by card: the browser is sent to
 *      the bank (this walk is hermetic, so the bank's page is not loaded —
 *      leaving the site for it is the assertion), or, on a host with no
 *      gateway keys, is told plainly that payment could not be started;
 *   4. either way a discount box is nowhere on the page (§15.4), and the
 *      wallet lists the order by recipient with its status and no code.
 *
 * Nothing to seed: the order the walk makes is money and stays (rule 12).
 *
 *   node scripts/smoke/gift.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const RECIPIENT = `SMOKE-Gift-${Date.now().toString(36).slice(-5)}`;

const HERMETIC_ARGS = [
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter,InterestFeedContentSuggestions',
    '--no-first-run',
    '--no-default-browser-check',
];

const browser = await chromium.launch({
    args: HERMETIC_ARGS,
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const offSite = [];

for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => {
        check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200));
        await finish();
    });
}

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

async function newPage(label) {
    const context = await browser.newContext();
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => {
        const url = route.request().url();
        if (url.startsWith(BASE)) {
            return route.continue();
        }
        // Leaving the site is recorded, not followed: the bank is not ours.
        if (route.request().isNavigationRequest()) {
            offSite.push(url);
        }

        return route.abort();
    });
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${label}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${label}: HTTP ${response.status()} ${response.url()}`);
        }
    });

    return page;
}

async function signIn(email) {
    const page = await newPage(email);
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');

    return page;
}

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');

// --------------------------------------------------------- 1. finding it

const reader = await signIn(STUDENT);
check('the reader signs in', !reader.url().includes('/login'), reader.url());

await reader.goto(`${BASE}/en/library`, { waitUntil: 'networkidle' });
// B0: the library's name. The full name heads its own page; the site's
// menu and footer carry the short one; Dhivehi and Arabic have their own.
const shelfHeading = (await reader.locator('h1').first().innerText()).trim();
const siteLinks = await reader.locator('header a, nav a, footer a').evaluateAll((els) => els.map((el) => [el.innerText.trim(), el.getAttribute('href') ?? '']));
check('the shelf is called the Akuru Digital Library', shelfHeading === 'Akuru Digital Library' && !(await text(reader)).includes('Knowledge Library'), shelfHeading);
check('the site menu and footer link to it as Digital Library', siteLinks.filter(([t, h]) => t === 'Digital Library' && /\/library$/.test(h)).length >= 2, siteLinks.filter(([, h]) => /\/library$/.test(h)).map(([t]) => t).join(' · ') || 'no library links');
for (const [locale, name] of [['dv', 'އަކުރު ޑިޖިޓަލް ލައިބްރަރީ'], ['ar', 'مكتبة أكورو الرقمية']]) {
    await reader.goto(`${BASE}/${locale}/library`, { waitUntil: 'networkidle' });
    const heading = (await reader.locator('h1').first().innerText()).trim();
    check(`in ${locale === 'dv' ? 'Dhivehi' : 'Arabic'} it has its own name`, heading === name, heading);
}
await reader.goto(`${BASE}/en/library`, { waitUntil: 'networkidle' });
await reader.click('#user-menu-wrapper button');
const menuLibrary = reader.locator('[data-testid="nav-my-library"]');
const menuWallet = reader.locator('[data-testid="nav-my-wallet"]');
check('the public site account menu offers My Library and My Wallet', (await menuLibrary.count()) === 1 && (await menuWallet.count()) === 1);
await menuLibrary.click();
await reader.waitForLoadState('networkidle');
check('My Library opens from it', /\/my-library$/.test(reader.url()) && (await text(reader)).includes('My Library'), reader.url().replace(BASE, ''));

// The app shell: More → the "Mine" group.
await reader.goto(`${BASE}/en/portal/home`, { waitUntil: 'networkidle' });
const more = reader.locator('button', { hasText: /^More/ }).first();
if (await more.count()) {
    await more.click();
    await reader.waitForTimeout(300);
}
const shellLinks = await reader.locator('#app-shell-more a, nav a').evaluateAll((els) => els.map((el) => [el.innerText.trim(), el.getAttribute('href') ?? '']));
const has = (label, path) => shellLinks.some(([t, h]) => t === label && h.endsWith(path));
check('the app shell offers Digital Library, My library and My wallet', has('Digital Library', '/library') && has('My library', '/my-library') && has('My wallet', '/my-wallet'), shellLinks.filter(([, h]) => /library|wallet/.test(h)).map(([t, h]) => `${t}→${h}`).join(' · ') || 'no library or wallet links in the shell');

// -------------------------------------------------------- 2. the offer

await reader.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
const buy = reader.locator('[data-testid="buy-gift-card"]');
check('the wallet offers to buy a gift card', (await buy.count()) === 1);
await buy.click();
await reader.waitForLoadState('networkidle');
const shop = await text(reader);
check('the gift card page shows the presets and the rules', /\/gift-cards$/.test(reader.url()) && shop.includes('MVR 100') && shop.includes('MVR 1,000') && shop.includes('Discount codes and wallet money cannot buy a gift card'), shop.slice(0, 160));
check('and no discount box (§15.4)', (await reader.locator('input[name="discount_code"], input[name="pay_with_wallet"]').count()) === 0);

// -------------------------------------------------------- 3. paying

await reader.locator('.gift-preset', { hasText: 'MVR 250' }).click();
check('a preset fills the amount', (await reader.inputValue('#gift-amount')) === '250', await reader.inputValue('#gift-amount'));
await reader.fill('input[name="recipient_name"]', RECIPIENT);
await reader.fill('input[name="recipient_email"]', 'gift@example.test');
await reader.fill('textarea[name="message"]', 'Happy reading.');
await reader.locator('[data-testid="gift-card-form"] button[type=submit]').click();
await reader.waitForLoadState('networkidle').catch(() => {});
await reader.waitForTimeout(800);

const wentToBank = offSite.some((url) => /bml|bankofmaldives|connect/i.test(url));
// The red box carries the gateway's own reason on a host without keys
// ("Payment gateway not configured") and the generic line otherwise.
const toldPlainly = /could not be started|not configured|Payment .*failed/i.test(await text(reader).catch(() => ''));
check('paying sends the browser to the bank, or says plainly that it could not start', wentToBank || toldPlainly, wentToBank ? offSite.find((url) => /bml|bankofmaldives|connect/i.test(url)).slice(0, 80) : toldPlainly ? 'no gateway keys on this host: told plainly' : (await text(reader).catch(() => '')).slice(0, 160));

// -------------------------------------------------------- 4. the record

await reader.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
const wallet = await text(reader);
const row = reader.locator('[data-testid="gift-card-orders"] > div', { hasText: RECIPIENT });
check('the wallet lists the order by recipient with its status', (await row.count()) === 1 && /pending|failed/.test(await row.innerText()), (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : wallet.slice(0, 160));
check('and shows no gift card code', !/AKG-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/.test(wallet));

// -------------------------------------------------------- 5. the office's view

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/commerce`, { waitUntil: 'networkidle' });
const orders = office.locator('[data-testid="gift-card-orders"] tr', { hasText: RECIPIENT });
check('the office sees the purchase, its buyer and its status', (await orders.count()) === 1 && /pending|failed/.test(await orders.innerText()), (await orders.count()) ? (await orders.innerText()).replace(/\s+/g, ' ') : 'no row for the order');
const csv = await office.request.get(`${BASE}/en/admin/commerce/gift-card-orders/export`);
check('and can export the purchases', csv.status() === 200 && (await csv.text()).includes(RECIPIENT), `HTTP ${csv.status()}`);

await finish();
