/**
 * Every identity a person holds, one tap away (the owner, 2026-09-27: "a
 * parent may be enrolled in a course, and he may be a vendor and a writer";
 * STATUS §5ic).
 *
 * As a person with several identities (SMOKE_MULTI, an account holding admin,
 * parent and vendor — grant them locally with tinker, revoke after): sign-in
 * lands on the admin panel; the header offers Family and My shop as pills, and
 * not Admin panel (this is it); Family opens the family portal, where the
 * pills are Admin panel and My shop; a Blade screen's user menu lists all
 * three under "Your views", and so does the phone menu. As the seeded vendor
 * (one identity): sign-in lands on the shop and no pill is offered.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/views.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_MULTI, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const MULTI = process.env.SMOKE_MULTI ?? 'admin@akuru.edu.mv';
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

const pills = (page) => page.locator('header [data-testid^="view-"]').evaluateAll((els) => els.map((el) => el.getAttribute('data-testid').replace('view-', '')));

// ------------------------------------------------------------ 1. three identities
const person = await signIn(MULTI, { width: 1400, height: 950 }, false);
check('an admin who is also a parent and a vendor lands on the admin panel', /\/admin$/.test(person.url()), person.url().replace(BASE, ''));
let shown = await pills(person);
check('the header offers Family and My shop — and not Admin panel, which this is', shown.join(',') === 'family,vendor', shown.join(','));
await person.click('header [data-testid="view-family"]');
await person.waitForURL(/\/portal\/home$/, { timeout: 15000 }).catch(() => {});
shown = await pills(person);
check('Family opens the family portal, where the pills are Admin panel and My shop', /\/portal\/home$/.test(person.url()) && shown.join(',') === 'admin,vendor', `${person.url().replace(BASE, '')} ${shown.join(',')}`);
await Promise.all([person.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), person.click('header [data-testid="view-vendor"]')]);
// A vendor role without a shop yet: the portal sends them to apply for one.
check('My shop opens the vendor portal', /\/vendor(\/apply)?$/.test(person.url()), person.url().replace(BASE, ''));

// The Blade shell: the user menu lists all three.
await person.goto(`${BASE}/en/admin/enrollments`, { waitUntil: 'networkidle' });
await person.click('button[aria-expanded][aria-controls=""], button:has(span:has-text("Admin User"))').catch(() => {});
const menuViews = await person.locator('nav [data-testid^="view-"]').evaluateAll((els) => els.map((el) => el.getAttribute('data-testid').replace('view-', '')));
check('a Blade screen lists the three views under "Your views" (user menu and phone menu)', menuViews.join(',') === 'admin,family,vendor,admin,family,vendor' && (await person.textContent('nav')).includes('Your views'), menuViews.join(','));

const phone = await signIn(MULTI, { width: 390, height: 844 }, true);
await phone.goto(`${BASE}/en/admin/enrollments`, { waitUntil: 'networkidle' });
await phone.click('button[aria-controls="nav-mobile-menu"]');
await phone.waitForSelector('#nav-mobile-menu', { state: 'visible' });
const phoneViews = await phone.locator('#nav-mobile-menu [data-testid^="view-"]').allInnerTexts();
check('on a phone the menu lists them by name', phoneViews.map((t) => t.trim()).join(' | ') === 'Admin panel | Family | My shop', phoneViews.join(' | '));

// ------------------------------------------------------------ 2. one identity
const vendor = await signIn(VENDOR, { width: 1400, height: 950 }, false);
check('the seeded vendor lands on their shop, not the public course dashboard', /\/vendor$/.test(vendor.url()), vendor.url().replace(BASE, ''));
check('and is offered no pill: one identity, nothing to switch to', (await pills(vendor)).length === 0, (await pills(vendor)).join(','));

await finish();
