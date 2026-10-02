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
 * Since §5mq the settings are folding cards with a one-line summary each, and
 * a checklist at the top says what stands between the shop and its customers.
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

// ------------------------------------------------------------ 2. what stands between the shop and its customers (§5mq)

const ready = await page.locator('[data-testid="shop-readiness"]').innerText().catch(() => '');
check('a "Your shop page" checklist sits at the top: ID card, products on sale, page design', (await count('[data-testid="shop-readiness"] [data-testid^="ready-"]')) >= 3 && /ID card/i.test(ready), ready.replace(/\s+/g, ' ').slice(0, 160));
const folded = await page.evaluate(() => [...document.querySelectorAll('section[data-open]')].map((s) => s.dataset.open));
check('the seven settings start folded on a phone, each a heading with a one-line summary', folded.length >= 6 && folded.every((o) => o === '0') && (await count('[data-testid$="-summary"]')) >= 6, `${folded.length} cards, ${folded.filter((o) => o === '0').length} folded`);

// ------------------------------------------------------------ 3. getting to the settings

const chips = await count('[data-testid="section-nav"] a');
check('a row of section chips sits under the heading, each big enough for a thumb', chips >= 7 && (await smallTargets('[data-testid="section-nav"] a')).length === 0, `${chips} chips`);
const navBefore = await page.evaluate(() => document.querySelector('[data-testid="section-nav"]').getBoundingClientRect().top);
await page.click('[data-testid="jump-settings"]');
await page.waitForTimeout(500);
const settingsTop = await page.evaluate(() => document.querySelector('[data-testid="shop-settings"]').getBoundingClientRect().top);
const navAfter = await page.evaluate(() => document.querySelector('[data-testid="section-nav"]').getBoundingClientRect().top);
check('tapping "Returns and holidays" opens that card and brings it onto the screen', settingsTop >= 0 && settingsTop < 300 && (await count('[data-testid="return-window"]')) === 1, `settings at ${Math.round(settingsTop)}px from the top`);
check('and the chips stay pinned to the top while the page scrolls', navAfter >= 0 && navAfter <= 1 && navBefore > 1, `chips at ${Math.round(navAfter)}px (were ${Math.round(navBefore)}px)`);
await page.click('[data-testid="jump-delivery"]');
await page.waitForTimeout(500);
const deliveryTop = await page.evaluate(() => document.querySelector('[data-testid="delivery-methods"]').getBoundingClientRect().top);
check('"Delivery methods" too', deliveryTop >= 0 && deliveryTop < 300, `${Math.round(deliveryTop)}px from the top`);

// ------------------------------------------------------------ 4. labelled boxes, thumb-sized links

await page.click('[data-testid="add-method"]');
await settle('[data-testid="delivery-row-0"]');
const labelled = await page.evaluate(() => {
    const row = document.querySelector('[data-testid="delivery-row-0"]');
    const boxes = row.querySelectorAll('input:not([type=checkbox]), select');
    const named = [...boxes].filter((b) => b.closest('label')?.querySelector('span')?.innerText.trim());
    return { boxes: boxes.length, named: named.length, cols: new Set([...boxes].map((b) => Math.round(b.getBoundingClientRect().left))).size };
});
check('a new delivery method is nine labelled boxes, two to a row', labelled.boxes === 9 && labelled.named === 9 && labelled.cols === 2, `${labelled.named}/${labelled.boxes} labelled, ${labelled.cols} columns`);
await page.click('[data-testid="jump-notices"]');
await page.waitForTimeout(400);
const noticeHeads = await page.evaluate(() => [...document.querySelectorAll('[data-testid="shop-notices"] th')].filter((th) => th.getBoundingClientRect().width > 0).length);
check('the notices table drops its always-ticked "in the app" column on a phone', noticeHeads === 3, `${noticeHeads} columns showing`);
const small = await smallTargets('[data-testid="product-list"] .table-actions a, [data-testid="product-list"] .table-actions button');
check('every Edit, Duplicate and View link on a product card is big enough for a thumb', small.length === 0, small.length ? small.slice(0, 4).join(' | ') : `${await count('[data-testid="product-list"] .table-actions a, [data-testid="product-list"] .table-actions button')} links, none under 32px`);

// ------------------------------------------------------------ 5. a save on the phone

