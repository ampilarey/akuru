/**
 * Workspaces: one shell, one job at a time (STATUS §5id, ADR-040).
 *
 * As a person with three workspaces (SMOKE_MULTI, an account holding admin,
 * parent and vendor — grant them locally with tinker, revoke after): sign-in
 * lands on the School office; the header's switcher reads "School" and lists
 * School, Family and My shop; switching to Family posts and lands on the
 * family portal, where the More menu holds the family's groups only; the
 * School's Blade screens show the School's groups and the same switcher in
 * the header, the user menu and the phone menu. As the seeded vendor (one
 * workspace): sign-in lands on the shop and no switcher is shown.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/workspaces.mjs
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

const texts = (page, selector) => page.locator(selector).allInnerTexts();
const count = (page, selector) => page.locator(selector).count();
const switcherLabel = async (page) => ((await page.locator('header [data-testid="workspace-switcher"]').textContent().catch(() => '')) || '').replace(/[▾▴]/g, '').trim();
const openSwitcher = async (page) => {
    await page.click('header [data-testid="workspace-switcher"]');
    await page.waitForSelector('#app-shell-workspaces', { state: 'visible' });
    const listed = await page.locator('#app-shell-workspaces [data-testid^="workspace-"]').evaluateAll((els) => els.map((el) => el.getAttribute('data-testid').replace('workspace-', '')));
    await page.keyboard.press('Escape');
    await page.waitForSelector('#app-shell-workspaces', { state: 'detached' });
    return listed;
};
const switchTo = async (page, key) => {
    await page.click('header [data-testid="workspace-switcher"]');
    await page.waitForSelector('#app-shell-workspaces', { state: 'visible' });
    await page.click(`#app-shell-workspaces [data-testid="workspace-${key}"]`);
};
const moreGroups = async (page) => {
    await page.click('button[aria-controls="app-shell-more"]');
    await page.waitForSelector('#app-shell-more', { state: 'visible' });
    // textContent, not innerText: the headings are uppercased by CSS.
    const groups = await page.locator('#app-shell-more h2').allTextContents();
    await page.keyboard.press('Escape');
    await page.waitForSelector('#app-shell-more', { state: 'detached' });
    return groups.map((t) => t.trim());
};

// ------------------------------------------------------------ 1. three workspaces
const person = await signIn(MULTI, { width: 1400, height: 950 }, false);
check('an educational admin who is also a parent and a vendor lands on the School office', /\/school$/.test(person.url()), person.url().replace(BASE, ''));
check('the header’s switcher reads School', (await switcherLabel(person)) === 'School', await switcherLabel(person));
let listed = await openSwitcher(person);
check('and lists School, Family and My shop', listed.join(',') === 'school,family,vendor', listed.join(','));
let groups = await moreGroups(person);
check('the School’s More menu: Admissions, the academics, teaching, the office, Personal last — nothing of the Institute', groups.includes('Admissions') && groups.includes('School year') && groups.includes('Teaching') && groups.includes('Finance') && groups.at(-1) === 'Personal' && !groups.includes('Learn') && !groups.includes('Website & content'), groups.join(' | '));

await switchTo(person, 'family');
await person.waitForURL(/\/portal\/home$/, { timeout: 15000 }).catch(() => {});
check('Family switches (a post) and lands on the family portal', /\/portal\/home$/.test(person.url()), person.url().replace(BASE, ''));
check('where the switcher reads Family', (await switcherLabel(person)) === 'Family', await switcherLabel(person));
groups = await moreGroups(person);
check('and the More menu holds the family’s groups only', groups.join(',') === 'Communication,Education,Evaluation,Other,Personal', groups.join(' | '));
await person.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the choice holds on the next page', (await switcherLabel(person)) === 'Family', await switcherLabel(person));

// Opening the School office makes the School active again.
await person.goto(`${BASE}/en/school`, { waitUntil: 'networkidle' });
check('opening /school makes the School active again', (await switcherLabel(person)) === 'School' && (await count(person, '[data-testid="today"]')) === 1, await switcherLabel(person));

// The Blade shell: the same workspace, the same switcher (the Quran progress list; the enrolment lists are Inertia since C9 slice 4).
await person.goto(`${BASE}/en/quran-progress`, { waitUntil: 'networkidle' });
const bladeSections = await person.locator('#nav-more-menu [data-nav-section]').evaluateAll((els) => els.map((el) => el.getAttribute('data-nav-section')));
check('a School Blade screen’s More menu carries the School’s groups', bladeSections.includes('panel_admissions') && bladeSections.includes('school_year') && !bladeSections.includes('panel_website'), bladeSections.join(','));
await person.click('nav [data-testid="workspace-switcher"]');
await person.waitForSelector('#nav-workspaces', { state: 'visible' });
const bladeSwitch = await person.locator('#nav-workspaces [data-testid^="workspace-"]').evaluateAll((els) => els.map((el) => el.getAttribute('data-testid').replace('workspace-', '')));
check('and the header switcher lists the three', bladeSwitch.join(',') === 'school,family,vendor', bladeSwitch.join(','));
await Promise.all([person.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), person.click('#nav-workspaces [data-testid="workspace-vendor"]')]);
check('switching to My shop from a Blade screen lands on the vendor portal', /\/vendor(\/apply)?$/.test(person.url()), person.url().replace(BASE, ''));

const phone = await signIn(MULTI, { width: 390, height: 844 }, true);
await phone.goto(`${BASE}/en/quran-progress`, { waitUntil: 'networkidle' });
await phone.click('button[aria-controls="nav-mobile-menu"]');
await phone.waitForSelector('#nav-mobile-menu', { state: 'visible' });
const phoneSwitch = await texts(phone, '#nav-mobile-menu [data-testid^="workspace-"]:not([data-testid="workspace-home"])');
check('on a phone the menu lists the workspaces by name, then the home and the groups', phoneSwitch.map((t) => t.trim()).join(' | ') === 'School | Family | My shop' && (await count(phone, '#nav-mobile-menu [data-testid="workspace-home"]')) === 1 && (await count(phone, '#nav-mobile-menu [data-nav-section="school_year"]')) === 1, phoneSwitch.join(' | '));

// ------------------------------------------------------------ 2. one workspace
const vendor = await signIn(VENDOR, { width: 1400, height: 950 }, false);
check('the seeded vendor lands on their shop', /\/vendor$/.test(vendor.url()), vendor.url().replace(BASE, ''));
check('and sees no switcher: one workspace, nothing to switch to', (await count(vendor, '[data-testid="workspace-switcher"]')) === 0);

await finish();
