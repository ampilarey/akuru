/**
 * Is a student called by the whole name — first, middle and last — wherever
 * the school shows it?
 *
 * C17 slice R4 gave a student a middle name on the registration forms (STATUS
 * §5og). The office's own forms had no field for it, and the directory, its
 * search, its CSV and the lists other screens build went on joining the first
 * name and the last. R4b (STATUS §5op) carries it everywhere;
 * `StudentNamesCarryTheMiddleNameTest` holds the actions. This walks the
 * screens, one office login and one parent:
 *
 *   1. the office adds a student with a middle name from the directory's form
 *      (once — a second run finds the first run's student and goes on);
 *   2. the directory finds them by the middle name alone, by the whole name
 *      and by the first and last — and lists them by the whole name;
 *   3. the CSV has a middle_name column, and the student's row fills it;
 *   4. the class page's roster search finds them by the middle name, in full;
 *   5. the office types a middle name for the seeded pupil on the profile,
 *      and the page's title becomes the whole name — and so does the
 *      pupil's own page, which also shows the save left their account
 *      linked (it unlinked it once, STATUS §5oo);
 *   6. the class page's roster names the pupil in full;
 *   7. the pupil's parent sees the whole name on My children;
 *   8. the office empties it again, and the title and the pupil's own page
 *      are the two names they started with — so the next walk finds the
 *      seeded state.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/middle-name.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PARENT, SMOKE_STUDENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// The walk's own student, made once. A surname nobody else is seeded with.
const FIRST = 'Zunaira';
const MIDDLE = 'Hassan';
const LAST = 'Smokewalk';
const WHOLE = `${FIRST} ${MIDDLE} ${LAST}`;
// The middle name the walk lends the seeded pupil, and takes back.
const LENT = 'Mohamed';

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

async function newPage(label) {
    const context = await browser.newContext();
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    // Read only a mounted page (STATUS §5fz).
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
    page.on('pageerror', (error) => problems.push(`${label}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${label}: HTTP ${response.status()} ${response.url()}`);
        }
    });

    return page;
}

async function signIn(email) {
    const page = await newPage(email);
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const title = async (page) => (await page.locator('h1').first().innerText().catch(() => '')).trim();

async function settles(page, needle, ms = 6000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await page.innerText('body')).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

// The directory's rows for a search: the name each one is listed by.
async function directory(page, search) {
    await page.goto(`${BASE}/en/people/students?search=${encodeURIComponent(search)}`, { waitUntil: 'networkidle' });
    const links = page.locator('table tbody tr a[href*="/people/students/"]');
    const out = [];
    for (let i = 0; i < (await links.count()); i += 1) {
        out.push({ name: (await links.nth(i).innerText()).replace(/\s+/g, ' ').trim(), href: new URL(await links.nth(i).getAttribute('href'), BASE).href });
    }

    return out;
}

// The profile form on a student's page: set the middle name, save, and wait
// for the redirect's flash on a fresh load.
async function setMiddleName(page, url, value) {
    await page.goto(`${url}?tab=overview`, { waitUntil: 'networkidle' });
    const field = page.getByLabel('Middle name', { exact: true });
    await field.fill(value);
    await page.locator('button:has-text("Save profile")').click();

    return settles(page, 'Student updated.');
}

// ---------------------------------------------------------------- the pupil

const student = await signIn(STUDENT);
check('the pupil signs in', !student.url().includes('/login'), student.url());
// The name on the pupil's own page — empty when their account no longer
// leads to their record.
const ownName = async () => {
    await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
    await student.locator('main h2').first().waitFor({ timeout: 10000 }).catch(() => {});
    return ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
};
const PUPIL = await ownName();
check('the pupil has a name, with no middle name yet', PUPIL.length > 0 && !PUPIL.includes(LENT), PUPIL || 'no h2 on /portal/performance');

// ---------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());

// 1. Add the walk's student, once.
let found = await directory(admin, WHOLE);
if (found.length === 0) {
    await admin.getByLabel('First name', { exact: true }).fill(FIRST);
    await admin.getByLabel('Middle name', { exact: true }).fill(MIDDLE);
    await admin.getByLabel('Last name', { exact: true }).fill(LAST);
    await admin.getByLabel('Date of birth', { exact: true }).fill('2013-04-04');
    // A select's accessible name carries its chosen option ("Gender Select"),
    // so by its label's text — which runs on into the options, "GenderSelect…".
    await admin.locator('label', { hasText: /^\s*Gender/ }).locator('select').selectOption('female');
    await Promise.all([
        admin.waitForURL(/\/people\/students\/\d+/, { timeout: 15000 }).catch(() => {}),
        admin.locator('button:has-text("Add student")').click(),
    ]);
    await settles(admin, 'Student created.');
    check('the office adds a student with a middle name from the directory', /\/people\/students\/\d+/.test(admin.url()) && (await title(admin)) === WHOLE, `${admin.url().replace(BASE, '')} · ${await title(admin)}`);
    // The page's data script holds the first page loaded, not the one
    // Inertia went on to; a fresh load reads this one.
    await admin.goto(admin.url(), { waitUntil: 'networkidle' });
    const made = await props(admin);
    check('the middle name is stored as its own part', made.student?.middle_name === MIDDLE && made.student?.first_name === FIRST && made.student?.last_name === LAST, JSON.stringify({ first: made.student?.first_name, middle: made.student?.middle_name, last: made.student?.last_name }));
} else {
    check('the walk\'s student is there from an earlier run', found.length === 1, found.map((row) => row.name).join(' / '));
}

// 2. Found by the middle name, the whole name, and the first and last.
for (const search of [MIDDLE + ' ' + LAST, WHOLE, `${FIRST} ${LAST}`]) {
    found = await directory(admin, search);
    check(`the directory finds them by "${search}", listed by the whole name`, found.length === 1 && found[0].name === WHOLE, found.map((row) => row.name).join(' / ') || 'nobody');
}
found = await directory(admin, MIDDLE);
check(`the directory finds them by the middle name alone ("${MIDDLE}")`, found.some((row) => row.name === WHOLE), found.map((row) => row.name).join(' / ') || 'nobody');

// 3. The CSV.
const csv = await admin.request.get(`${BASE}/en/people/students/export?search=${encodeURIComponent(LAST)}&status=`);
const lines = (await csv.text()).trim().split(/\r?\n/);
check('the CSV has a middle_name column between the first and the last', csv.ok() && /^id,student_number,first_name,middle_name,last_name,/.test(lines[0] ?? ''), lines[0] ?? `HTTP ${csv.status()}`);
check('and the student\'s row fills it', lines.some((line) => line.includes(`${FIRST},${MIDDLE},${LAST}`)), lines.slice(1, 3).join(' / ') || 'no rows');

// 5. The seeded pupil, lent a middle name on the profile.
found = await directory(admin, PUPIL);
const pupil = found.find((row) => row.name === PUPIL);
check('the office finds the seeded pupil by their name', Boolean(pupil), found.map((row) => row.name).join(' / ') || 'nobody');
if (!pupil) {
    await finish();
}
const [pupilFirst, ...pupilRest] = PUPIL.split(' ');
const LENT_WHOLE = [pupilFirst, LENT, ...pupilRest].join(' ');
check('the office types a middle name on the pupil\'s profile', await setMiddleName(admin, pupil.href, LENT), (await text(admin)).slice(0, 120));
await admin.goto(`${pupil.href}?tab=overview`, { waitUntil: 'networkidle' });
check('and the page is titled with the whole name', (await title(admin)) === LENT_WHOLE, await title(admin));
// Saving the profile once unlinked the pupil's account (STATUS §5oo).
const own = await ownName();
check('the pupil\'s own page names them in full — the save left their account theirs', own === LENT_WHOLE, own || 'no record — the account was unlinked');
const classId = (await props(admin)).student?.class_id;

// 4 and 6. The class page: its roster names the pupil in full, and its
// roster search finds the walk's student by the middle name.
if (classId) {
    await admin.goto(`${BASE}/en/academics/classes/${classId}`, { waitUntil: 'networkidle' });
    const roster = (await props(admin)).roster ?? [];
    check('the class roster names the pupil in full', roster.some((row) => row.name === LENT_WHOLE) && (await text(admin)).includes(LENT_WHOLE), roster.map((row) => row.name).slice(0, 4).join(' / ') || 'empty roster');
    await admin.goto(`${BASE}/en/academics/classes/${classId}?q=${encodeURIComponent(MIDDLE)}`, { waitUntil: 'networkidle' });
    const candidates = (await props(admin)).candidates ?? [];
    check('the roster search finds the walk\'s student by the middle name, in full', candidates.some((row) => row.name === WHOLE) && (await text(admin)).includes(WHOLE), candidates.map((row) => row.name).slice(0, 4).join(' / ') || 'no candidates');
} else {
    check('the seeded pupil is in a class', false, 'no class_id on the pupil\'s page');
}

// ---------------------------------------------------------------- the parent

// 7. My children.
const parent = await signIn(PARENT);
check('the parent signs in', !parent.url().includes('/login'), parent.url());
await parent.goto(`${BASE}/en/portal/children`, { waitUntil: 'networkidle' });
check('My children names the pupil in full', (await text(parent)).includes(LENT_WHOLE), (await text(parent)).slice(0, 160));

// ---------------------------------------------------------------- put back

// 8. Emptied, the title is the two names again.
check('the office empties the middle name again', await setMiddleName(admin, pupil.href, ''), (await text(admin)).slice(0, 120));
await admin.goto(`${pupil.href}?tab=overview`, { waitUntil: 'networkidle' });
const after = await props(admin);
check('and the pupil is called by the two names, with no middle name stored', (await title(admin)) === PUPIL && after.student?.middle_name === null, `${await title(admin)} · middle_name=${JSON.stringify(after.student?.middle_name)}`);
const ownAfter = await ownName();
check('and the pupil still opens their own record, by the two names', ownAfter === PUPIL, ownAfter || 'no record — the account was unlinked');

await finish();
