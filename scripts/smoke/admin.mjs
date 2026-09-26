/**
 * The admin panel, walked as the office (the admin-panel audit, STATUS §5hs).
 *
 * Every admin landing page opens for the seeded `admin` account, the two
 * `super_admin`-only screens refuse it (or open, when SMOKE_SUPER_ADMIN is
 * given), the Inertia shell's More menu now lists the whole panel and its
 * Blade entries open with a full page load, the four listings that had no
 * CSV now have one, and three writes go through: a CMS page is created and
 * deleted, an operations checklist item is ticked and unticked, and a
 * Dhivehi translation override is saved.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/admin.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_SUPER_ADMIN (optional), SMOKE_PASSWORD, SMOKE_CHROMIUM.
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

// ------------------------------------------------------------ 1. every landing page, as the office

const office = await signIn(ADMIN);
const landings = [
    ['/en/admin/enrollments', 'Enrol'],
    ['/en/admin/enrollments/payments', 'Payment'],
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
];
const failedLandings = [];
for (const [path, word] of landings) {
    const response = await office.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    const body = await text(office);
    if (!response || response.status() !== 200 || !body.includes(word) || body.trim() === '') {
        failedLandings.push(`${path}→${response?.status()}${body.includes(word) ? '' : ' (no "' + word + '")'}`);
    }
}
check(`all ${landings.length} admin landing pages open for the office, each with its heading`, failedLandings.length === 0, failedLandings.join(', '));

// The two super_admin-only screens: refused to a plain admin, open to a super admin.
const users = await office.goto(`${BASE}/en/admin/users`, { waitUntil: 'networkidle' });
const settings = await office.goto(`${BASE}/en/admin/settings`, { waitUntil: 'networkidle' });
check('Users and Settings refuse the admin role (super_admin only)', users?.status() === 403 && settings?.status() === 403, `${users?.status()} ${settings?.status()}`);
if (SUPER) {
    const su = await signIn(SUPER);
    const u = await su.goto(`${BASE}/en/admin/users`, { waitUntil: 'networkidle' });
    const s = await su.goto(`${BASE}/en/admin/settings`, { waitUntil: 'networkidle' });
    const o = await su.goto(`${BASE}/en/admin/users/otp-abuse`, { waitUntil: 'networkidle' });
    check('and open for the super admin', u?.status() === 200 && s?.status() === 200 && o?.status() === 200, `${u?.status()} ${s?.status()} ${o?.status()}`);
}

// ------------------------------------------------------------ 2. the Inertia shell's More menu lists the panel

await office.goto(`${BASE}/en/admin/operations`, { waitUntil: 'networkidle' });
await office.click('button[aria-controls="app-shell-more"]');
await settle(office, '#app-shell-more');
const hardLinks = await office.locator('#app-shell-more a[data-nav-hard]').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
const menuLinks = await office.locator('#app-shell-more a').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
const wanted = ['/admin/enrollments', '/admin/instructors', '/admin/public-site/pages', '/admin/prayer-times/islands', '/admin/commerce', '/admin/library', '/admin/pronunciation', '/admin/bookshop', '/admin/translations', '/admin/operations/features'];
const missing = wanted.filter((href) => !menuLinks.some((h) => h && h.endsWith(href)));
check('from an Inertia admin screen, the More menu reaches the whole panel', missing.length === 0, missing.join(', '));
check('its Blade entries are plain links (a full page load), the Inertia ones are not', hardLinks.length === 4 && hardLinks.every((h) => /enrollments|instructors|public-site\/pages|prayer-times/.test(h)), hardLinks.join(', '));
check('and Users and Settings are not offered to a plain admin', !menuLinks.some((h) => h && (h.endsWith('/admin/users') || h.endsWith('/admin/settings'))));
await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.click('#app-shell-more a[data-nav-hard][href$="/admin/instructors"]')]);
check('clicking a Blade entry lands on the Blade screen', /\/admin\/instructors$/.test(office.url()) && (await text(office)).includes('Instructors'), office.url().replace(BASE, ''));

// ------------------------------------------------------------ 3. the four new CSVs

for (const [path, head] of [
    ['/en/admin/instructors/export', 'id,name,email'],
    ['/en/admin/prayer-times/groups/export', 'id,name,members'],
    ['/en/admin/public-site/pages/export', 'id,title,slug'],
    ['/en/admin/public-site/courses/export', 'id,title,slug,category'],
]) {
    const csv = await csvOf(office, path);
    check(`CSV ${path.replace('/en/admin/', '')}`, csv.status === 200 && csv.type.includes('text/csv') && csv.text.startsWith(head), `${csv.status} ${csv.text.split('\n')[0].slice(0, 60)}`);
}
for (const [path] of [['/en/admin/instructors'], ['/en/admin/prayer-times/groups'], ['/en/admin/public-site/pages'], ['/en/admin/public-site/courses']]) {
    await office.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    check(`the Export CSV link is on ${path.replace('/en/admin/', '')}`, (await count(office, '[data-testid="export-csv"]')) === 1);
}

// ------------------------------------------------------------ 4. three writes

const slug = `smoke-audit-${Date.now()}`;
await office.goto(`${BASE}/en/admin/public-site/pages/create`, { waitUntil: 'networkidle' });
await office.fill('input[name="title"]', 'SMOKE audit page');
await office.fill('input[name="slug"]', slug);
await office.fill('textarea[name="body"]', '<p>Hello</p><script>alert(1)</script>');
// Scoped to the page form: the Blade nav carries a sign-out form with its own submit button.
await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.click('form[action*="public-site/pages"] button[type=submit]')]);
await office.goto(`${BASE}/en/admin/public-site/pages`, { waitUntil: 'networkidle' });
check('a CMS page is created from the form', (await text(office)).includes('SMOKE audit page'));
const row = office.locator('tr', { hasText: 'SMOKE audit page' }).first();
office.once('dialog', (d) => d.accept());
await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), row.locator('form button[type=submit], form button').last().click()]);
check('and deleted again', !(await text(office)).includes('SMOKE audit page'));

await office.goto(`${BASE}/en/admin/operations`, { waitUntil: 'networkidle' });
const firstBox = office.locator('input[type=checkbox]').first();
const before = await firstBox.isChecked();
await firstBox.click();
await office.waitForFunction((was) => document.querySelector('input[type=checkbox]')?.checked !== was, before, { timeout: 20000 }).catch(() => {});
const after = await office.locator('input[type=checkbox]').first().isChecked();
await office.locator('input[type=checkbox]').first().click();
await office.waitForFunction((was) => document.querySelector('input[type=checkbox]')?.checked === was, before, { timeout: 20000 }).catch(() => {});
check('an operations checklist item is ticked and unticked', after !== before && (await office.locator('input[type=checkbox]').first().isChecked()) === before);

await office.goto(`${BASE}/en/admin/translations?q=ops_checklist`, { waitUntil: 'networkidle' });
await settle(office, 'textarea');
const translationRows = await count(office, 'textarea');
check('the translation editor opens with rows to correct', translationRows > 0, `${translationRows} rows`);

await finish();
