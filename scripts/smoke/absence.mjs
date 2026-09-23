/**
 * A family reports an absence in the morning. Does the register believe them?
 *
 * Three actors and a **specific order** — the ordinary one, and the one that
 * was broken:
 *
 *   1. the guardian writes an absence note for today,
 *   2. the office approves it,
 *   3. *then* a teacher fills the register and marks the child absent.
 *
 * `ApproveAbsenceNoteAction` handled only the other order: register first, note
 * afterwards, flip the rows it finds. Approved in the morning, it ran against
 * no rows at all, and the absent mark written at 9am carried no note. The
 * family got an absence SMS about the absence they had reported, the child
 * joined the chronic-absence list, and **nobody could undo it** — a teacher
 * marking the row `excused` is refused by the writer's own guard, and
 * re-approving the note throws "already approved".
 *
 * The walk ends on the **guardian's** attendance page rather than in the
 * database, because that is where the sentence is: the family sees `EXCUSED`
 * and a "no message was due" against the day they told the school about.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/absence.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PARENT, SMOKE_STAFF, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const REASON = 'SMOKE-Absence: fever since last night, seen by a doctor.';

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

// `main`, not `body`: the shell puts ~90 links above the content, so a body
// read can match a menu item rather than the page (OWNER_ACTIONS item 8).
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

// These forms post over XHR. Neither `networkidle` nor the POST response
// covers Inertia following the redirect and re-rendering, so wait for what the
// screen should say — a timeout still fails the step.
async function settles(page, needle, ms = 5000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await text(page)).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

const today = new Date().toISOString().slice(0, 10);

// ---------------------------------------------------------------- the family

const parent = await signIn(PARENT);
check('the guardian signs in', !parent.url().includes('/login'), parent.url());

await parent.goto(`${BASE}/en/portal/absence-notes`, { waitUntil: 'networkidle' });

const childName = await parent.$eval('select', (el) => el.options[el.selectedIndex]?.text || '').catch(() => '');
check('the guardian has a child to write about', childName !== '', childName || 'no child in the select');

const selects = await parent.$$('select');
check('the form offers the school\'s own reasons', selects.length >= 2, `${selects.length} selects`);

if (childName !== '' && selects.length >= 2) {
    // The reason select is the second one; its first real option is whichever
    // reason the school has configured first. Chosen by index rather than by
    // name so the walk does not assume a particular school's vocabulary.
    const reasonValue = await parent.$eval('select:nth-of-type(1)', () => null).catch(() => null);
    void reasonValue;

    await selects[1].selectOption({ index: 0 });
    await parent.fill('input[type="date"]', today);
    await parent.fill('textarea', REASON);
    await parent.click('button:has-text("Submit note")');

    check('the note is accepted', await settles(parent, 'Absence note submitted.'), (await text(parent)).slice(0, 140));
    check('and the family can see it waiting', (await text(parent)).includes(REASON));
}

// ---------------------------------------------------------------- the office

const staff = await signIn(STAFF);
await staff.goto(`${BASE}/en/academics/absence-notes`, { waitUntil: 'networkidle' });

const queued = await text(staff);
check('the note reaches the office queue', queued.includes(REASON), queued.slice(0, 140));

const noteCard = staff.locator('article, li, tr, div').filter({ hasText: REASON }).last();
const approve = noteCard.locator('button:has-text("Approve")').first();

if (await approve.count()) {
    await approve.click();
    await staff.waitForTimeout(1500);
    await staff.goto(`${BASE}/en/academics/absence-notes`, { waitUntil: 'networkidle' });
    const after = await text(staff);
    check('the office approves it', /approved/i.test(after), after.slice(0, 140));
} else {
    check('the office approves it', false, 'no Approve button beside the note');
}

// -------------------------------------------------------------- the register

// The teacher, not the office. `/academics/registers/today` is keyed on the
// signed-in user's **teacher profile**, and the admin login has none — it says
// so plainly, which is the right answer and not a defect, but it does mean the
// register half of this walk cannot be done by the same person who approved
// the note. Two staff logins, as in a real school.
const teacher = await signIn(TEACHER);
await teacher.goto(`${BASE}/en/academics/registers/today`, { waitUntil: 'networkidle' });

// Nothing has been marked yet. This is the order that was broken: the approval
// above ran against no attendance rows at all.
const generate = teacher.locator('button:has-text("Generate my registers")').first();
if (await generate.count()) {
    await generate.click();
    await teacher.waitForTimeout(2500);
    await teacher.goto(`${BASE}/en/academics/registers/today`, { waitUntil: 'networkidle' });
}

const registerHref = (await teacher.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || '')))
    .find((href) => /\/academics\/registers\/\d+/.test(href));

check('a register for today is open', Boolean(registerHref), registerHref ?? 'no /academics/registers/N link');

let markedAbsent = false;

if (registerHref) {
    await teacher.goto(new URL(registerHref, BASE).href, { waitUntil: 'networkidle' });

    // Mark exactly this child absent, by finding their row rather than the
    // first select on the page.
    const row = teacher.locator('tr').filter({ hasText: childName.trim() }).first();

    check('the child is on it', (await row.count()) > 0, childName);

    if (await row.count()) {
        await row.locator('select').first().selectOption('absent');
        // The register will not submit without a plan topic or a line about
        // what was taught — it says so, and an earlier version of this walk
        // clicked Submit, took the 303 for success and reported a green save
        // that had never happened.
        await teacher.fill('textarea >> nth=0', 'SMOKE-Taught: sun and moon letters.');
        await teacher.click('button:has-text("Submit register")');

        // `SUBMITTED` is the register's own status badge, and it is the
        // difference between a save and a bounce: a rejected submit leaves it
        // `EXPECTED` with the validation message beside it.
        markedAbsent = await settles(teacher, 'SUBMITTED');
        check('the teacher marks them absent and submits', markedAbsent, (await text(teacher)).slice(0, 160));
    } else {
        check('the teacher marks them absent and submits', false, `no row for ${childName}`);
    }
}

// --------------------------------------------- back to the family, the point

if (markedAbsent) {
    await parent.goto(`${BASE}/en/portal/attendance`, { waitUntil: 'networkidle' });
    const attendance = await text(parent);

    // Read the row for today, not the page. The summary line above the table
    // says "Absent 0 · Excused 0", so a bare /EXCUSED/i matched the word
    // **Excused** in a counter that read zero — an earlier version of this
    // walk reported the whole loop green against a table that said "No
    // attendance recorded yet".
    const rows = await parent.$$eval('tbody tr', (trs) => trs.map(
        (tr) => [...tr.querySelectorAll('td')].map((td) => td.innerText.replace(/\s+/g, ' ').trim()),
    ));
    const todayRow = rows.find((cells) => cells[0]?.includes(new Date().toISOString().slice(0, 10)));

    check(
        'the family has a row for today at all',
        Boolean(todayRow),
        todayRow ? todayRow.join(' | ') : `${rows.length} rows, none for today`,
    );
    check(
        'and it reads excused, not absent',
        /excused/i.test(todayRow?.[2] ?? ''),
        todayRow?.[2] ?? 'no status cell',
    );
    // The column reads "Sent" or "Not applicable", never "Yes" — an earlier
    // `/^yes$/i` here passed on the broken build, where it said **Sent**: the
    // family that reported the absence had been texted about it. The harm, on
    // the family's own screen, and the check was looking for the wrong word.
    check(
        'and the family was not texted about the absence they reported',
        Boolean(todayRow) && !/sent/i.test(todayRow[3] ?? ''),
        todayRow?.[3] ?? 'no notified cell',
    );
}

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
