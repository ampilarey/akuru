/**
 * The admin panel in four parts (the owner, 2026-09-26: "still admin page
 * is too much complicated — can't u categorize and group everything to
 * make it easy"; STATUS §5hy).
 *
 * As the seeded `admin` account: /admin shows Admissions, Website & content,
 * Shops & money and System, each a row of cards; a card lists the screens
 * inside its section; a screen inside a Blade section opens with a full
 * page load and an Inertia section with a visit; the Inertia shell's More
 * menu heads its admin column with the same four parts and the Blade More
 * dropdown and mobile menu carry the same headings; at phone width the
 * cards stack and nothing is cut off. Users and Settings are absent for a
 * plain admin; with SMOKE_SUPER_ADMIN, present under System.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/admin-hub.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_SUPER_ADMIN (a second account; the plain-admin
 *              steps expect SMOKE_ADMIN not to be one), SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? null;
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

async function signIn(email, viewport, mobile) {
    const context = await browser.newContext({ viewport, isMobile: mobile, hasTouch: mobile });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`HTTP ${response.status()} ${response.url()}`);
        }
    });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

const count = (page, selector) => page.locator(selector).count();
const texts = (page, selector) => page.locator(selector).allTextContents();
const hrefs = (page, selector) => page.locator(selector).evaluateAll((els) => els.map((el) => (el.getAttribute('href') || '').replace(/^https?:\/\/[^/]+/, '').replace(/^\/(en|dv|ar)(?=\/|$)/, '')));

// ------------------------------------------------------------ 1. the hub, in four parts
const office = await signIn(ADMIN, { width: 1400, height: 950 }, false);
const hub = await office.goto(`${BASE}/en/admin`, { waitUntil: 'networkidle' });
const parts = await texts(office, '[data-testid^="part-"] h2');
check('the hub opens with its four parts, in order', hub?.status() === 200 && parts.join(' | ') === 'Admissions | Website & content | Shops & money | System', parts.join(' | '));
check('and a row of part links at the top, one per part with its count', (await count(office, '[data-testid="hub-parts"] a')) === 4 && /Website & content\s*3/.test((await texts(office, '[data-testid="hub-parts"] a')).join(' ')));
const sections = await count(office, '[data-testid^="section-"]');
check('a plain admin sees eleven sections — the panel less Users and Settings', sections === 11 && (await count(office, '[data-testid="section-manage_users"], [data-testid="section-system_settings"]')) === 0, `${sections}`);
check('Admissions holds Enrolments and Instructors', (await count(office, '[data-testid="part-panel_admissions"] [data-testid="section-admin_enrolments"], [data-testid="part-panel_admissions"] [data-testid="section-admin_instructors"]')) === 2);
check('Shops & money holds Commerce, the Library office and the Bookstore', (await count(office, '[data-testid="part-panel_money"] [data-testid^="section-"]')) === 3);
const cmsChildren = await texts(office, '[data-testid="section-website_cms"] [data-testid^="child-"]');
check('the Website card lists its eight screens', cmsChildren.length === 8 && cmsChildren[0] === 'Pages' && cmsChildren[7] === 'Funnel', cmsChildren.join(', '));
check('the prayer-times card its four, and Enrolments its Payments', (await count(office, '[data-testid="section-prayer_times"] [data-testid^="child-"]')) === 4 && (await count(office, '[data-testid="child-enrolment_payments"]')) === 1);
check('a Blade screen inside a section is a plain link; an Inertia section a visit', (await office.locator('[data-testid="child-prayer_groups"]').evaluate((el) => el.tagName)) === 'A' && (await office.locator('[data-testid="open-commerce"]').evaluate((el) => el.getAttribute('href')))?.endsWith('/admin/commerce'));

// Open a screen inside a section, then a section, then come back through the shell.
await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.click('[data-testid="child-prayer_groups"]')]);
check('Recipient groups (a screen inside Prayer times) opens as its Blade page', /\/admin\/prayer-times\/groups$/.test(office.url()) && (await office.textContent('body')).includes('ecipient'), office.url().replace(BASE, ''));
await office.goto(`${BASE}/en/admin`, { waitUntil: 'networkidle' });
// An Inertia visit: wait for the address, not a navigation.
await office.click('[data-testid="open-commerce"]');
await office.waitForURL(/\/admin\/commerce$/, { timeout: 15000 }).catch(() => {});
check('Commerce opens from its card', /\/admin\/commerce$/.test(office.url()), office.url().replace(BASE, ''));

// ------------------------------------------------------------ 2. the Inertia More menu: the admin column headed by the parts
await office.click('button[aria-controls="app-shell-more"]');
await office.waitForSelector('#app-shell-more', { state: 'visible' });
const heads = await texts(office, '#app-shell-more [data-nav-section]');
check('the shell’s More menu heads its admin column with the four parts', heads.join(' | ') === 'Admissions | Website & content | Shops & money | System', heads.join(' | '));
const moreLinks = await hrefs(office, '#app-shell-more a');
check('with the front door first and the sections under their parts', moreLinks.includes('/admin') && moreLinks.indexOf('/admin/enrollments') < moreLinks.indexOf('/admin/public-site/pages') && moreLinks.indexOf('/admin/public-site/pages') < moreLinks.indexOf('/admin/commerce') && moreLinks.indexOf('/admin/commerce') < moreLinks.indexOf('/admin/operations'));
check('and the inner screens stay off the menu (they are on the hub)', !moreLinks.includes('/admin/prayer-times/groups') && !moreLinks.includes('/admin/public-site/funnel'));
// An Inertia visit, not a page load: wait for the address, not a navigation.
await office.click('#app-shell-more a[href$="/admin"]');
await office.waitForURL(/\/admin$/, { timeout: 15000 }).catch(() => {});
check('Admin panel in the More menu returns to the hub', /\/admin$/.test(office.url()), office.url().replace(BASE, ''));

// ------------------------------------------------------------ 3. the Blade menus, same headings
await office.goto(`${BASE}/en/admin/enrollments`, { waitUntil: 'networkidle' });
await office.click('button[aria-controls="nav-more-menu"]');
await office.waitForSelector('#nav-more-menu', { state: 'visible' });
const bladeHeads = await texts(office, '#nav-more-menu [data-nav-section]');
check('the Blade More dropdown carries School and the four parts', bladeHeads.map((t) => t.trim()).join(' | ') === 'School | Admissions | Website & content | Shops & money | System', bladeHeads.join(' | '));
const dropdown = await hrefs(office, '#nav-more-menu a');
check('with the panel under them and no Users or Settings for a plain admin', dropdown[0] === '/admin' && dropdown.includes('/admin/instructors') && dropdown.includes('/admin/bookshop') && !dropdown.some((h) => h.endsWith('/admin/users') || h.endsWith('/admin/settings')));
const menuBox = await office.locator('#nav-more-menu').boundingBox();
check('and the dropdown fits on the screen', menuBox && menuBox.x >= 0 && menuBox.x + menuBox.width <= 1400 && menuBox.y + menuBox.height <= 950, JSON.stringify(menuBox));

// ------------------------------------------------------------ 4. a phone
const phone = await signIn(ADMIN, { width: 390, height: 844 }, true);
await phone.goto(`${BASE}/en/admin`, { waitUntil: 'networkidle' });
const phoneMeasure = await phone.evaluate(() => {
    const vw = window.innerWidth;
    const cards = Array.from(document.querySelectorAll('[data-testid^="section-"]')).map((el) => el.getBoundingClientRect());
    const lefts = new Set(cards.map((r) => Math.round(r.left)));
    const wide = Array.from(document.querySelectorAll('body *')).filter((el) => el.getBoundingClientRect().right > vw + 1).length;
    return { overflow: document.documentElement.scrollWidth - vw, columns: lefts.size, wide, parts: document.querySelectorAll('[data-testid="hub-parts"] a').length };
});
check('at 390 px the cards stack in one column and nothing is cut off', phoneMeasure.overflow <= 1 && phoneMeasure.columns === 1 && phoneMeasure.wide === 0 && phoneMeasure.parts === 4, JSON.stringify(phoneMeasure));
await phone.click('[data-testid="hub-parts"] a[href="#panel_money"]');
const moneyTop = await phone.locator('[data-testid="part-panel_money"]').evaluate((el) => el.getBoundingClientRect().top);
check('a part link jumps to its part', moneyTop >= -2 && moneyTop < 200, `${Math.round(moneyTop)}px`);
await phone.goto(`${BASE}/en/admin/enrollments`, { waitUntil: 'networkidle' });
await phone.click('button[aria-controls="nav-mobile-menu"]');
await phone.waitForSelector('#nav-mobile-menu', { state: 'visible' });
const mobileHeads = await texts(phone, '#nav-mobile-menu [data-nav-section]');
check('the Blade mobile menu carries the same headings', mobileHeads.map((t) => t.trim()).join(' | ') === 'School | Admissions | Website & content | Shops & money | System', mobileHeads.join(' | '));

// ------------------------------------------------------------ 5. one home (the owner: "I don't understand what's
// happening sometimes, /dashboard or /admin", then "I don't know"): /dashboard lands an administrator here, with
// today's numbers on top and the full dashboard a link away.
await office.goto(`${BASE}/en/dashboard`, { waitUntil: 'networkidle' });
check('an admin’s /dashboard lands on the admin panel', /\/admin$/.test(office.url()), office.url().replace(BASE, ''));
const tiles = await texts(office, '[data-testid="today"] [data-testid^="today-"] span:last-child');
check('with Today on top: pending payment, enrolled today, paid today, new accounts, unfilled registers, ungraded exams', tiles.length === 6 && tiles[0] === 'Pending payment' && tiles[5] === 'Ungraded exams', tiles.join(', '));
const values = await texts(office, '[data-testid="today"] [data-testid^="today-"] span:first-child');
check('each with a number', values.length === 6 && values.every((v) => /^[\d,.]+$/.test(v)), values.join(', '));
await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.click('[data-testid="today-pending_payment"] a')]);
check('the pending-payment tile opens the enrolments', /\/admin\/enrollments$/.test(office.url()), office.url().replace(BASE, ''));
await office.goto(`${BASE}/en/admin`, { waitUntil: 'networkidle' });
await office.click('[data-testid="today-more"]');
await office.waitForURL(/\/portal\/overview$/, { timeout: 15000 }).catch(() => {});
check('and the full-dashboard link opens the staff overview, which still carries the Admin panel button', /\/portal\/overview$/.test(office.url()) && (await count(office, '[data-testid="open-admin-panel"]')) === 1, office.url().replace(BASE, ''));
await office.click('[data-testid="open-admin-panel"]');
await office.waitForURL(/\/admin$/, { timeout: 15000 }).catch(() => {});
check('whose button comes back here', /\/admin$/.test(office.url()), office.url().replace(BASE, ''));

// ------------------------------------------------------------ 6. a super admin, when given
if (SUPER) {
    const su = await signIn(SUPER, { width: 1400, height: 950 }, false);
    await su.goto(`${BASE}/en/admin`, { waitUntil: 'networkidle' });
    check('a super admin sees all thirteen sections, Users and Settings first under System', (await count(su, '[data-testid^="section-"]')) === 13 && (await texts(su, '[data-testid="part-panel_system"] [data-testid^="open-"]')).slice(0, 2).join(' | ') === 'Manage users | System settings');
    await su.goto(`${BASE}/en/dashboard`, { waitUntil: 'networkidle' });
    check('a super admin’s /dashboard lands on the admin panel too, with the full dashboard a link away', /\/admin$/.test(su.url()) && (await office.locator('[data-testid="today-more"]').count()) >= 0 && (await su.getAttribute('[data-testid="today-more"]', 'href') || '').endsWith('/dashboard/numbers'), su.url().replace(BASE, ''));
    await Promise.all([su.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), su.click('[data-testid="today-more"]')]);
    check('the full dashboard opens, and carries the Admin panel button', /\/dashboard\/numbers$/.test(su.url()) && (await count(su, '[data-testid="open-admin-panel"]')) === 1 && (await su.textContent('body')).includes('Super Admin Dashboard'), su.url().replace(BASE, ''));
    await Promise.all([su.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), su.click('[data-testid="open-admin-panel"]')]);
    check('and it comes back here', /\/admin$/.test(su.url()), su.url().replace(BASE, ''));
}

await finish();
