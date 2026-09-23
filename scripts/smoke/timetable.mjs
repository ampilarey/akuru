/**
 * Does the timetable refuse a teacher in two places at once — and let the
 * office overrule it on purpose, with a reason?
 *
 * S2.2's conflict engine is a seventeen-case unit matrix and the builder
 * was walked at §5eu, but the §2 row still said *UNVERIFIED as a lone task*:
 * nobody had watched the refusal happen on the screen, which is the whole
 * point of a conflict checker (S2 audit, STATUS §5fs). One login:
 *
 *   1. the office places a subject with a teacher into a slot of
 *      `SMOKE-Class A`;
 *   2. switches to `SMOKE-Class B` and places the same teacher in the same
 *      slot — and is **refused**, told why, with nothing saved;
 *   3. ticks *Allow conflict*, gives a reason, and places it — saved, and
 *      the cell wears its conflict badge so nobody mistakes it for clean;
 *   4. the teacher view shows the teacher in both classes at that hour;
 *      removing the override empties the cell; the week exports as CSV.
 *
 * `SmokeMarkerSeeder::timetableCycle()` plants the two empty classes and
 * clears their entries before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/timetable.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const CLASS_A = 'SMOKE-Class A';
const CLASS_B = 'SMOKE-Class B';
const REASON = 'SMOKE-Override: exam week, combined class';

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

async function until(page, predicate, ms = 6000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if (await predicate()) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

// The first teaching period's Monday cell: row 1 of the grid is the first
// period that is not a break, and its second column is Monday.
const slot = (page) => page.locator('tbody tr').filter({ hasNot: page.locator('text=/break/i') }).first().locator('td').nth(1);
const entriesIn = (cell) => cell.locator('div.rounded');

async function openClass(page, label) {
    const select = page.locator('select').filter({ has: page.locator('option', { hasText: 'Class' }) }).first();
    const option = (await select.locator('option').allInnerTexts()).find((row) => row.trim() === label);
    if (!option) {
        return false;
    }
    await select.selectOption({ label: option });
    await page.waitForLoadState('networkidle');

    return until(page, async () => (await select.locator('option:checked').innerText()).trim() === label);
}

// ---------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());
await admin.goto(`${BASE}/en/academics/timetable`, { waitUntil: 'networkidle' });

// 1. a placement in class A
check('the builder offers the two smoke classes', await openClass(admin, CLASS_A), (await text(admin)).slice(0, 160));
const yearId = new URL(admin.url()).searchParams.get('academic_year_id');
const classAId = new URL(admin.url()).searchParams.get('class_id');
if ((await entriesIn(slot(admin)).count()) > 0) {
    check('there is an empty week to walk', false, `${CLASS_A} already has an entry in the first slot — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}
// The placement panel's teacher — a labelled select, unlike the page's
// filter selects, whose first option is the word "Teacher".
const teacherSelect = admin.locator('label:has-text("Teacher") select').first();
const TEACHER = (await teacherSelect.locator('option:checked').innerText().catch(() => '')).trim();
check('a teacher is chosen for the placement', TEACHER.length > 0, TEACHER || 'no teacher in the placement panel');
await admin.locator('button[draggable]').first().click();
const SUBJECT = (await admin.locator('button[draggable]').first().innerText()).trim();
await slot(admin).click();
const placed = await until(admin, async () => (await entriesIn(slot(admin)).count()) === 1);
check('a subject and a teacher are placed in the first slot', placed && (await slot(admin).innerText()).includes(TEACHER), placed ? `${SUBJECT} · ${TEACHER}` : (await text(admin)).slice(0, 160));

// 2. the same teacher, the same slot, another class — refused
check('the builder switches to the second class', await openClass(admin, CLASS_B));
const classBId = new URL(admin.url()).searchParams.get('class_id');
const teacherId = await teacherSelect.inputValue();
await admin.locator('button[draggable]').first().click();
await slot(admin).click();
// The refusal is the red line the save comes back with — read there, not
// from the page, which always carries the words "Allow conflict".
const refusal = admin.locator('p.text-red-600');
const refused = await until(admin, async () => (await refusal.count()) > 0 && /conflicts: .*teacher/i.test(await refusal.first().innerText()));
check('the same teacher in the same slot elsewhere is refused, and the office is told why', refused && (await entriesIn(slot(admin)).count()) === 0, refused ? (await refusal.first().innerText()).trim().slice(0, 160) : (await text(admin)).slice(0, 160));

// 3. overruled on purpose, with a reason
const allow = admin.locator('label', { hasText: 'Allow conflict' }).locator('input[type="checkbox"]');
check('the office may overrule a conflict', (await allow.count()) > 0, (await allow.count()) ? 'Allow conflict is offered' : 'no override control — the login lacks timetables.allow_conflict');
if (await allow.count()) {
    await allow.check();
    await admin.locator('input[placeholder="Override reason"]').fill(REASON);
    await admin.locator('button[draggable]').first().click();
    await slot(admin).click();
}
const overridden = await until(admin, async () => (await entriesIn(slot(admin)).count()) === 1);
check('with a reason it is placed, and the cell wears its conflict badge', overridden && /Conflict: .*teacher/i.test(await slot(admin).innerText()), overridden ? (await slot(admin).innerText()).replace(/\s+/g, ' ').slice(0, 120) : (await text(admin)).slice(0, 160));

// 4. the teacher view, the removal, the export
//
// The views are opened by URL: the view buttons re-fetch the page in the
// background with the state preserved, and a walk that clicks one and reads
// the grid straight after reads the old view as often as the new.
const builder = (params) => `${BASE}/en/academics/timetable?${new URLSearchParams({ academic_year_id: yearId, ...params })}`;
await admin.goto(builder({ view: 'teacher', teacher_id: teacherId }), { waitUntil: 'networkidle' });
const teacherSlot = (await slot(admin).innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the teacher view shows them in both classes at that hour, by name', (await entriesIn(slot(admin)).count()) >= 2 && teacherSlot.includes(CLASS_A) && teacherSlot.includes(CLASS_B), teacherSlot.slice(0, 160));

// Back in the class view — in the teacher view the cell holds both classes'
// entries and "the first Remove" would take the wrong one.
const classBUrl = builder({ view: 'class', class_id: classBId });
await admin.goto(classBUrl, { waitUntil: 'networkidle' });
const [deleted] = await Promise.all([
    admin.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/academics/timetable/')).catch(() => null),
    slot(admin).locator('button', { hasText: 'Remove' }).first().click(),
]);
// Read the week back from a fresh request rather than from whatever the
// builder was mid-way through re-rendering.
await admin.goto(classBUrl, { waitUntil: 'networkidle' });
check('removing the override empties the cell', deleted !== null && deleted.status() < 400 && (await entriesIn(slot(admin)).count()) === 0, deleted ? `DELETE ${deleted.status()} · ${(await slot(admin).innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 80) || 'empty'}` : 'no DELETE was sent');
await admin.goto(builder({ view: 'class', class_id: classAId }), { waitUntil: 'networkidle' });
check('the first class keeps its lesson', (await entriesIn(slot(admin)).count()) === 1 && (await slot(admin).innerText()).includes(TEACHER), (await slot(admin).innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 80));

const csv = await admin.request.get(`${BASE}/en/academics/timetable/export`);
check('the week exports as CSV', csv.status() === 200 && /csv/i.test(csv.headers()['content-type'] ?? ''), `HTTP ${csv.status()} ${csv.headers()['content-type'] ?? ''}`);

await finish();
