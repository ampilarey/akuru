/**
 * The admin panel's two shells, as the office (the layout audit, STATUS §5ht).
 *
 * On a phone: the Blade hamburger opens a menu that reaches the whole panel.
 * On a desktop: the More dropdown says whether it is open; the user menu in a
 * right-to-left locale stays inside the viewport; the tab title names the
 * screen; Tab lands on the skip link first. In the Inertia shell: the skip
 * link, the translated Alerts, the language switcher.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/admin-layout.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
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

async function signIn(email, viewport) {
    const context = await browser.newContext({ viewport });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${email}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${email}: HTTP ${response.status()} ${response.url()}`);
        }
    });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

const count = (page, selector) => page.locator(selector).count();

// ------------------------------------------------------------ 1. a phone

const phone = await signIn(ADMIN, { width: 390, height: 844 });
await phone.goto(`${BASE}/en/admin/enrollments`, { waitUntil: 'networkidle' });
check('on a phone the mobile menu starts closed and cloaked', !(await phone.locator('#nav-mobile-menu').isVisible()));
await phone.click('button[aria-controls="nav-mobile-menu"]');
await phone.locator('#nav-mobile-menu').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
const expanded = await phone.locator('button[aria-controls="nav-mobile-menu"]').getAttribute('aria-expanded');
const mobileLinks = await phone.locator('#nav-mobile-menu a').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
const wanted = ['/admin/enrollments', '/admin/instructors', '/admin/public-site/pages', '/admin/public-site/courses', '/admin/operations', '/admin/translations', '/admin/commerce', '/admin/library', '/admin/bookshop', '/admin/prayer-times/islands', '/admin/pronunciation'];
const missing = wanted.filter((href) => !mobileLinks.some((h) => h && h.endsWith(href)));
check('the hamburger opens it and says so', (await phone.locator('#nav-mobile-menu').isVisible()) && expanded === 'true', `aria-expanded=${expanded}`);
check('and the phone menu reaches the whole admin panel the admin role may open', missing.length === 0, missing.join(', '));
check('without Users or Settings for a plain admin', !mobileLinks.some((h) => h && (h.endsWith('/admin/users') || h.endsWith('/admin/settings'))));
const overflow = await phone.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
check('and nothing overflows the phone sideways', !overflow);

// ------------------------------------------------------------ 2. a desktop, left to right

const desk = await signIn(ADMIN, { width: 1400, height: 950 });
await desk.goto(`${BASE}/en/admin/public-site/pages`, { waitUntil: 'networkidle' });
check('the tab is titled after the screen', (await desk.title()).startsWith('Pages - '), await desk.title());
const more = desk.locator('button[aria-controls="nav-more-menu"]');
check('the More menu starts closed', (await more.getAttribute('aria-expanded')) === 'false' && !(await desk.locator('#nav-more-menu').isVisible()));
await more.click();
await desk.locator('#nav-more-menu').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
check('and says when it is open', (await more.getAttribute('aria-expanded')) === 'true' && (await desk.locator('#nav-more-menu').isVisible()));
// From a fresh load: the browser resumes tabbing from the last focused element otherwise.
await desk.goto(`${BASE}/en/admin/public-site/pages`, { waitUntil: 'networkidle' });
await desk.keyboard.press('Tab');
const focused = await desk.evaluate(() => ({ href: document.activeElement?.getAttribute('href'), text: document.activeElement?.textContent?.trim() }));
check('the first Tab lands on the skip link', focused.href === '#main' && /Skip to content/.test(focused.text || ''), JSON.stringify(focused));

// ------------------------------------------------------------ 3. a desktop, right to left

await desk.goto(`${BASE}/dv/admin/public-site/pages`, { waitUntil: 'networkidle' });
check('in Dhivehi the page is right-to-left', (await desk.locator('html').getAttribute('dir')) === 'rtl');
const userButton = desk.locator('button[aria-controls]').filter({ has: desk.locator('svg') }).nth(1);
await desk.locator('nav button[\\:aria-expanded="userMenuOpen"]').click().catch(async () => userButton.click());
const menu = desk.locator('nav div[x-show="userMenuOpen"]');
await menu.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
const box = await menu.boundingBox();
const vw = await desk.evaluate(() => window.innerWidth);
check('the user menu opens inside the viewport when the page is mirrored', box !== null && box.x >= 0 && box.x + box.width <= vw + 1, box ? `x=${Math.round(box.x)} w=${Math.round(box.width)} vw=${vw}` : 'no box');

// ------------------------------------------------------------ 4. the Inertia shell

await desk.goto(`${BASE}/en/admin/operations`, { waitUntil: 'networkidle' });
check('the Inertia shell has the skip link and a main landmark', (await count(desk, 'a[href="#main"]')) === 1 && (await count(desk, 'main#main')) === 1);
check('and a language switcher', (await count(desk, 'a[href*="/dv/admin/operations"], a[href^="/dv"]')) >= 1);
await desk.goto(`${BASE}/dv/admin/operations`, { waitUntil: 'networkidle' });
const alerts = await desk.locator('a[href="/portal/notifications"]').innerText().catch(() => '');
check('in Dhivehi the Alerts link is in Dhivehi', alerts.includes('އެލާޓް'), alerts);

await finish();
