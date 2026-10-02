/**
 * The four long admin pages, shortened (ADMIN_PANEL.md §7 P5 and M6,
 * STATUS §5no), walked on a phone:
 *
 *  - Prayer islands: 25 a page with a search, not 205 rows at once.
 *  - Translations: the group chips, the search and the suspect filter are
 *    the URL; 25 rows of the active group arrive, not the whole catalog.
 *  - Feature testing: the sections fold; a heading opens one, a filter
 *    opens the ones it finds something in, Open all opens them all.
 *  - The Bookstore office: a sticky row of chips jumps to each section.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/long-pages.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN (a super admin), SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--no-first-run', '--no-default-browser-check'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
let finished = false;
async function finish() {
    if (finished) return;
    finished = true;
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}
for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => { check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200)); await finish(); });
}

const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
context.setDefaultNavigationTimeout(60000);
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();
page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
page.on('response', (response) => { if (response.status() >= 500) problems.push(`HTTP ${response.status()} ${response.url()}`); });

const count = (selector) => page.locator(selector).count();
const height = () => page.evaluate(() => document.documentElement.scrollHeight);
const settle = (selector) => page.waitForSelector(selector, { timeout: 20000 });

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

// Prayer islands: a page, a search.
await page.goto(`${BASE}/en/admin/prayer-times/islands`, { waitUntil: 'networkidle' });
await settle('[data-testid="islands-table"]');
const islandRows = await count('[data-testid="island-row"]');
const islandsShowing = (await page.locator('[data-testid="islands-showing"]').textContent().catch(() => ''))?.trim() ?? '';
check('the islands hub shows at most 25 rows and says how many there are', islandRows <= 25 && /\d+ of \d+/.test(islandsShowing), `${islandRows} rows · "${islandsShowing}" · ${await height()}px tall`);
const paged = (await count('[data-testid="islands-pagination"]')) === 1;
check('and a page row when there are more', paged || islandRows < 25, paged ? 'paged' : `${islandRows} rows, one page`);
if (paged) {
    await page.locator('[data-testid="islands-pagination"] a').last().click();
    await page.waitForURL(/page=2/, { timeout: 20000 });
    check('Next turns the page', (await count('[data-testid="island-row"]')) > 0 && page.url().includes('page=2'), page.url().replace(BASE, ''));
}
await page.goto(`${BASE}/en/admin/prayer-times/islands`, { waitUntil: 'networkidle' });
const firstIsland = (await page.locator('[data-testid="island-row"] td:nth-child(2)').first().textContent().catch(() => ''))?.trim().split(' ')[0] ?? '';
if (firstIsland) {
    await page.fill('[data-testid="islands-search"]', firstIsland);
    await page.press('[data-testid="islands-search"]', 'Enter');
    await page.waitForURL((url) => url.searchParams.get('q') === firstIsland, { timeout: 20000 });
    await settle('[data-testid="island-row"]');
    const found = await page.locator('[data-testid="island-row"]').allTextContents();
    check('the search narrows the list to the islands that match', found.length > 0 && found.every((row) => row.toLowerCase().includes(firstIsland.toLowerCase())), `${found.length} for "${firstIsland}"`);
}

// Translations: the URL is the filter; one page of one group.
await page.goto(`${BASE}/en/admin/translations`, { waitUntil: 'networkidle' });
await settle('textarea');
const trRows = await count('tbody tr');
check('the translation editor shows one page of the active group', trRows > 0 && trRows <= 25, `${trRows} rows · ${await height()}px tall`);
await page.click('[data-testid="group-learn"]');
await page.waitForURL(/group=learn/, { timeout: 20000 });
await settle('textarea');
check('a group chip is a visit that the URL remembers', page.url().includes('group=learn') && (await page.locator('[data-testid="translations-showing"]').textContent()).includes('learn'), page.url().replace(BASE, ''));
await page.goto(`${BASE}/en/admin/translations?q=dashboard`, { waitUntil: 'networkidle' });
await settle('textarea');
const searched = await page.locator('tbody tr td:first-child').allTextContents();
check('?q= searches on the server', searched.length > 0 && searched.every((cell) => cell.toLowerCase().includes('dashboard')), `${searched.length} rows`);
// A click, not `check()`: the box is the URL's state, so the tick arrives with the visit it starts.
await page.click('[data-testid="suspect-only"]');
await page.waitForURL(/suspect=1/, { timeout: 20000 });
check('the suspect filter joins the URL', page.url().includes('suspect=1') && page.url().includes('q=dashboard'), page.url().replace(BASE, ''));

// Feature testing: folded.
await page.goto(`${BASE}/en/admin/operations/features`, { waitUntil: 'networkidle' });
await settle('[data-testid^="feature-section-toggle-"]');
const sections = await count('[data-testid^="feature-section-toggle-"]');
const openAtStart = await count('[data-testid^="feature-section-"][data-open="true"]');
const itemsAtStart = await count('[data-testid^="feature-open-ft-"]');
const foldedHeight = await height();
check('Feature testing opens folded: headings with counts, no items drawn', sections > 3 && openAtStart === 0 && itemsAtStart === 0 && foldedHeight < 4000, `${sections} sections · ${itemsAtStart} items · ${foldedHeight}px tall`);
await page.locator('[data-testid^="feature-section-toggle-"]').first().click();
const afterOne = await count('[data-testid^="feature-open-ft-"]');
check('a heading opens its section', afterOne > 0 && (await count('[data-testid^="feature-section-"][data-open="true"]')) === 1, `${afterOne} items`);
await page.click('[data-testid="feature-open-all"]');
const afterAll = await count('[data-testid^="feature-open-ft-"]');
check('Open all opens every section', afterAll > afterOne && (await count('[data-testid^="feature-section-"][data-open="false"]')) === 0, `${afterAll} items · ${await height()}px tall`);
await page.click('[data-testid="feature-open-all"]');
check('and closes them again', (await count('[data-testid^="feature-open-ft-"]')) === 0);
await page.click('[data-testid="filter-untested"]');
check('a filter opens the sections it finds something in', (await count('[data-testid^="feature-open-ft-"]')) > 0);

// The Bookstore office: chips that jump.
await page.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle('[data-testid="section-nav"]');
const chips = await page.locator('[data-testid="section-nav"] a').allTextContents();
const chipBox = await page.locator('[data-testid="section-nav"]').boundingBox();
check('the office has a row of jump chips, inside the phone', chips.length >= 8 && chipBox && chipBox.x >= 0 && chipBox.x + chipBox.width <= 391, `${chips.length} chips: ${chips.slice(0, 5).join(', ')}…`);
const missing = [];
for (const id of await page.locator('[data-testid="section-nav"] a').evaluateAll((as) => as.map((a) => a.getAttribute('href').slice(1)))) {
    if ((await count(`#${id}`)) !== 1) missing.push(id);
}
check('every chip has its section on the page', missing.length === 0, missing.join(', '));
await page.click('[data-testid="jump-orders"]');
await page.waitForTimeout(600);
const ordersTop = await page.evaluate(() => document.getElementById('orders')?.getBoundingClientRect().top ?? 9999);
check('tapping Orders scrolls the orders section under the chips', ordersTop >= -2 && ordersTop < 200, `${Math.round(ordersTop)}px from the top`);
const navTop = await page.evaluate(() => document.querySelector('[data-testid="section-nav"]')?.getBoundingClientRect().top ?? 9999);
check('and the chip row stays in view, stuck to the top', navTop >= -1 && navTop < 80, `${Math.round(navTop)}px`);

await finish();
