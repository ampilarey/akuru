/**
 * A workspace's menu is only its own (docs/SIGN_IN_PLAN.md, ID1).
 *
 * The owner, 2026-09-28, with EduPage's drawer beside it: "Logged in with a
 * vendor account but I see educational items also." Until ID1 every
 * workspace's More menu ended with the same `Mine` group — Home (the family
 * portal, which showed a vendor an empty "Student Dashboard"), Messages,
 * Notices, Forms — and a parent's menu carried their own Learn and Schedule
 * beside their children's screens. This walk signs in on a phone as four
 * seeded people and reads each More panel as they would:
 *
 *   1. the vendor: their shop, a Home that is the shop, the Personal group
 *      and nothing of the school; the family portal sends them back to the
 *      shop; the Digital Library opens as its own page, not in a modal;
 *   2. the parent: Communication, Education, Evaluation, Other, Personal —
 *      the children, fees and pick-up, and none of their own learning;
 *   3. the teacher: the School's day loop, teaching, communication and their
 *      own record, and no Learn;
 *   4. the pupil: their own courses under Education, no parent-only screen;
 *   5. My learning (ID2a): a website learner lands inside the app on their
 *      own courses, the unpaid one listed as waiting; a parent who enrolled
 *      switches from Family to My learning and back;
 *   6. My account (ID2b): a person with no other workspace lands inside the
 *      app, sees their child awaiting the office and the enrolment they
 *      made, opens My enrolments and its receipt; the old course portal's
 *      addresses land in the app.
 *
 * Read-only: it opens menus and pages and changes nothing.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/identity.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PARENT, SMOKE_TEACHER,
 * SMOKE_STUDENT, SMOKE_LEARNER, SMOKE_PARENT_LEARNER, SMOKE_ACCOUNT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM, SMOKE_SHOTS (a folder for the More panels' screenshots).
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const PEOPLE = {
    vendor: process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv',
    parent: process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv',
    teacher: process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv',
    student: process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv',
};
const SCHOOL_TALK = ['/portal/messages', '/portal/announcements', '/portal/forms'];

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
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
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

const path = (href) => (href || '').replace(BASE, '').replace(/^\/(en|dv|ar)(?=\/|$)/, '') || '/';

// Opens the phone's More panel and reads it: the group headings (textContent,
// since CSS uppercases them), every link's label and path, and Home.
async function readMore(page, shot) {
    await page.click('button[aria-controls="app-shell-more"]');
    await page.waitForSelector('#app-shell-more', { state: 'visible' });
    const groups = (await page.locator('#app-shell-more section h2').allTextContents()).map((t) => t.trim());
    const links = await page.locator('#app-shell-more section a').evaluateAll((els) => els.map((el) => [el.textContent.trim(), el.getAttribute('href') || '', el.hasAttribute('data-nav-hard')]));
    const home = await page.locator('#app-shell-more [data-testid="workspace-home"]').getAttribute('href').catch(() => null);
    if (process.env.SMOKE_SHOTS) {
        await page.screenshot({ path: `${process.env.SMOKE_SHOTS}/identity-${shot}.png`, fullPage: true });
    }

    return { groups, labels: links.map(([label]) => label), paths: links.map(([, href]) => path(href)), hard: links.filter(([, , hard]) => hard).map(([, href]) => path(href)), home: path(home) };
}

// ------------------------------------------------------------ 1. the vendor
const vendor = await signIn(PEOPLE.vendor);
check('the vendor lands on their shop', /\/vendor(\/apply)?$/.test(vendor.url()), path(vendor.url()));
let menu = await readMore(vendor, 'vendor');
check('their More panel holds the Personal group and nothing else', menu.groups.join(',') === 'Personal', menu.groups.join(' | '));
check('with nothing of the school: no Messages, Notices, Forms, family portal or courses', !menu.paths.some((p) => [...SCHOOL_TALK, '/portal/home', '/learn', '/portal/homework'].includes(p)), menu.labels.join(', '));
check('its Home is the shop', menu.home === '/vendor', menu.home);
check('the Library, the Bookstore and the wallet are there, each a full page load', ['/library', '/shop', '/my-wallet'].every((p) => menu.hard.includes(p)), menu.hard.join(', '));
await Promise.all([vendor.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), vendor.click('#app-shell-more a[href$="/library"]')]);
check('Digital Library opens as its own page, not in a modal over the shop', path(vendor.url()) === '/library' && (await vendor.locator('iframe').count()) === 0, path(vendor.url()));
await vendor.goto(`${BASE}/en/portal/home`, { waitUntil: 'networkidle' });
check('the family portal sends a vendor back to their shop', /\/vendor(\/apply)?$/.test(vendor.url()) && !(await vendor.locator('body').innerText()).includes('Student Dashboard'), path(vendor.url()));

// ------------------------------------------------------------ 2. the parent
const parent = await signIn(PEOPLE.parent);
check('the parent lands on the family portal', /\/portal\/home$/.test(parent.url()), path(parent.url()));
menu = await readMore(parent, 'parent');
check('their More panel reads Communication, Education, Evaluation, Other, Personal', menu.groups.join(',') === 'Communication,Education,Evaluation,Other,Personal', menu.groups.join(' | '));
check('with the children, their fees, pick-up and the noticeboard', ['/portal/children', '/portal/invoices', '/portal/pickup', '/portal/announcements'].every((p) => menu.paths.includes(p)), menu.labels.join(', '));
check('and none of their own learning or the staff’s screens', !menu.paths.some((p) => ['/learn', '/learn/schedule', '/teach/schedule', '/portal/staff-check-in'].includes(p)), menu.paths.filter((p) => /learn|teach|staff/.test(p)).join(', ') || 'none');
check('Home is the family portal', menu.home === '/portal/home', menu.home);

// ------------------------------------------------------------ 3. the teacher
const teacher = await signIn(PEOPLE.teacher);
check('the teacher lands on their day', /\/portal\/teacher$/.test(teacher.url()), path(teacher.url()));
menu = await readMore(teacher, 'teacher');
check('their More panel is the School’s: Day loop, Teaching, Communication, My work, Personal', ['Day loop', 'Teaching', 'Communication', 'My work'].every((g) => menu.groups.includes(g)) && menu.groups.at(-1) === 'Personal', menu.groups.join(' | '));
check('with teaching and the school’s messages, and no Learn of their own', menu.paths.includes('/teach/schedule') && menu.paths.includes('/portal/messages') && !menu.paths.includes('/learn') && !menu.paths.includes('/portal/children'), menu.paths.filter((p) => /learn|teach|children/.test(p)).join(', '));
check('Home is their day', menu.home === '/portal/teacher', menu.home);

// ------------------------------------------------------------ 4. the pupil
const student = await signIn(PEOPLE.student);
check('the pupil lands on their portal', /\/portal\/home$/.test(student.url()), path(student.url()));
menu = await readMore(student, 'student');
check('their More panel reads Communication, Education, Evaluation, Other, Personal', menu.groups.join(',') === 'Communication,Education,Evaluation,Other,Personal', menu.groups.join(' | '));
check('with their own courses under Education, and no parent-only screen', menu.paths.includes('/learn') && menu.paths.includes('/learn/schedule') && !menu.paths.some((p) => ['/portal/children', '/portal/pickup', '/portal/movements'].includes(p)), menu.labels.join(', '));

// ------------------------------------------------------ 5. My learning (ID2a)
// SmokeMarkerSeeder::learnerIdentities plants both: an adult who registered
// on the website (no role) and a parent who enrolled in a course themselves.
const LEARNER = process.env.SMOKE_LEARNER ?? 'smoke-learner@akuru.edu.mv';
const PARENT_LEARNER = process.env.SMOKE_PARENT_LEARNER ?? 'smoke-parent-learner@akuru.edu.mv';
const switcher = async (page) => page.locator('header [data-testid="workspace-switcher"]').count();

const learner = await signIn(LEARNER);
check('a website learner lands on My learning, inside the app — not on the website', path(learner.url()) === '/learn' && (await learner.locator('header [data-testid="shell-home"]').count()) === 1, path(learner.url()));
const learnerText = (await learner.locator('main').innerText()).replace(/\s+/g, ' ');
check('with their course under way, and the one waiting on its payment', learnerText.includes('SMOKE-Learner-Course') && /SMOKE-Learner-Waiting · Awaiting payment/.test(learnerText), learnerText.slice(0, 200));
check('one workspace, so no switcher', (await switcher(learner)) === 0);
menu = await readMore(learner, 'learner');
check('their More panel: Education and Personal, their own courses and enrolments', menu.groups.join(',') === 'Education,Personal' && menu.paths.includes('/learn') && menu.paths.includes('/my-enrollments') && !menu.paths.some((p) => [...SCHOOL_TALK, '/portal/children', '/portal/homework'].includes(p)), `${menu.groups.join(' | ')} — ${menu.labels.join(', ')}`);
await learner.goto(`${BASE}/en/portal/home`, { waitUntil: 'networkidle' });
check('the family portal sends them back to My learning', path(learner.url()) === '/learn', path(learner.url()));

const parentLearner = await signIn(PARENT_LEARNER);
check('a parent who enrolled themselves lands on Family', /\/portal\/home$/.test(parentLearner.url()), path(parentLearner.url()));
await parentLearner.click('header [data-testid="workspace-switcher"]');
await parentLearner.waitForSelector('#app-shell-workspaces', { state: 'visible' });
const offered = await parentLearner.locator('#app-shell-workspaces [data-testid^="workspace-"]').evaluateAll((els) => els.map((el) => el.textContent.trim()));
check('the switcher offers Family and My learning', offered.join(',') === 'Family,My learning', offered.join(', '));
await Promise.all([parentLearner.waitForURL(/\/learn$/, { timeout: 15000 }).catch(() => {}), parentLearner.click('#app-shell-workspaces [data-testid="workspace-learner"]')]);
await parentLearner.waitForLoadState('networkidle');
check('switching to My learning shows their own course', path(parentLearner.url()) === '/learn' && (await parentLearner.locator('main').innerText()).includes('SMOKE-Learner-Course'), path(parentLearner.url()));
menu = await readMore(parentLearner, 'parent-learner');
check('and My learning’s menu carries none of their children’s screens', menu.groups.join(',') === 'Education,Personal' && !menu.paths.some((p) => ['/portal/children', '/portal/homework', '/portal/pickup'].includes(p)), menu.labels.join(', '));

// ------------------------------------------------------ 6. My account (ID2b)
// SmokeMarkerSeeder::accountHolder plants a person with no role and no course
// of their own: a child registered on the website and not yet checked, and a
// paid enrolment for the child. Until ID2b they landed on a Blade page in the
// website's layout, and the old course portal's pages were the same.
const ACCOUNT = process.env.SMOKE_ACCOUNT ?? 'smoke-account@akuru.edu.mv';
const account = await signIn(ACCOUNT);
check('a person with no other workspace lands on My account, inside the app', path(account.url()) === '/my-account' && (await account.locator('header [data-testid="shell-home"]').count()) === 1, path(account.url()));
const accountText = (await account.locator('main').innerText()).replace(/\s+/g, ' ');
check('asked to choose a password, told their child awaits the office, their enrolment listed', (await account.locator('[data-testid="set-password-notice"]').count()) === 1 && /SMOKE-AccountChild Ibrahim · awaiting the office/.test(accountText) && accountText.includes('SMOKE-Learner-Waiting'), accountText.slice(0, 240));
check('nothing of the website: no courses open for enrolment', !/Open for enrollment/i.test(accountText));
const doors = await account.locator('[data-testid="account-doors"] a').evaluateAll((els) => els.map((el) => el.getAttribute('href') || ''));
check('its doors are its menu: courses, enrolments, profile, the Library, the Bookstore, the wallet', ['/my-enrollments', '/learn/catalog', '/profile', '/library', '/shop', '/my-wallet'].every((p) => doors.map(path).includes(p)), doors.map(path).join(', '));
menu = await readMore(account, 'account');
check('their More panel: Education and Personal, and Home is My account', menu.groups.join(',') === 'Education,Personal' && menu.paths.includes('/my-enrollments') && menu.home === '/my-account', `${menu.groups.join(' | ')} — ${menu.home}`);
await account.click('#app-shell-more a[href$="/my-enrollments"]');
await account.waitForURL(/\/my-enrollments$/, { timeout: 15000 }).catch(() => {});
await account.waitForLoadState('networkidle');
const enrolText = (await account.locator('main').innerText()).replace(/\s+/g, ' ');
check('My enrolments opens in the app, with the child’s enrolment, paid, and its receipt', path(account.url()) === '/my-enrollments' && enrolText.includes('SMOKE-AccountChild Ibrahim') && (await account.locator('[data-testid="payment-receipt"]').count()) >= 1 && (await account.locator('[data-testid="export-csv"]').count()) === 1, enrolText.slice(0, 200));
const receiptHref = await account.locator('[data-testid="payment-receipt"]').first().getAttribute('href').catch(() => null);
if (receiptHref) {
    await account.goto(`${BASE}${receiptHref.replace(/^https?:\/\/[^/]+/, '')}`, { waitUntil: 'networkidle' });
    check('the receipt opens', (await account.locator('body').innerText()).includes('SMOKE-Learner-Waiting'), path(account.url()));
}
for (const [from, to] of [['/portal/dashboard', '/my-account'], ['/portal/payments', '/my-enrollments']]) {
    await account.goto(`${BASE}/en${from}`, { waitUntil: 'networkidle' });
    check(`the old course portal's ${from} lands in the app, on ${to}`, path(account.url()) === to, path(account.url()));
}

await finish();
