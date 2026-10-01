/**
 * Logging out of the shell (STATUS §5mw). The owner's screenshot, 2026-10-01:
 * Log out from the vendor shell drew the public home page inside a modal over
 * the shell. The shell posts logout as an Inertia request; the home page is
 * Blade; the fix answers with a full-page location. This walk signs in, logs
 * out from the drawer, and checks the browser really left: the home page is
 * the document, not a frame, and the shell is gone.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/logout.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--disable-component-update', '--no-first-run', '--no-default-browser-check'],
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

// Once on a phone (the owner's case), once on a desktop: the drawer and the header differ.
for (const [label, viewport] of [['phone', { width: 390, height: 844 }], ['desktop', { width: 1400, height: 950 }]]) {
    const page = await signIn(VENDOR, viewport);
    await page.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
    check(`${label}: the vendor is in the shell`, (await page.locator('#main').count()) === 1 && /\/vendor/.test(page.url()));
    const logout = page.getByRole('button', { name: /log out/i });
    if ((await logout.count()) === 0 || !(await logout.first().isVisible())) {
        // The button lives in the account drawer: open it from the avatar/menu.
        const openers = page.locator('header button');
        for (let i = (await openers.count()) - 1; i >= 0; i--) {
            await openers.nth(i).click().catch(() => {});
            await page.waitForTimeout(200);
            if ((await logout.count()) > 0 && (await logout.first().isVisible())) break;
        }
    }
    check(`${label}: Log out is reachable`, (await logout.count()) > 0 && (await logout.first().isVisible()));
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), logout.first().click()]);
    await page.waitForTimeout(500);
    const url = page.url();
    const frames = page.frames().length;
    const shellGone = (await page.locator('#main').count()) === 0 && (await page.locator('[data-testid="section-nav"]').count()) === 0;
    const homeIsDocument = (await page.locator('body').innerText()).includes('Learn, read and grow') || /\/(en)?\/?$/.test(new URL(url).pathname);
    check(`${label}: logging out leaves the shell for the public home page as the document, not a modal`, shellGone && homeIsDocument && frames === 1, `${new URL(url).pathname}, ${frames} frame(s)`);
    const back = await page.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
    check(`${label}: and the session is really gone — the portal asks to sign in`, /\/login/.test(page.url()) || back?.status() === 401 || back?.status() === 403, new URL(page.url()).pathname);
    await page.context().close();
}

await finish();
