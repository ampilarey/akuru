/**
 * The admin panel, walked as the two people who run it (the admin-panel
 * audit, STATUS §5hs; the educational admin's permission set, STATUS §5ie).
 *
 * As the seeded educational admin (`admin@`): the School's two admin landing
 * pages open, every Institute screen — the website, instructors, prayer
 * times, the shops, the library office, the system — answers 403, and from an
 * Inertia School screen the More menu reaches the whole School and nothing of
 * the Institute. As the seeded system admin (`superadmin@`): every Institute
 * landing page opens with its heading, Users, Settings and the OTP log open,
 * the More menu reaches the whole Institute, the four listings that had no
 * CSV have one, and three writes go through: a CMS page is created and
 * deleted, an operations checklist item is ticked and unticked, and the
 * translation editor opens with rows.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/admin.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_SUPER_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
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
const text = async (page) => (await page.innerText('body').catch(() => '')).replace(/\s+/g, ' ');
const csvOf = async (page, path) => {
    const response = await page.request.get(`${BASE}${path}`);
    return { status: response.status(), text: await response.text(), type: response.headers()['content-type'] ?? '' };
};
const statusOf = async (page, path) => (await page.request.get(`${BASE}${path}`, { maxRedirects: 0 })).status();

// The Institute's landing pages: the system admin's alone.
const instituteLandings = [
    ['/en/admin/instructors', 'Instructors'],
    ['/en/admin/public-site/pages', 'Manage Pages'],
    ['/en/admin/public-site/courses', 'Manage Courses'],
    ['/en/admin/public-site/courses/deleted', 'eleted'],
    ['/en/admin/public-site/research', 'esearch'],
    ['/en/admin/public-site/daily-content', 'aily'],
    ['/en/admin/public-site/daily-content/queue', 'ueue'],
    ['/en/admin/public-site/daily-subscriptions', 'ubscri'],
    ['/en/admin/public-site/leads', 'ead'],
    ['/en/admin/public-site/funnel', 'unnel'],
    ['/en/admin/prayer-times/islands', 'sland'],
    ['/en/admin/prayer-times/groups', 'ecipient groups'],
    ['/en/admin/prayer-times/broadcasts', 'roadcast'],
    ['/en/admin/prayer-times/import', 'mport'],
    ['/en/admin/operations', 'hecklist'],
    ['/en/admin/operations/features', 'alkthrough'],
    ['/en/admin/translations', 'ranslation'],
    ['/en/admin/commerce', 'ommerce'],
    ['/en/admin/library', 'ibrary'],
    ['/en/admin/library/reading-alerts', 'lert'],
    ['/en/admin/pronunciation', 'ronunciation'],
    ['/en/admin/bookshop', 'ookstore'],
    ['/en/admin/users', 'User Management'],
    ['/en/admin/users/otp-abuse', 'OTP'],
    ['/en/admin/settings', 'Settings'],
];
// The School's: the educational admin's (and the dean's).
const schoolLandings = [
    ['/en/admin/enrollments', 'Enrol'],
    ['/en/admin/enrollments/payments', 'Payment'],
];

const landingsOpen = async (page, landings) => {
    const failed = [];
    for (const [path, word] of landings) {
        const response = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
        const body = await text(page);
        if (!response || response.status() !== 200 || !body.includes(word) || body.trim() === '') {
            failed.push(`${path}→${response?.status()}${body.includes(word) ? '' : ' (no "' + word + '")'}`);
        }
    }
    return failed;
};

// ------------------------------------------------------------ 1. the educational admin: the School, and none of the Institute

const office = await signIn(ADMIN);
check('the educational admin lands on the School office', /\/school$/.test(office.url()), office.url().replace(BASE, ''));
const schoolFailed = await landingsOpen(office, schoolLandings);
check(`the School's ${schoolLandings.length} admin landing pages open for the educational admin`, schoolFailed.length === 0, schoolFailed.join(', '));

// A decision records who made it (STATUS §5ih): suspend and reinstate a live
// enrolment and read the stamp on the page.
await office.goto(`${BASE}/en/admin/enrollments?status=active`, { waitUntil: 'networkidle' });
const enrolmentHref = await office.locator('a[href*="/admin/enrollments/"]:not([href$="/payments"]):not([href*="export"])').first().getAttribute('href').catch(() => null);
if (enrolmentHref) {
    await office.goto(enrolmentHref.startsWith('http') ? enrolmentHref : `${BASE}${enrolmentHref}`, { waitUntil: 'networkidle' });
    const suspendForm = office.locator('form[action$="/suspend"]');
    if (await suspendForm.count()) {
        office.once('dialog', (d) => d.accept());
        await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), suspendForm.locator('button').click()]);
        const stamped = (await office.locator('[data-testid="last-decision"]').textContent().catch(() => '')) || '';
        check('suspending an enrolment records who did it and when', /Suspended by .+ on \d{2} \w{3} \d{4}/.test(stamped), stamped.trim().slice(0, 80));
        office.once('dialog', (d) => d.accept());
        await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.locator('form[action$="/reinstate"] button').click()]);
        const restamped = (await office.locator('[data-testid="last-decision"]').textContent().catch(() => '')) || '';
        check('and reinstating it overwrites the stamp', /Reinstated by .+ on/.test(restamped), restamped.trim().slice(0, 80));
    } else {
        check('suspending an enrolment records who did it and when', false, 'no live enrolment to suspend on ' + enrolmentHref);
    }
} else {
    check('suspending an enrolment records who did it and when', false, 'no enrolment listed');
}
const admitted = [];
for (const [path] of instituteLandings) {
    const status = await statusOf(office, path);
    if (status !== 403) {
        admitted.push(`${path}→${status}`);
    }
}
check(`every one of the Institute's ${instituteLandings.length} screens refuses the educational admin (403)`, admitted.length === 0, admitted.join(', '));
const csvRefused = [];
for (const path of ['/en/admin/instructors/export', '/en/admin/public-site/pages/export', '/en/admin/prayer-times/groups/export', '/en/admin/commerce/gift-card-orders/export', '/en/admin/bookshop/vendors/export']) {
    const status = await statusOf(office, path);
    if (status !== 403) {
        csvRefused.push(`${path}→${status}`);
    }
}
check('and so do the CSVs behind them', csvRefused.length === 0, csvRefused.join(', '));

// From an Inertia School screen, the More menu is the School's (STATUS §5id).
await office.goto(`${BASE}/en/school`, { waitUntil: 'networkidle' });
await office.goto(`${BASE}/en/academics/years`, { waitUntil: 'networkidle' });
await office.click('button[aria-controls="app-shell-more"]');
await settle(office, '#app-shell-more');
const hardLinks = await office.locator('#app-shell-more a[data-nav-hard]').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
const menuLinks = await office.locator('#app-shell-more a').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
const wanted = ['/admin/enrollments', '/academics/years', '/people/students', '/exams/schedule', '/finance/invoices', '/hr/payroll', '/announcements'];
const missing = wanted.filter((href) => !menuLinks.some((h) => h && h.endsWith(href)));
check('from an Inertia School screen, the educational admin\'s More menu reaches the whole School', missing.length === 0, missing.join(', '));
check('its Blade entries are plain links (a full page load), the Inertia ones are not', hardLinks.some((h) => h.endsWith('/admin/enrollments')) && hardLinks.some((h) => h.endsWith('/announcements')) && !hardLinks.some((h) => h.endsWith('/academics/years')), hardLinks.join(', '));
check('and nothing of the Institute is offered: no Website CMS, Commerce, Users or Settings', !menuLinks.some((h) => h && /\/admin\/(public-site\/pages|commerce|users|settings|operations)$/.test(h)));
await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.click('#app-shell-more a[data-nav-hard][href$="/admin/enrollments"]')]);
check('clicking a Blade entry lands on the Blade screen', /\/admin\/enrollments$/.test(office.url()) && (await text(office)).includes('Enrol'), office.url().replace(BASE, ''));
// The role as people read it (STATUS §5if): the Blade user menu names the job.
// textContent, not innerText: the user menu is closed (hidden) until clicked.
const officeMenu = (await office.locator('nav').first().textContent().catch(() => '')) || '';
check('the Blade user menu calls the educational admin by that name, not "Admin"', officeMenu.includes('Educational admin') && !/\bHeadmaster\b|Super Admin/.test(officeMenu), officeMenu.replace(/\s+/g, ' ').slice(0, 120));

// ------------------------------------------------------------ 2. the system admin: the whole Institute

const su = await signIn(SUPER);
check('the system admin lands on the Institute', /\/admin$/.test(su.url()), su.url().replace(BASE, ''));
const instituteFailed = await landingsOpen(su, instituteLandings);
check(`all ${instituteLandings.length} Institute landing pages open for the system admin, each with its heading`, instituteFailed.length === 0, instituteFailed.join(', '));
const paidToday = await su.goto(`${BASE}/en/admin/enrollments/payments`, { waitUntil: 'networkidle' });
check('and the enrolment payments list, which the Institute home’s "paid today" tile opens', paidToday?.status() === 200);
// The users screen names the roles as the owner does (STATUS §5if).
// The list is newest first, so the seeded logins are filtered to by role.
await su.goto(`${BASE}/en/admin/users`, { waitUntil: 'networkidle' });
const badges = await su.locator('[data-testid="role-badge"]').allTextContents();
const filterOptions = await su.locator('select[name="role"] option').allTextContents();
await su.goto(`${BASE}/en/admin/users?role=admin`, { waitUntil: 'networkidle' });
const adminBadges = await su.locator('[data-testid="role-badge"]').allTextContents();
await su.goto(`${BASE}/en/admin/users?role=headmaster`, { waitUntil: 'networkidle' });
const deanBadges = await su.locator('[data-testid="role-badge"]').allTextContents();
check('the users screen badges read System admin, Educational admin, Dean — never "Super Admin"', badges.some((b) => b.trim() === 'System admin') && adminBadges.length > 0 && adminBadges.every((b) => b.trim() === 'Educational admin') && deanBadges.length > 0 && deanBadges.every((b) => b.trim() === 'Dean') && !badges.some((b) => /Super Admin|Headmaster/.test(b)), [...new Set([...badges, ...adminBadges, ...deanBadges].map((b) => b.trim()))].join(', '));
check('and its filter offers every role by that name', filterOptions.map((o) => o.trim()).includes('Dean') && filterOptions.map((o) => o.trim()).includes('Bookstore admin'), filterOptions.map((o) => o.trim()).join(', '));

// The role and access screen (STATUS §5ig): the seeded parent is made a
// supervisor and back, deactivated and reactivated, from the panel.
await su.goto(`${BASE}/en/admin/users?role=parent`, { waitUntil: 'networkidle' });
const parentRow = su.locator('tr', { hasText: 'parent@akuru.edu.mv' }).first();
await Promise.all([su.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), parentRow.locator('[data-testid="user-roles-link"]').click()]);
await settle(su, '[data-testid="roles-form"]');
const rolesBefore = (await su.locator('[data-testid="roles-current"]').textContent()) || '';
check('the users list opens a person’s Roles & access screen', /\/admin\/users\/\d+\/roles$/.test(su.url()) && rolesBefore.includes('Parent'), `${su.url().replace(BASE, '')} ${rolesBefore}`);
// The seeded parent may hold side roles from other walks, so the check is
// "Supervisor joins what was there, and leaves again".
await su.locator('[data-testid="role-supervisor"]').check();
await su.click('[data-testid="roles-save"]');
await su.waitForFunction((was) => (document.querySelector('[data-testid="roles-current"]')?.textContent || '') !== was, rolesBefore, { timeout: 15000 }).catch(() => {});
const rolesAfter = (await su.locator('[data-testid="roles-current"]').textContent()) || '';
check('ticking Supervisor and saving makes them a supervisor too', rolesAfter.includes('Supervisor') && rolesAfter.includes('Parent') && rolesAfter !== rolesBefore, rolesAfter);
await su.locator('[data-testid="role-supervisor"]').uncheck();
await su.click('[data-testid="roles-save"]');
await su.waitForFunction((was) => (document.querySelector('[data-testid="roles-current"]')?.textContent || '') === was, rolesBefore, { timeout: 15000 }).catch(() => {});
check('and unticking it takes the role away again', (await su.locator('[data-testid="roles-current"]').textContent()) === rolesBefore, await su.locator('[data-testid="roles-current"]').textContent());
await su.click('[data-testid="access-toggle"]');
await su.waitForFunction(() => /Deactivated/.test(document.querySelector('[data-testid="access-state"]')?.textContent || ''), null, { timeout: 15000 }).catch(() => {});
check('Deactivate turns the account off', /Deactivated/.test(await su.locator('[data-testid="access-state"]').textContent()));
await su.click('[data-testid="access-toggle"]');
await su.waitForFunction(() => /Can sign in/.test(document.querySelector('[data-testid="access-state"]')?.textContent || ''), null, { timeout: 15000 }).catch(() => {});
check('and Reactivate turns it back on', /Can sign in/.test(await su.locator('[data-testid="access-state"]').textContent()));
// The system admin's own screen keeps their role and their access.
await su.goto(`${BASE}/en/admin/users?role=super_admin`, { waitUntil: 'networkidle' });
const selfRow = su.locator('tr', { hasText: SUPER }).first();
await Promise.all([su.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), selfRow.locator('[data-testid="user-roles-link"]').click()]);
await settle(su, '[data-testid="roles-form"]');
check('on their own screen the System admin role is locked and there is no deactivate button', (await su.locator('[data-testid="role-super_admin"]').isDisabled()) && (await count(su, '[data-testid="access-toggle"]')) === 0);

await su.goto(`${BASE}/en/admin`, { waitUntil: 'networkidle' });
await su.goto(`${BASE}/en/admin/operations`, { waitUntil: 'networkidle' });
await su.click('button[aria-controls="app-shell-more"]');
await settle(su, '#app-shell-more');
const suLinks = await su.locator('#app-shell-more a').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
const suWanted = ['/admin/instructors', '/admin/public-site/pages', '/admin/prayer-times/islands', '/admin/commerce', '/admin/library', '/admin/pronunciation', '/admin/bookshop', '/admin/translations', '/admin/operations/features', '/admin/users', '/admin/settings'];
const suMissing = suWanted.filter((href) => !suLinks.some((h) => h && h.endsWith(href)));
check('the system admin\'s More menu reaches the whole Institute, and not the School\'s admissions', suMissing.length === 0 && !suLinks.some((h) => h && h.endsWith('/admin/enrollments')), suMissing.join(', '));

// ------------------------------------------------------------ 3. the four CSVs

for (const [path, head] of [
    ['/en/admin/instructors/export', 'id,name,email'],
    ['/en/admin/prayer-times/groups/export', 'id,name,members'],
    ['/en/admin/public-site/pages/export', 'id,title,slug'],
    ['/en/admin/public-site/courses/export', 'id,title,slug,category'],
]) {
    const csv = await csvOf(su, path);
    check(`CSV ${path.replace('/en/admin/', '')}`, csv.status === 200 && csv.type.includes('text/csv') && csv.text.startsWith(head), `${csv.status} ${csv.text.split('\n')[0].slice(0, 60)}`);
}
for (const [path] of [['/en/admin/instructors'], ['/en/admin/prayer-times/groups'], ['/en/admin/public-site/pages'], ['/en/admin/public-site/courses']]) {
    await su.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    check(`the Export CSV link is on ${path.replace('/en/admin/', '')}`, (await count(su, '[data-testid="export-csv"]')) === 1);
}

// ------------------------------------------------------------ 4. three writes

const slug = `smoke-audit-${Date.now()}`;
await su.goto(`${BASE}/en/admin/public-site/pages/create`, { waitUntil: 'networkidle' });
await su.fill('input[name="title"]', 'SMOKE audit page');
await su.fill('input[name="slug"]', slug);
await su.fill('textarea[name="body"]', '<p>Hello</p><script>alert(1)</script>');
// Scoped to the page form: the Blade nav carries a sign-out form with its own submit button.
await Promise.all([su.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), su.click('form[action*="public-site/pages"] button[type=submit]')]);
await su.goto(`${BASE}/en/admin/public-site/pages`, { waitUntil: 'networkidle' });
check('a CMS page is created from the form', (await text(su)).includes('SMOKE audit page'));
const row = su.locator('tr', { hasText: 'SMOKE audit page' }).first();
su.once('dialog', (d) => d.accept());
await Promise.all([su.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), row.locator('form button[type=submit], form button').last().click()]);
check('and deleted again', !(await text(su)).includes('SMOKE audit page'));

await su.goto(`${BASE}/en/admin/operations`, { waitUntil: 'networkidle' });
const firstBox = su.locator('input[type=checkbox]').first();
const before = await firstBox.isChecked();
await firstBox.click();
await su.waitForFunction((was) => document.querySelector('input[type=checkbox]')?.checked !== was, before, { timeout: 20000 }).catch(() => {});
const after = await su.locator('input[type=checkbox]').first().isChecked();
await su.locator('input[type=checkbox]').first().click();
await su.waitForFunction((was) => document.querySelector('input[type=checkbox]')?.checked === was, before, { timeout: 20000 }).catch(() => {});
check('an operations checklist item is ticked and unticked', after !== before && (await su.locator('input[type=checkbox]').first().isChecked()) === before);

await su.goto(`${BASE}/en/admin/translations?q=ops_checklist`, { waitUntil: 'networkidle' });
await settle(su, 'textarea');
const translationRows = await count(su, 'textarea');
check('the translation editor opens with rows to correct', translationRows > 0, `${translationRows} rows`);

// Admin-panel audit finding 13 (STATUS §5il): the button clears, and where
// the configuration was cached it is rebuilt rather than left off.
await su.goto(`${BASE}/en/admin/settings`, { waitUntil: 'networkidle' });
await Promise.all([su.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), su.click('form[action*="clear-cache"] button[type=submit]')]);
const cacheFlash = await text(su);
check('Clear all caches reports what it did, and the screen is still there', /All caches cleared/.test(cacheFlash) && cacheFlash.includes('Cache Management'));

await finish();
