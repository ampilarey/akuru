/**
 * Can each kind of person find their way, without reading a wall of links?
 *
 * Until 2026-09-24 every Inertia screen carried the same 109 links in one
 * wrapping strip, for everybody, and a link the person could not open still
 * showed and answered 403 (docs/APPSHELL_NAV_IA.md, KNOWN_ISSUES P3 #11). The
 * shell now renders a short primary bar for the person's roles and a *More*
 * menu of labelled groups, built on the server from each route's own guard.
 *
 * This walk reads the shell as three people and asks the proposal's own
 * acceptance questions:
 *
 *   1. a teacher finds **Today** in the bar, opens it, and the bar is short;
 *   2. the office finds **Years** and **Exams** in the bar, and Payroll under
 *      More rather than in the strip;
 *   3. a parent finds **Fees** in the bar and is shown no staff screen at all —
 *      the menu names nothing that would refuse them.
 *
 * Read-only.
 *
 *   node scripts/smoke/nav.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_TEACHER, SMOKE_PARENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
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

async function signIn(email) {
    const context = await browser.newContext();
    // Sixty seconds, not thirty: staging behind Cloudflare stalled past thirty on two page loads in one run (STATUS §5fz).
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
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

// The primary bar is the `<nav>` in the header; the More menu is the region it
// controls. Both are read by their accessible names, not by position.
const bar = (page) => page.locator('header nav');
const barLabels = async (page) => (await bar(page).locator('a').allInnerTexts()).map((label) => label.trim()).filter(Boolean);
const menuLabels = async (page) => (await page.locator('#app-shell-more a').allInnerTexts()).map((label) => label.trim()).filter(Boolean);
const menuHrefs = async (page) => page.locator('#app-shell-more a').evaluateAll((as) => as.map((a) => a.getAttribute('href') || ''));

async function openMore(page) {
    const more = bar(page).locator('button[aria-controls="app-shell-more"]');
    if (!(await more.count())) {
        return false;
    }
    await more.click();
    await page.locator('#app-shell-more').waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});

    return (await page.locator('#app-shell-more').count()) > 0;
}

// -------------------------------------------------------------- the teacher

const teacher = await signIn(TEACHER);
check('the teacher signs in', !teacher.url().includes('/login'), teacher.url());
await teacher.goto(`${BASE}/en/portal/teacher`, { waitUntil: 'networkidle' });

const teacherBar = await barLabels(teacher);
check('the teacher\'s bar is short — one row, not eleven', teacherBar.length <= 12, `${teacherBar.length} links: ${teacherBar.join(' · ')}`);
check('and Today is in it', teacherBar.includes('Today'), teacherBar.join(' · '));
check('the office-only Years is not', !teacherBar.includes('Years') && !(await openMore(teacher) && (await menuLabels(teacher)).includes('Years')), (await menuLabels(teacher)).join(' · ').slice(0, 160));
await teacher.keyboard.press('Escape');

await bar(teacher).locator('a', { hasText: /^Today$/ }).first().click();
await teacher.waitForURL(/\/academics\/registers\/today/, { timeout: 15000 }).catch(() => {});
check('clicking Today lands on today\'s registers', /\/academics\/registers\/today/.test(teacher.url()), teacher.url().replace(BASE, ''));
check('and the bar says which link is the current one', (await bar(teacher).locator('a[aria-current="page"]').allInnerTexts()).join('') === 'Today', (await bar(teacher).locator('a[aria-current="page"]').allInnerTexts()).join(' · ') || 'no aria-current');

// --------------------------------------------------------------- the office

const staff = await signIn(STAFF);
await staff.goto(`${BASE}/en/academics/years`, { waitUntil: 'networkidle' });
const staffBar = await barLabels(staff);
check('the office finds Years and Exams in the bar', staffBar.includes('Years') && staffBar.includes('Exams'), staffBar.join(' · '));
check('and does not have to scan a wall of links', staffBar.length <= 12, `${staffBar.length} links`);

const opened = await openMore(staff);
const staffMenu = opened ? await menuLabels(staff) : [];
check('More opens the rest, grouped', opened && staffMenu.length > 40 && (await staff.locator('#app-shell-more h2').allInnerTexts()).length >= 6, opened ? `${staffMenu.length} links under ${(await staff.locator('#app-shell-more h2').allInnerTexts()).join(' / ')}` : 'no More button');
check('Payroll is under More, not in the bar', staffMenu.includes('Payroll') && !staffBar.includes('Payroll'), staffMenu.includes('Payroll') ? '' : 'Payroll not in the menu');

// A menu left open across a page change is a menu the person closes twice.
await staff.locator('#app-shell-more a', { hasText: /^Rooms$/ }).first().click();
await staff.waitForURL(/\/academics\/rooms/, { timeout: 15000 }).catch(() => {});
check('choosing a screen closes the menu', (await staff.locator('#app-shell-more').count()) === 0 && /\/academics\/rooms/.test(staff.url()), staff.url().replace(BASE, ''));

// --------------------------------------------------------------- the parent

const parent = await signIn(PARENT);
await parent.goto(`${BASE}/en/portal/home`, { waitUntil: 'networkidle' });
const parentBar = await barLabels(parent);
check('the parent finds Fees and Children in the bar', parentBar.includes('Fees') && parentBar.includes('Children'), parentBar.join(' · '));

const parentOpened = await openMore(parent);
const parentHrefs = parentOpened ? await menuHrefs(parent) : [];
// Staff screens start with a staff prefix. `/academics/requests` is the one
// exception by design: the same screen takes a family's requests and a staff
// member's leave, and admits whoever may submit or review.
const staffOnly = parentHrefs.filter((href) => /^\/(hr|finance|exams|academics|people|catalog|circulation|admin)(\/|$)/.test(href) && href !== '/academics/requests');
check('and is shown no staff screen at all', parentOpened && staffOnly.length === 0, staffOnly.length ? staffOnly.slice(0, 5).join(' ') : `${parentHrefs.length} links, all the family's`);

// The proposal's point: nothing in the menu refuses the person it is shown to.
let refused = [];
for (const href of parentHrefs) {
    const response = await parent.request.get(new URL(href, BASE).href, { maxRedirects: 0 }).catch(() => null);
    if (response && response.status() === 403) {
        refused.push(href);
    }
}
check('every link the parent is shown lets them in', parentHrefs.length > 0 && refused.length === 0, refused.length ? `403: ${refused.join(' ')}` : `${parentHrefs.length} links opened`);

// -------------------------------------------------------------------- report

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
