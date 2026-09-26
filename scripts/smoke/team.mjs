/**
 * Can the office have Bookstore admins who run the Bookstore and nothing
 * else? (BOOKSHOP_PLAN slice B10b.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` takes the role off the parent.
 *
 * The admin:
 *   1. opens /admin/bookshop and adds parent@akuru.edu.mv as a Bookstore
 *      admin; they are listed;
 * The parent:
 *   2. signs in and lands on the Bookstore office screen, with "Bookstore"
 *      in their menu, but no add/remove on the team;
 *   3. is still kept out of the school's admin pages;
 * The admin:
 *   4. removes them;
 * The parent:
 *   5. is kept out of the Bookstore office screen again.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/team.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_APPLICANT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const PARENT = process.env.SMOKE_APPLICANT ?? 'parent@akuru.edu.mv';

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

async function signIn(email) {
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 }, acceptDownloads: true });
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

const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const count = (page, selector) => page.locator(selector).count();
const inner = async (page, selector) => (await page.locator(selector).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
const csvOf = async (page, href) => {
    const response = await page.request.get(href.startsWith('http') ? href : `${BASE}${href}`);
    return { status: response.status(), text: await response.text() };
};

// ------------------------------------------------------------ 1. the admin adds them

const admin = await signIn(ADMIN);
await admin.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(admin, '[data-testid="office-team"]');
await admin.fill('[data-testid="team-email"]', PARENT);
await admin.click('[data-testid="team-add"]');
await admin.waitForSelector(`[data-testid="team-${PARENT}"]`, { timeout: 20000 }).catch(() => {});
check('the admin adds the parent as a Bookstore admin', (await count(admin, `[data-testid="team-${PARENT}"]`)) === 1);
check('no one-time password for an existing account', (await count(admin, '[data-testid="team-password"]')) === 0);

// ------------------------------------------------------------ 2. they run the Bookstore

const manager = await signIn(PARENT);
await manager.goto(`${BASE}/en/dashboard`, { waitUntil: 'networkidle' });
await settle(manager, '[data-testid="office-team"]');
check('the parent lands on the Bookstore office screen', manager.url().includes('/admin/bookshop'), manager.url());
check('with the team listed but no add or remove', (await count(manager, '[data-testid="team-add"]')) === 0 && (await count(manager, '[data-testid^="team-remove-"]')) === 0);
check('and Bookstore in their menu', (await manager.locator('a[href$="/admin/bookshop"]').count()) > 0);

// ------------------------------------------------------------ 3. not the school's admin

const ops = await manager.request.get(`${BASE}/en/admin/operations`, { failOnStatusCode: false, maxRedirects: 0 });
check('the school admin pages stay closed', ops.status() === 403, String(ops.status()));

// ------------------------------------------------------------ 4. removed

await admin.reload({ waitUntil: 'networkidle' });
await settle(admin, '[data-testid^="team-remove-"]');
admin.once('dialog', (dialog) => dialog.accept());
await admin.locator('[data-testid^="team-remove-"]').first().click();
await admin.waitForSelector(`[data-testid="team-${PARENT}"]`, { state: 'detached', timeout: 20000 }).catch(() => {});
check('the admin removes them', (await count(admin, `[data-testid="team-${PARENT}"]`)) === 0);

// ------------------------------------------------------------ 5. closed again

const again = await manager.request.get(`${BASE}/en/admin/bookshop`, { failOnStatusCode: false, maxRedirects: 0 });
check('the Bookstore office screen is closed to them again', again.status() === 403, String(again.status()));

await finish();
