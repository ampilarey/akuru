/**
 * The workspace homes (STATUS §5id): the School office at /school and the
 * Institute at /admin, each today's numbers then the workspace in parts.
 *
 * As the seeded educational admin: /dashboard lands on the School office;
 * Today shows the admissions and the registers numbers; the parts are
 * Admissions, Academics and Office; a card lists the screens inside its
 * group; a screen inside a Blade section opens with a full page load and an
 * Inertia one with a visit; the More menu is the School's; at 390 px the
 * cards stack and nothing is cut off. As the seeded system admin
 * (SMOKE_SUPER_ADMIN, `superadmin@` by default): /dashboard lands on the
 * Institute, with Website & content, Shops & money and System, the CMS
 * card's eight screens, the Institute bar, and the full dashboard a link
 * away.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/admin-hub.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_SUPER_ADMIN, SMOKE_CHROMIUM.
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
const moreGroups = async (page) => {
    await page.click('button[aria-controls="app-shell-more"]');
    await page.waitForSelector('#app-shell-more', { state: 'visible' });
    // textContent, not innerText: the headings are uppercased by CSS.
    const groups = (await page.locator('#app-shell-more h2').allTextContents()).map((t) => t.trim());
    const hrefs = await page.locator('#app-shell-more a').evaluateAll((els) => els.map((el) => (el.getAttribute('href') || '').replace(/^https?:\/\/[^/]+/, '').replace(/^\/(en|dv|ar)(?=\/|$)/, '')));
    await page.keyboard.press('Escape');
    return { groups, hrefs };
};

// ------------------------------------------------------------ 1. the School office
const office = await signIn(ADMIN, { width: 1400, height: 950 }, false);
// When the same account is also the system admin (a local grant for the
// sweeps), it lands on the Institute; the School office is then opened.
if (SUPER !== ADMIN) {
    check('the educational admin’s /dashboard lands on the School office', /\/school$/.test(office.url()), office.url().replace(BASE, ''));
}
await office.goto(`${BASE}/en/school`, { waitUntil: 'networkidle' });
const tiles = await texts(office, '[data-testid="today"] [data-testid^="today-"] span:last-child');
check('Today on top: pending payment, enrolled today, paid today, unfilled registers, ungraded exams', tiles.length === 5 && tiles[0] === 'Pending payment' && tiles[4] === 'Ungraded exams', tiles.join(', '));
const parts = await texts(office, '[data-testid^="part-"] h2');
check('then the School in three parts: Admissions, Academics, Office', parts.join(' | ') === 'Admissions | Academics | Office', parts.join(' | '));
check('Admissions holds Enrolments with its Payments screen', (await count(office, '[data-testid="section-admin_enrolments"] [data-testid="child-enrolment_payments"]')) === 1);
const yearChips = await texts(office, '[data-testid="section-school_year"] [data-testid^="child-"]');
check('the School year card lists its eleven screens', yearChips.length === 11 && yearChips[0] === 'Years', yearChips.join(', '));
check('the Office part holds People, Finance, HR and the lending library', (await count(office, '[data-testid="part-school_office"] [data-testid^="section-"]')) === 4);
check('a Blade screen inside a section is a plain link; an Inertia one a visit', (await office.locator('[data-testid="child-enrolment_payments"]').evaluate((el) => el.tagName)) === 'A' && (await office.locator('[data-testid="child-years"]').evaluate((el) => el.getAttribute('href')))?.endsWith('/academics/years'));

await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.click('[data-testid="child-enrolment_payments"]')]);
check('Payments (a screen inside Enrolments) opens as its Blade page', /\/admin\/enrollments\/payments$/.test(office.url()), office.url().replace(BASE, ''));
await office.goto(`${BASE}/en/school`, { waitUntil: 'networkidle' });
await office.click('[data-testid="child-years"]');
await office.waitForURL(/\/academics\/years$/, { timeout: 15000 }).catch(() => {});
check('Years (inside School year) opens as an Inertia visit', /\/academics\/years$/.test(office.url()), office.url().replace(BASE, ''));

const school = await moreGroups(office);
check('the More menu is the School’s: Admissions first, School year … Library, Mine; nothing of the Institute', school.groups[0] === 'Admissions' && school.groups.includes('School year') && school.groups.includes('HR') && school.groups.at(-1) === 'Mine' && !school.groups.includes('Website & content') && !school.groups.includes('System'), school.groups.join(' | '));
check('with the Blade screens the Blade nav used to link by hand', school.hrefs.includes('/announcements') && school.hrefs.includes('/quran-progress') && school.hrefs.includes('/substitutions/requests'));
check('and the inner screens kept off the menu (they are on the home)', !school.hrefs.includes('/admin/enrollments/payments'));
// C9 slice 4: the enrolment lists are Inertia; the Quran progress list is the School's Blade entry.
await office.click('button[aria-controls="app-shell-more"]');
await office.waitForSelector('#app-shell-more', { state: 'visible' });
await Promise.all([office.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), office.click('#app-shell-more a[data-nav-hard][href$="/quran-progress"]')]);
check('a Blade entry in the More menu opens its Blade screen', /\/quran-progress$/.test(office.url()), office.url().replace(BASE, ''));

// The full dashboard, and back.
await office.goto(`${BASE}/en/school`, { waitUntil: 'networkidle' });
await office.click('[data-testid="today-more"]');
await office.waitForURL(/\/portal\/overview$/, { timeout: 15000 }).catch(() => {});
check('the Staff overview link opens the overview, which carries the way home', /\/portal\/overview$/.test(office.url()) && (await count(office, '[data-testid="open-admin-panel"]')) === 1, office.url().replace(BASE, ''));
await office.click('[data-testid="open-admin-panel"]');
await office.waitForURL(/\/school$/, { timeout: 15000 }).catch(() => {});
check('whose button comes back to the School office', /\/school$/.test(office.url()), office.url().replace(BASE, ''));

// ------------------------------------------------------------ 2. a phone
const phone = await signIn(ADMIN, { width: 390, height: 844 }, true);
await phone.goto(`${BASE}/en/school`, { waitUntil: 'networkidle' });
const phoneMeasure = await phone.evaluate(() => {
    const vw = window.innerWidth;
    const cards = Array.from(document.querySelectorAll('[data-testid^="section-"]')).map((el) => el.getBoundingClientRect());
    const lefts = new Set(cards.map((r) => Math.round(r.left)));
    // A link inside the header's sideways-scrolling strip (STATUS §5hu, §5jq)
    // sits past the edge by design and is reached by a swipe; only an element
    // with no scrolling ancestor is cut off.
    const scrolls = (el) => {
        for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) {
            if (/auto|scroll/.test(getComputedStyle(a).overflowX)) return true;
        }
        return false;
    };
    const wide = Array.from(document.querySelectorAll('body *')).filter((el) => el.getBoundingClientRect().right > vw + 1 && !scrolls(el)).length;
    return { overflow: document.documentElement.scrollWidth - vw, columns: lefts.size, wide, parts: document.querySelectorAll('[data-testid="hub-parts"] a').length };
});
check('at 390 px the cards stack in one column and nothing is cut off', phoneMeasure.overflow <= 1 && phoneMeasure.columns === 1 && phoneMeasure.wide === 0 && phoneMeasure.parts === 3, JSON.stringify(phoneMeasure));
await phone.click('[data-testid="hub-parts"] a[href="#school_academics"]');
await phone.waitForFunction(() => document.querySelector('[data-testid="part-school_academics"]').getBoundingClientRect().top < 300, null, { timeout: 5000 }).catch(() => {});
// Under Chromium's phone emulation the layout viewport (1085 px) is taller than the
// visual one (844 px) and scrollIntoView lands the target that difference lower, so
// "at the top" here is the top third of the phone, not the first 200 px.
const academicsTop = await phone.locator('[data-testid="part-school_academics"]').evaluate((el) => el.getBoundingClientRect().top);
check('a part link jumps to its part', academicsTop >= -2 && academicsTop < 300, `${Math.round(academicsTop)}px`);

// ------------------------------------------------------------ 3. the Institute, when given
if (SUPER) {
    const su = await signIn(SUPER, { width: 1400, height: 950 }, false);
    await su.goto(`${BASE}/en/dashboard`, { waitUntil: 'networkidle' });
    check('a system admin’s /dashboard lands on the Institute', /\/admin$/.test(su.url()), su.url().replace(BASE, ''));
    const suParts = await texts(su, '[data-testid^="part-"] h2');
    check('in three parts: Website & content, Shops & money, System — twelve sections', suParts.join(' | ') === 'Website & content | Shops & money | System' && (await count(su, '[data-testid^="section-"]')) === 12, `${suParts.join(' | ')} ${await count(su, '[data-testid^="section-"]')}`);
    const cmsChips = await texts(su, '[data-testid="section-website_cms"] [data-testid^="child-"]');
    check('the Website card lists its eight screens, the prayer-times card its four', cmsChips.length === 8 && cmsChips[0] === 'Pages' && (await count(su, '[data-testid="section-prayer_times"] [data-testid^="child-"]')) === 4, cmsChips.join(', '));
    const bar = (await su.locator('header nav a[aria-current], header nav a:not([hrefLang])').allInnerTexts()).map((t) => t.trim()).filter(Boolean);
    check('the Institute bar: Website CMS, Commerce, Library office, Akuru Bookstore, Manage users', ['Website CMS', 'Commerce', 'Library office', 'Akuru Bookstore', 'Manage users'].every((label) => bar.includes(label)), bar.join(' | '));
    const institute = await moreGroups(su);
    check('the More menu: Website & content, Shops & money, System, Mine — nothing of the School', institute.groups.join(',') === 'Website & content,Shops & money,System,Mine', institute.groups.join(' | '));
    check('and the full dashboard a link away', (await su.getAttribute('[data-testid="today-more"]', 'href') || '').endsWith('/dashboard/numbers'));
    if (SUPER !== ADMIN) {
        check('no switcher for one workspace', (await count(su, '[data-testid="workspace-switcher"]')) === 0);
    }
    // An Inertia visit since C9 slice 13: wait for the address, then the page.
    await Promise.all([su.waitForURL(/\/dashboard\/numbers$/), su.click('[data-testid="today-more"]')]);
    await su.waitForSelector('[data-testid="numbers-kpis"]');
    check('the full dashboard opens, and carries the way home', /\/dashboard\/numbers$/.test(su.url()) && (await count(su, '[data-testid="open-admin-panel"]')) === 1 && (await su.textContent('body')).includes('Super Admin Dashboard'), su.url().replace(BASE, ''));
}

await finish();
