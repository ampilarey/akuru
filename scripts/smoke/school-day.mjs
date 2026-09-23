/**
 * Does a late mark reach the office's lists and the family, and does the
 * school calendar show families what is theirs and nothing more?
 *
 * Three E-rows shipped with tests and "still owed: the browser walk" in
 * their own STATUS entries — E10a lateness aggregated (§5al), E10b who is
 * not in today (§5ay), E11b the school calendar families can see (§5ax).
 * This walks them (E-track audit D1, STATUS §5fr). Three logins:
 *
 *   1. the office adds two calendar days for next week — a sports day
 *      published to families, a staff meeting kept internal — and the
 *      family's calendar shows the first and not the second;
 *   2. the teacher marks the pupil twelve minutes late on today's register;
 *   3. the office's who-is-not-in-today list has the pupil with the period,
 *      the lateness panel has them with one late mark and twelve minutes,
 *      and the tardies CSV downloads;
 *   4. the family's attendance page has today's row reading late.
 *
 * `SmokeMarkerSeeder::schoolDayCycle()` clears the two days and the pupil's
 * marks for today before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/school-day.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_TEACHER, SMOKE_STUDENT,
 * SMOKE_PARENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const PUBLIC_DAY = 'SMOKE-Sports-Day';
const INTERNAL_DAY = 'SMOKE-Staff-Meeting';
const MINUTES = '12';

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
    const context = await browser.newContext();
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    // Read only a mounted page. On a real host the app's JavaScript can land
    // after the network goes idle, and a read at that moment sees an empty
    // `main` — a fifth of the first staging run's failures were that (STATUS
    // §5fz). Bounded, so a page with nothing in it still reads as empty.
    const mounted = async () => {
        const deadline = Date.now() + 4000;
        while (Date.now() < deadline) {
            const ready = await page.evaluate(() => {
                const main = document.querySelector('main');
                return !document.querySelector('#app') || (main !== null && main.innerText.trim().length > 0);
            }).catch(() => true);
            if (ready) {
                return;
            }
            await page.waitForTimeout(150);
        }
    };
    for (const method of ['goto', 'reload']) {
        const raw = page[method].bind(page);
        page[method] = async (...args) => {
            const response = await raw(...args);
            await mounted();
            return response;
        };
    }
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

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');

async function settles(page, needle, ms = 6000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await text(page)).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

const hrefs = async (page) => page.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || ''));
const rowText = async (page, needle) => {
    const row = page.locator('tr', { hasText: needle }).first();

    return (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : '';
};
const isoDaysFromNow = (days) => new Date(Date.now() + days * 86400000).toISOString().slice(0, 10);

// ---------------------------------------------------------------- the pupil

const student = await signIn(STUDENT);
check('the pupil signs in', !student.url().includes('/login'), student.url());
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the pupil has a name', NAME.length > 0, NAME || 'no h2 on /portal/performance');

// ---------------------------------------------------------------- the office

// 1. two calendar days, one for families and one not
const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());
await admin.goto(`${BASE}/en/academics/calendar`, { waitUntil: 'networkidle' });
if ((await text(admin)).includes(PUBLIC_DAY)) {
    check('there is a clean calendar to walk', false, `${PUBLIC_DAY} already exists — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

// The form's two boxes, in order: "affects the timetable" (which is what the
// family's calendar prints as *No school*) and "show to families".
async function addDay(title, offset, { closesSchool, isPublic }) {
    const form = admin.locator('form', { hasText: 'Add day' });
    await form.locator('input[type="date"]').first().fill(isoDaysFromNow(offset));
    await form.locator('label', { hasText: 'Type' }).first().locator('select').selectOption('event');
    await form.locator('label', { hasText: 'Title (EN)' }).first().locator('input').fill(title);
    const boxes = form.locator('input[type="checkbox"]');
    for (const [index, wanted] of [[0, closesSchool], [1, isPublic]]) {
        if ((await boxes.nth(index).isChecked()) !== wanted) {
            await boxes.nth(index).click();
        }
    }
    await form.locator('button:has-text("Add day")').click();

    return settles(admin, title);
}

const publicSaved = await addDay(PUBLIC_DAY, 7, { closesSchool: false, isPublic: true });
const internalSaved = await addDay(INTERNAL_DAY, 8, { closesSchool: false, isPublic: false });
check('the office adds a public day and an internal day', publicSaved && internalSaved, `${PUBLIC_DAY}: ${publicSaved} · ${INTERNAL_DAY}: ${internalSaved}`);

// ---------------------------------------------------------------- the family

const parent = await signIn(PARENT);
check('the parent signs in', !parent.url().includes('/login'), parent.url());
await parent.goto(`${BASE}/en/portal/holidays`, { waitUntil: 'networkidle' });
const calendar = await text(parent);
const sportsDay = calendar.match(new RegExp(`\\d{4}-\\d{2}-\\d{2} ${PUBLIC_DAY}[^0-9]*`))?.[0]?.trim() ?? '';
check('the family\'s calendar shows the published day and not the internal one', calendar.includes(PUBLIC_DAY) && !calendar.includes(INTERNAL_DAY), sportsDay || calendar.slice(0, 160));
// An event that does not close the school must not tell a parent to keep
// the child home — "No school" is stated for the days that close it.
check('a day that keeps the school open is not read as "No school"', sportsDay.length > 0 && !/No school/.test(sportsDay), sportsDay);

// -------------------------------------------------------------- the teacher

// 2. a late mark on today's register
const teacher = await signIn(TEACHER);
check('the teacher signs in', !teacher.url().includes('/login'), teacher.url());
await teacher.goto(`${BASE}/en/academics/registers/today`, { waitUntil: 'networkidle' });
const generate = teacher.locator('button:has-text("Generate my registers")').first();
if (await generate.count()) {
    await generate.click();
    await teacher.waitForTimeout(2500);
    await teacher.goto(`${BASE}/en/academics/registers/today`, { waitUntil: 'networkidle' });
}
let registerUrl = null;
for (const href of [...new Set((await hrefs(teacher)).filter((h) => /\/academics\/registers\/\d+$/.test(h)))]) {
    await teacher.goto(new URL(href, BASE).href, { waitUntil: 'networkidle' });
    if ((await teacher.locator('tr', { hasText: NAME }).count()) > 0) {
        registerUrl = teacher.url();
        break;
    }
}
check('a register for today has the pupil on its roster', Boolean(registerUrl), registerUrl?.replace(BASE, '') ?? `no register today lists ${NAME}`);
if (!registerUrl) {
    await finish();
}
const row = teacher.locator('tr', { hasText: NAME }).first();
await row.locator('select').first().selectOption('late');
await row.locator('input[type="number"]').first().fill(MINUTES);
if ((await teacher.locator('textarea').nth(0).inputValue()).trim() === '') {
    await teacher.locator('textarea').nth(0).fill('SMOKE-Taught: sun and moon letters.');
}
await teacher.click('button:has-text("Submit register")');
check('the teacher marks the pupil late and submits', await settles(teacher, 'SUBMITTED'), (await text(teacher)).slice(0, 120));

// ---------------------------------------------------------------- the office

// 3. the two lists
// A pupil who came late is *in*: the list is of children not in the
// building, and putting a late child on it would send the office ringing
// a home whose child is sitting in class.
const absences = await admin.goto(`${BASE}/en/academics/attendance/absences`, { waitUntil: 'networkidle' });
const missing = await rowText(admin, NAME);
check('who-is-not-in-today opens and does not list a pupil who came late', absences.status() === 200 && missing === '', missing || `HTTP ${absences.status()} · ${NAME} not listed`);

await admin.goto(`${BASE}/en/academics/attendance`, { waitUntil: 'networkidle' });
// The panel's own table, not the page's first table of marks — both have a
// row for this pupil and only one of them aggregates.
const lateRow = admin.locator('table').filter({ hasText: 'Left early' }).locator('tr', { hasText: NAME }).first();
const late = (await lateRow.count()) ? (await lateRow.innerText()).replace(/\s+/g, ' ') : '';
check('the lateness panel has the pupil with one late mark and the minutes', late.includes(NAME) && new RegExp(`\\b1\\b[^0-9]*\\b${MINUTES}\\b`).test(late), late || ((await text(admin)).match(/Lateness and early departures.{0,160}/)?.[0] ?? (await text(admin)).slice(0, 160)));
const tardiesHref = (await hrefs(admin)).find((h) => h.includes('kind=tardies'));
const csv = tardiesHref ? await admin.request.get(new URL(tardiesHref, BASE).href) : null;
check('the tardies CSV downloads with the pupil on it', csv !== null && csv.status() === 200 && /csv/i.test(csv.headers()['content-type'] ?? '') && (await csv.text()).includes(NAME), csv ? `HTTP ${csv.status()} ${csv.headers()['content-type'] ?? ''}` : 'no kind=tardies link');

// ---------------------------------------------------------------- the family

// 4. today's row
await parent.goto(`${BASE}/en/portal/attendance`, { waitUntil: 'networkidle' });
const today = await rowText(parent, new Date().toISOString().slice(0, 10));
check('the family sees today\'s row reading late', /late/i.test(today), today || (await text(parent)).slice(0, 160));

await finish();
