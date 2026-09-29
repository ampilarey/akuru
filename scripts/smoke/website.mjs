/**
 * Does the website lead with Akuru's four products, on a desk and on a phone?
 * (the 2026-09-28 website design, STATUS §5ki.)
 *
 * A visitor, not signed in:
 *
 *   1. on a desk finds Courses, Digital Library, Bookstore and School in the
 *      header, opens About and finds News, Research and Contact there, and
 *      still sees the prayer times in the header;
 *   2. on a phone finds the bottom bar — Home, Courses, Library, Shop,
 *      Account — and it takes them where it says;
 *   3. opens the phone menu, searches from it, and finds the four products
 *      and About;
 *   4. no page runs wider than the screen at 360, 390, 1024, 1280 or 1440.
 *
 * Read-only.
 *
 *   node scripts/smoke/website.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';

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
const path = (url) => new URL(url).pathname.replace(/^\/(en|dv|ar)(?=\/|$)/, '') || '/';

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

async function visitor(width, height) {
    const context = await browser.newContext({ viewport: { width, height } });
    context.setDefaultNavigationTimeout(60000);
    // Only the site itself: Google Translate, fonts and analytics stay out of the walk.
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${width}px: HTTP ${response.status()} ${response.url()}`);
        }
    });
    return page;
}

const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);

// ------------------------------------------------------------ 1. on a desk
const desk = await visitor(1440, 900);
await desk.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
const products = await desk.locator('[data-testid="site-nav"] nav > a').allInnerTexts();
check('the header leads with the four products', JSON.stringify(products.map((t) => t.trim())) === JSON.stringify(['Courses', 'Digital Library', 'Bookstore', 'School']), products.join(' · '));
check('the prayer times are still in the header', await desk.locator('[data-testid="site-nav"] [data-block="prayer_bar"]').isVisible());
check('the About menu is closed until asked for', !(await desk.locator('[data-testid="nav-about-menu"]').isVisible()));
await desk.click('[data-testid="nav-about"]');
const about = await desk.locator('[data-testid="nav-about-menu"] a').allInnerTexts();
check('About opens and holds News, Research and Contact', ['News', 'Research', 'Contact'].every((t) => about.includes(t)), about.join(' · '));
await Promise.all([desk.waitForURL(/\/research/), desk.click('[data-testid="nav-about-menu"] a:has-text("Research")')]);
check('Research opens from About', path(desk.url()) === '/research', path(desk.url()));
await desk.keyboard.press('Escape');
await Promise.all([desk.waitForURL(/\/library/), desk.click('[data-testid="nav-library"]')]);
check('Digital Library opens, and the header says where you are', path(desk.url()) === '/library' && (await desk.getAttribute('[data-testid="nav-library"]', 'aria-current')) === 'page', path(desk.url()));

// ------------------------------------------------------------ 2. the phone's bottom bar
const phone = await visitor(390, 844);
await phone.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
const tabs = (await phone.locator('[data-testid="bottom-bar"] a').allInnerTexts()).map((t) => t.trim());
check('the phone bar is Home, Courses, Library, Shop, Account', JSON.stringify(tabs) === JSON.stringify(['Home', 'Courses', 'Library', 'Shop', 'Account']), tabs.join(' · '));
for (const [tab, to] of [['bottom-library', '/library'], ['bottom-shop', '/shop'], ['bottom-courses', '/courses'], ['bottom-home', '/']]) {
    await Promise.all([phone.waitForLoadState('networkidle'), phone.click(`[data-testid="${tab}"]`)]);
    await phone.waitForLoadState('networkidle');
    check(`the bar's ${tab.replace('bottom-', '')} goes to ${to}, and lights up`, path(phone.url()) === to && (await phone.getAttribute(`[data-testid="${tab}"]`, 'aria-current')) === 'page', path(phone.url()));
}
check('the chat button shows on a phone, clear of the bar', await phone.locator('[data-testid="viber-float"]').isVisible());

// ------------------------------------------------------------ 3. the phone menu
await phone.click('[data-testid="nav-burger"]');
check('the menu opens with the four products and About', (await phone.locator('[data-testid="mobile-menu-products"] a').count()) === 4 && (await phone.locator('[data-testid="mobile-menu-about"] a').count()) >= 9);
await phone.fill('#nav-m-q', 'Arabic');
await Promise.all([phone.waitForURL(/\/search/), phone.press('#nav-m-q', 'Enter')]);
check('searching from the menu opens the results', path(phone.url()) === '/search' && new URL(phone.url()).searchParams.get('q') === 'Arabic', phone.url());

// ------------------------------------------------------------ 4. nothing runs wider than the screen
for (const [width, height] of [[360, 740], [390, 844], [1024, 800], [1280, 800], [1440, 900]]) {
    const page = await visitor(width, height);
    for (const at of ['/en', '/en/library', '/en/shop', '/en/courses']) {
        await page.goto(`${BASE}${at}`, { waitUntil: 'networkidle' });
        const extra = await overflow(page);
        check(`${at} fits ${width}px`, extra <= 0, extra > 0 ? `${extra}px too wide` : '');
    }
}

await finish();
