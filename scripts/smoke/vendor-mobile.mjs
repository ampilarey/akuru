/**
 * The vendor portal on a phone (STATUS §5mp).
 *
 * The owner: "enhance the mobile layout of the vendor settings page". The
 * portal is one long page — products, then returns and holidays, the shop's
 * domain, discount codes, notices, newsletter, delivery methods, people — and
 * on a 390px phone the settings began 2,300px down with nothing to jump by.
 * This walk signs in as Fitrah's owner on a 390 × 844 screen and asks what a
 * phone user asks: does the page fit, can I get to the settings, are the
 * boxes labelled, are the links big enough for a thumb, and does a save work?
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/vendor-mobile.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

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

for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => {
        check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200));
        await finish();
    });
}

async function finish() {
    const failed = results.filter(([, ok]) => !ok).length;
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(84)} ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true };
const context = await browser.newContext(phone);
context.setDefaultNavigationTimeout(60000);
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();
page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
page.on('response', (response) => {
    if (response.status() >= 500) {
        problems.push(`HTTP ${response.status()} ${response.url()}`);
    }
});

const settle = async (selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const count = (selector) => page.locator(selector).count();

// `Math.min(clientWidth, screen.width)`: under phone emulation a document
// wider than the phone widens the layout viewport with it (mobile.mjs).
const overflow = () => page.evaluate(() => {
    const root = document.documentElement;
    const width = Math.min(root.clientWidth, window.screen.width);
    const widest = Math.max(root.scrollWidth, document.body.scrollWidth);

    return { over: widest - width > 2, by: Math.round(widest - width) };
});
const smallTargets = (selector) => page.evaluate((sel) => [...document.querySelectorAll(sel)]
    .map((el) => ({ text: (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 30), r: el.getBoundingClientRect() }))
    .filter(({ r }) => r.width > 0 && (r.height < 32 || r.width < 32))
    .map(({ text, r }) => `${text} ${Math.round(r.width)}×${Math.round(r.height)}`), selector);

// ------------------------------------------------------------ 1. sign in, the page fits

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', VENDOR);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
await page.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if (await count('[data-testid="accept-agreement"]')) {
    await page.check('[data-testid="accept-agreement"]');
    await page.click('[data-testid="agreement-form"] button[type=submit]');
    await settle('[data-testid="shop-sections"]');
}
check('the shop owner opens the portal on a 390px phone', /\/vendor$/.test(page.url()) && (await count('[data-testid="vendor-name"]')) === 1, page.url().replace(BASE, ''));
const fit = await overflow();
check('the page fits the phone — nothing scrolls sideways', !fit.over, fit.over ? `wider by ${fit.by}px` : '390px');
check('the eight shop links sit in two even columns', await page.evaluate(() => {
    const links = [...document.querySelectorAll('[data-testid="shop-sections"] a')];
    const lefts = new Set(links.map((a) => Math.round(a.getBoundingClientRect().left)));
    return links.length === 8 && lefts.size === 2 && links.every((a) => a.getBoundingClientRect().height >= 32);
}));

// ------------------------------------------------------------ 2. getting to the settings

const chips = await count('[data-testid="section-nav"] a');
check('a row of section chips sits under the heading, each big enough for a thumb', chips >= 7 && (await smallTargets('[data-testid="section-nav"] a')).length === 0, `${chips} chips`);
const navBefore = await page.evaluate(() => document.querySelector('[data-testid="section-nav"]').getBoundingClientRect().top);
await page.click('[data-testid="jump-settings"]');
await page.waitForTimeout(500);
const settingsTop = await page.evaluate(() => document.querySelector('[data-testid="shop-settings"]').getBoundingClientRect().top);
const navAfter = await page.evaluate(() => document.querySelector('[data-testid="section-nav"]').getBoundingClientRect().top);
check('tapping "Returns and holidays" brings the settings onto the screen', settingsTop >= 0 && settingsTop < 300, `settings at ${Math.round(settingsTop)}px from the top`);
check('and the chips stay pinned to the top while the page scrolls', navAfter >= 0 && navAfter <= 1 && navBefore > 1, `chips at ${Math.round(navAfter)}px (were ${Math.round(navBefore)}px)`);
await page.click('[data-testid="jump-delivery"]');
await page.waitForTimeout(500);
const deliveryTop = await page.evaluate(() => document.querySelector('[data-testid="delivery-methods"]').getBoundingClientRect().top);
check('"Delivery methods" too', deliveryTop >= 0 && deliveryTop < 300, `${Math.round(deliveryTop)}px from the top`);

// ------------------------------------------------------------ 3. labelled boxes, thumb-sized links

await page.click('[data-testid="add-method"]');
await settle('[data-testid="delivery-row-0"]');
const labelled = await page.evaluate(() => {
    const row = document.querySelector('[data-testid="delivery-row-0"]');
    const boxes = row.querySelectorAll('input:not([type=checkbox]), select');
    const named = [...boxes].filter((b) => b.closest('label')?.querySelector('span')?.innerText.trim());
    return { boxes: boxes.length, named: named.length, cols: new Set([...boxes].map((b) => Math.round(b.getBoundingClientRect().left))).size };
});
check('a new delivery method is nine labelled boxes, two to a row', labelled.boxes === 9 && labelled.named === 9 && labelled.cols === 2, `${labelled.named}/${labelled.boxes} labelled, ${labelled.cols} columns`);
const noticeHeads = await page.evaluate(() => [...document.querySelectorAll('[data-testid="shop-notices"] th')].filter((th) => th.getBoundingClientRect().width > 0).length);
check('the notices table drops its always-ticked "in the app" column on a phone', noticeHeads === 3, `${noticeHeads} columns showing`);
const small = await smallTargets('[data-testid="product-list"] .table-actions a, [data-testid="product-list"] .table-actions button');
check('every Edit, Duplicate and View link on a product card is big enough for a thumb', small.length === 0, small.length ? small.slice(0, 4).join(' | ') : `${await count('[data-testid="product-list"] .table-actions a, [data-testid="product-list"] .table-actions button')} links, none under 32px`);

// ------------------------------------------------------------ 4. a save on the phone

// Saved means read back after a reload — a flash may already be on the page from the step before.
const saveSettings = async (value) => {
    await page.fill('[data-testid="return-window"]', value);
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/vendor/settings') && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}),
        page.click('[data-testid="save-settings"]'),
    ]);
    await settle();
    await page.reload({ waitUntil: 'networkidle' });
    return page.locator('[data-testid="return-window"]').inputValue();
};
const was = await page.locator('[data-testid="return-window"]').inputValue();
const saved = await saveSettings('10');
check('the returns window saves from the phone and reads back after a reload', saved === '10', `window now ${saved} days`);
await saveSettings(was || '7');

await finish();
