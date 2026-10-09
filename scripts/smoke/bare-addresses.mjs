/**
 * Do the addresses the app sends people to without a language reach the app?
 * (STATUS §5pm.)
 *
 * The screens link and redirect to bare addresses (`/vendor`), and the
 * localization package sends a GET on to the remembered language. That only
 * works if the web server hands the request to the app. From 2026-10-03 a
 * folder in `public/` (the ID-card reader's files, in `public/vendor`) took
 * `/vendor`, so every one of these met a 404:
 *   - the bare address;
 *   - the redirect after the Vendor Agreement;
 *   - the portal's Home link on its other pages;
 *   - the portal's product search.
 * `/robots.txt` was answered by Laravel's default file, not by the app's
 * route.
 *
 * The seller (Fitrah's owner) walks each of those. Then the walk reads
 * `/robots.txt`, and fetches the ID-card reader from its new folder.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/bare-addresses.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await context.newPage();
const misses = [];
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => {
    if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`);
    if ([403, 404].includes(r.status()) && new URL(r.url()).pathname.startsWith('/vendor')) misses.push(`${r.status()} ${r.url()}`);
});

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', VENDOR);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

const portalShown = async () => (await page.locator('[data-testid="vendor-name"]').count()) === 1;

// 1. The bare address itself.
const bare = await page.goto(`${BASE}/vendor`, { waitUntil: 'networkidle' });
check('a bare /vendor reaches the portal, in the remembered language', bare?.status() === 200 && new URL(page.url()).pathname === '/en/vendor' && await portalShown(), `HTTP ${bare?.status()} ${page.url()}`);

// 2. The Vendor Agreement, when the seeder has asked for it again.
if ((await page.locator('[data-testid="accept-agreement"]').count()) > 0) {
    await page.check('[data-testid="accept-agreement"]');
    await page.click('[data-testid="agreement-form"] button[type=submit]');
    await page.locator('[data-testid="shop-sections"]').first().waitFor({ timeout: 20000 }).catch(() => {});
    check('accepting the Vendor Agreement opens the portal', (await page.locator('[data-testid="shop-sections"]').count()) === 1 && (await page.locator('[data-testid="accept-agreement"]').count()) === 0, page.url());
} else {
    check('accepting the Vendor Agreement opens the portal', (await page.locator('[data-testid="shop-sections"]').count()) === 1, `neither the agreement nor the portal is on ${page.url()}`);
}

// 3. Home, from another of the portal's pages.
await page.goto(`${BASE}/en/vendor/orders`, { waitUntil: 'networkidle' });
const home = page.locator('[data-testid="nav-home"]');
check('the portal\'s other pages link Home', (await home.count()) === 1);
if (await home.count()) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), home.click()]);
    check('and Home opens the portal', await portalShown() && new URL(page.url()).pathname === '/en/vendor', page.url());
}

// 4. The product search, which asks the bare address for its list.
const search = page.locator('#products form input[aria-label]').first();
check('the portal offers a product search', (await search.count()) === 1);
if (await search.count()) {
    await search.fill('Tracing');
    const before = await page.locator('[data-testid="products-total"]').innerText().catch(() => '');
    await page.click('[data-testid="filter-products"]');
    // The list is redrawn in place once the bare address answers.
    await page.waitForFunction((was) => document.querySelector('[data-testid="products-total"]')?.textContent !== was, before, { timeout: 20000 }).catch(() => {});
    const list = await page.locator('#products').innerText().catch(() => '');
    check('and finds a product by its name', list.includes('Arabic Letters Tracing Book') && !list.includes('Wooden Alphabet Puzzle'), list.replace(/\s+/g, ' ').slice(0, 200));
}

check('no portal address met a 403 or 404 on the way', misses.length === 0, misses.join(' | '));

// 5. robots.txt is the app's.
const robots = await page.request.get(`${BASE}/robots.txt`, { maxRedirects: 0 });
const robotsText = await robots.text();
check('/robots.txt is answered at the root by the app, with the sitemap', robots.status() === 200 && /text\/plain/.test(robots.headers()['content-type'] ?? '') && robotsText.includes('Disallow: /admin/') && robotsText.includes('Sitemap: '), `HTTP ${robots.status()} ${robotsText.slice(0, 60).replace(/\s+/g, ' ')}`);

// 6. The ID-card reader from its own folder.
const reader = await page.request.get(`${BASE}/ocr/tesseract/7.0.0/worker.min.js`);
check('the ID-card reader is served from /ocr/tesseract', reader.status() === 200 && /javascript/.test(reader.headers()['content-type'] ?? ''), `HTTP ${reader.status()} ${reader.headers()['content-type']}`);

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(72)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