// Saved means read back after a reload — a flash may already be on the page from the step before.
const saveSettings = async (value) => {
    if (!(await count('[data-testid="return-window"]'))) {
        await page.click('[data-testid="jump-settings"]');
        await page.locator('[data-testid="return-window"]').waitFor({ timeout: 10000 }).catch(() => {});
    }
    await page.fill('[data-testid="return-window"]', value);
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/vendor/settings') && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}),
        page.click('[data-testid="save-settings"]'),
    ]);
    await settle();
    await page.reload({ waitUntil: 'networkidle' });
    await page.click('[data-testid="jump-settings"]');
    await page.locator('[data-testid="return-window"]').waitFor({ timeout: 10000 }).catch(() => {});
    return page.locator('[data-testid="return-window"]').inputValue();
};
const was = await page.locator('[data-testid="return-window"]').inputValue();
const saved = await saveSettings('10');
check('the returns window saves from the phone and reads back after a reload', saved === '10', `window now ${saved} days`);
await saveSettings(was || '7');

// ------------------------------------------------------------ 6. the shop's other pages fit the same phone

const subpages = [
    ['/en/vendor/orders', 'orders-heading', 'open-orders'],
    ['/en/vendor/money', 'money-heading', 'open-money'],
    ['/en/vendor/stock', 'stock-heading', 'open-stock'],
    ['/en/vendor/reviews', 'reviews-heading', 'open-reviews'],
    ['/en/vendor/quotes', 'quotes-heading', 'open-quotes'],
    ['/en/vendor/insights', 'insights-heading', 'open-insights'],
    ['/en/vendor/storefront', 'designer-heading', 'open-designer'],
    ['/en/vendor/storefront/sections', 'sections-heading', 'open-sections'],
];
for (const [path, heading, current] of subpages) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    await settle(`[data-testid="${heading}"]`);
    const wide = await overflow();
    const here = await page.evaluate(() => {
        const link = document.querySelector('[data-testid="vendor-subnav"] a[aria-current="page"]');
        const links = [...document.querySelectorAll('[data-testid="vendor-subnav"] a')];
        const width = Math.min(document.documentElement.clientWidth, window.screen.width);
        return {
            n: links.length,
            current: link?.getAttribute('data-testid') || '',
            short: links.filter((a) => a.getBoundingClientRect().height < 32).length,
            outside: links.filter((a) => {
                const r = a.getBoundingClientRect();
                return r.right > width + 1 || r.left < -1;
            }).length,
        };
    });
    check(`${path} fits the phone and its row marks this page`, (await count(`[data-testid="${heading}"]`)) === 1 && !wide.over && here.n === 9 && here.short === 0 && here.outside === 0 && here.current === current, wide.over ? `wider by ${wide.by}px` : `${here.n} links, ${here.outside} past the edge, current ${here.current}`);
    if (current === 'open-designer') {
        const folds = await page.evaluate(() => ({
            look: document.querySelector('[data-testid="designer-look"]')?.dataset.open || '',
            identity: document.querySelector('[data-testid="designer-identity"]')?.dataset.open || '',
            preview: document.querySelector('[data-testid="designer-preview"]')?.dataset.open || '',
        }));
        check('Look starts open; Identity and the preview start folded', folds.look === '1' && folds.identity === '0' && folds.preview === '0', JSON.stringify(folds));
        await page.click('[data-testid="designer-identity-toggle"]');
        check('tapping Identity shows its details', await page.locator('[data-testid="story"]').isVisible());
        await page.click('[data-testid="designer-identity-toggle"]');
        check('tapping Identity again folds those details away', (await page.locator('[data-testid="designer-identity"]').getAttribute('data-open')) === '0' && !(await page.locator('[data-testid="story"]').isVisible()));
        await page.click('[data-testid="designer-look-toggle"]');
        check('tapping Look folds it', (await page.locator('[data-testid="designer-look"]').getAttribute('data-open')) === '0' && !(await page.locator('[data-testid="color-primary"]').isVisible()));
        await page.click('[data-testid="designer-look-toggle"]');
        check('tapping Look again brings the colours back', await page.locator('[data-testid="color-primary"]').isVisible());
    }
}
const groups = await page.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' }).then(() => page.click('[data-testid="jump-settings"]')).then(() => page.waitForTimeout(400)).then(() => count('[data-testid="shop-settings"] fieldset legend'));
check('returns, holiday, and delivery-and-cash are three groups inside the settings card', groups === 3, `${groups} groups`);

await finish();
