/**
 * Does the daily habit work — homework, the noticeboard, a message and its
 * reply, a class poll?
 *
 * `docs/EDUPAGE_FEATURES_PLAN.md` Wave 1 is the four things a family opens
 * every day on the old system: the homework list (E3), the noticeboard (E4),
 * messages (E2) and the notifications that point at them (E22). Each shipped
 * with feature tests over HTTP; none had been walked as the people involved
 * (E-track audit D1, STATUS §5fp). Four logins:
 *
 *   1. the teacher writes homework into today's register — the register is
 *      the one place homework is entered, by design;
 *   2. the pupil sees it under the subject with its due date and ticks it
 *      done; the tick survives a reload; the parent sees the same list with
 *      nothing to tick;
 *   3. the office posts a notice to the pupil's class only; the pupil and the
 *      parent read it on the noticeboard, and the parent's CSV has it;
 *   4. the parent writes to the teacher; the teacher is told in their
 *      notification centre and their inbox shows it unread; the teacher
 *      replies and the parent reads the reply in the thread;
 *   5. the teacher polls the whole class; the parent answers once; the
 *      teacher sees the tally.
 *
 * `SmokeMarkerSeeder::familyCycle()` clears the threads, the notice, the
 * notifications and the ticks, and blanks the homework, before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/family.mjs
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

const HOMEWORK = 'SMOKE-Homework: read page 12';
const NOTICE = 'SMOKE-Notice';
const MESSAGE = 'SMOKE-Message';
const REPLY = 'SMOKE-Reply: noted, thank you.';
const POLL = 'SMOKE-Poll';
const POLL_QUESTION = 'SMOKE-Poll-Question: coming on Sunday?';
const YES = 'SMOKE-Yes';

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

// ---------------------------------------------------------------- the pupil

const student = await signIn(STUDENT);
check('the pupil signs in', !student.url().includes('/login'), student.url());
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the pupil has a name', NAME.length > 0, NAME || 'no h2 on /portal/performance');

// -------------------------------------------------------------- the teacher

// 1. homework, in the register — the only place it is written
const teacher = await signIn(TEACHER);
check('the teacher signs in', !teacher.url().includes('/login'), teacher.url());
await teacher.goto(`${BASE}/en/academics/registers/today`, { waitUntil: 'networkidle' });
const generate = teacher.locator('button:has-text("Generate my registers")').first();
if (await generate.count()) {
    await generate.click();
    await teacher.waitForTimeout(2500);
    await teacher.goto(`${BASE}/en/academics/registers/today`, { waitUntil: 'networkidle' });
}

// The register that has this pupil on its roster, found by looking rather
// than by taking the first link: the teacher may have several classes today.
let registerUrl = null;
let CLASS = '';
for (const href of [...new Set((await hrefs(teacher)).filter((h) => /\/academics\/registers\/\d+$/.test(h)))]) {
    await teacher.goto(new URL(href, BASE).href, { waitUntil: 'networkidle' });
    if ((await teacher.locator('tr', { hasText: NAME }).count()) > 0) {
        registerUrl = teacher.url();
        CLASS = ((await text(teacher)).match(/Today · [^·]+ · ([^·]+) · \d{4}-\d{2}-\d{2}/)?.[1] ?? '').trim();
        break;
    }
}
check('a register for today has the pupil on its roster', Boolean(registerUrl) && CLASS.length > 0, registerUrl ? `${registerUrl.replace(BASE, '')} · ${CLASS}` : `no register today lists ${NAME}`);
if (!registerUrl) {
    await finish();
}

if ((await teacher.locator('textarea').nth(1).inputValue()).startsWith('SMOKE-Homework')) {
    check('there is a clean register to walk', false, 'SMOKE-Homework is already on the register — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder');
    await finish();
}
if ((await teacher.locator('textarea').nth(0).inputValue()).trim() === '') {
    await teacher.locator('textarea').nth(0).fill('SMOKE-Taught: sun and moon letters.');
}
await teacher.locator('textarea').nth(1).fill(HOMEWORK);
const due = await teacher.locator('input[type="date"]').first().inputValue();
await teacher.click('button:has-text("Submit register")');
await teacher.waitForTimeout(1500);
await teacher.goto(registerUrl, { waitUntil: 'networkidle' });
check('the homework is saved on the register with a due date', (await teacher.locator('textarea').nth(1).inputValue()) === HOMEWORK && /^\d{4}-\d{2}-\d{2}$/.test(await teacher.locator('input[type="date"]').first().inputValue()), `due ${await teacher.locator('input[type="date"]').first().inputValue() || due || 'none'}`);

// ---------------------------------------------------------------- the pupil

// 2. sees it, ticks it, and the tick stays
await student.goto(`${BASE}/en/portal/homework`, { waitUntil: 'networkidle' });
const item = student.locator('li', { hasText: HOMEWORK }).first();
const itemText = (await item.innerText().catch(() => '')).replace(/\s+/g, ' ');
// "Arabic Language · Ustadh Mohamed Due 2026-09-24 …" — the teacher's display
// name, which is how the parent's recipient list will name them too.
const TEACHER_NAME = itemText.match(/· (.+?) Due /)?.[1]?.trim() ?? '';
check('the pupil sees the homework with its due date, under the subject and the teacher', (await item.count()) > 0 && /Due \d{4}-\d{2}-\d{2}/.test(itemText) && TEACHER_NAME.length > 0, itemText.slice(0, 140) || (await text(student)).slice(0, 160));
if (await item.count()) {
    await item.locator('input[aria-label="Mark done"]').click();
    await student.waitForTimeout(1500);
    await student.goto(`${BASE}/en/portal/homework`, { waitUntil: 'networkidle' });
}
check('ticking it done persists', (await student.locator('li', { hasText: HOMEWORK }).locator('input[aria-label="Mark not done"]').count()) > 0, (await student.locator('li', { hasText: HOMEWORK }).first().innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 120));

// ---------------------------------------------------------------- the office

// 3. a notice to this class only
const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());
await admin.goto(`${BASE}/en/announcements`, { waitUntil: 'networkidle' });
const notice = admin.locator('form', { hasText: 'Publish notice' });
await notice.locator('label', { hasText: 'Title' }).first().locator('input').fill(NOTICE);
await notice.locator('label', { hasText: 'Notice' }).first().locator('textarea').fill(`${NOTICE}: bring your reader on Sunday.`);
const classBox = notice.locator('label', { hasText: CLASS }).locator('input[type="checkbox"]').first();
check('the notice form offers the pupil\'s class', (await classBox.count()) > 0, CLASS);
if (await classBox.count()) {
    await classBox.check();
}
await notice.locator('button:has-text("Publish notice")').click();
check('the notice is published to the class', await settles(admin, NOTICE) && (await admin.locator('tr', { hasText: NOTICE }).first().innerText().catch(() => '')).includes('1 class'), (await admin.locator('tr', { hasText: NOTICE }).first().innerText().catch(() => '')).replace(/\s+/g, ' ') || (await text(admin)).slice(0, 160));

await student.goto(`${BASE}/en/portal/announcements`, { waitUntil: 'networkidle' });
check('the pupil reads it on the noticeboard', (await text(student)).includes(NOTICE), (await text(student)).match(new RegExp(`${NOTICE}[^.]*\\.`))?.[0] ?? (await text(student)).slice(0, 160));

// ---------------------------------------------------------------- the parent

const parent = await signIn(PARENT);
check('the parent signs in', !parent.url().includes('/login'), parent.url());
await parent.goto(`${BASE}/en/portal/homework`, { waitUntil: 'networkidle' });
const parentItem = parent.locator('li', { hasText: HOMEWORK }).first();
check('the parent sees the same homework, with nothing to tick', (await parentItem.count()) > 0 && (await parentItem.locator('input[type="checkbox"]').count()) === 0, (await parentItem.count()) ? 'read-only' : (await text(parent)).slice(0, 160));
await parent.goto(`${BASE}/en/portal/announcements`, { waitUntil: 'networkidle' });
check('the parent reads the notice too', (await text(parent)).includes(NOTICE), (await text(parent)).slice(0, 120));
const csv = await parent.request.get(`${BASE}/en/portal/announcements/export`);
check('the noticeboard exports as CSV', csv.status() === 200 && /csv/i.test(csv.headers()['content-type'] ?? '') && (await csv.text()).includes(NOTICE), `HTTP ${csv.status()} ${csv.headers()['content-type'] ?? ''}`);

// 4. a message to the teacher
await parent.goto(`${BASE}/en/portal/messages/new`, { waitUntil: 'networkidle' });
const person = parent.locator('label', { hasText: 'A person' }).locator('input[type="radio"]');
if (await person.count()) {
    await person.check();
}
// Recipients read "teacher — child": the teacher who set the homework, in
// the context of this child.
const toSelect = parent.locator('label', { hasText: 'To' }).first().locator('select');
const TEACHER_OPTION = (await toSelect.locator('option').allInnerTexts()).find((label) => label.startsWith(TEACHER_NAME) && label.includes(NAME)) ?? null;
check('the teacher who set the homework is offered as a recipient, for this child', Boolean(TEACHER_OPTION), TEACHER_OPTION ?? `no recipient "${TEACHER_NAME} — ${NAME}"`);
if (TEACHER_OPTION) {
    await toSelect.selectOption({ label: TEACHER_OPTION });
}
await parent.locator('label', { hasText: 'Subject' }).first().locator('input').fill(MESSAGE);
await parent.locator('label', { hasText: 'Message' }).first().locator('textarea').fill(`${MESSAGE}: ${NAME} will be late on Sunday.`);
await parent.locator('button[type="submit"]').first().click();
await parent.waitForLoadState('networkidle');
await parent.goto(`${BASE}/en/portal/messages`, { waitUntil: 'networkidle' });
check('the parent sends it and finds it in their inbox', (await text(parent)).includes(MESSAGE), (await text(parent)).slice(0, 160));

// ------------------------------------------------------------- the teacher

await teacher.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the teacher is told in their notification centre', (await text(teacher)).includes(MESSAGE) && !(await text(teacher)).includes('Nothing unread.'), (await text(teacher)).match(/\d+ unread\./)?.[0] ?? (await text(teacher)).slice(0, 160));
await teacher.goto(`${BASE}/en/portal/messages`, { waitUntil: 'networkidle' });
const inboxRow = teacher.locator('li', { hasText: MESSAGE }).first();
// The unread count is a badge beside the subject, rendered with no space
// between them ("SMOKE-Message1"), so it is read as an element, not a word.
check('the teacher\'s inbox shows it unread', (await inboxRow.count()) > 0 && (await inboxRow.locator('span.rounded-full').filter({ hasText: /^\s*1\s*$/ }).count()) > 0, (await inboxRow.innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 120) || (await text(teacher)).slice(0, 160));
if (await inboxRow.count()) {
    await inboxRow.locator('a').first().click();
    await teacher.waitForLoadState('networkidle');
}
check('the teacher reads what the parent wrote', (await text(teacher)).includes(`${NAME} will be late on Sunday.`), (await text(teacher)).slice(0, 160));
await teacher.locator('label', { hasText: 'Reply' }).first().locator('textarea').fill(REPLY);
await teacher.click('button:has-text("Send reply")');
check('the teacher replies', await settles(teacher, REPLY), (await text(teacher)).slice(0, 160));

// ------------------------------------------------------------- the parent

await parent.goto(`${BASE}/en/portal/messages`, { waitUntil: 'networkidle' });
// "With <teacher's full name>" on the 1:1 row — the account name, which is
// what a class thread must show this family and nothing more.
const TEACHER_FULL = ((await parent.locator('li', { hasText: MESSAGE }).first().innerText().catch(() => '')).match(/With (.+)/)?.[1] ?? '').trim();
await parent.locator('li', { hasText: MESSAGE }).first().locator('a').first().click();
await parent.waitForLoadState('networkidle');
check('the parent reads the reply in the thread', (await text(parent)).includes(REPLY) && (await text(parent)).includes(TEACHER_NAME) && TEACHER_FULL.length > 0, (await text(parent)).match(new RegExp(`${REPLY.slice(0, 11)}[^.]*\\.`))?.[0] ?? (await text(parent)).slice(0, 160));

// 5. a class poll
await teacher.goto(`${BASE}/en/portal/messages/new`, { waitUntil: 'networkidle' });
// A teacher with nobody to write to *as a person* is shown the class form
// straight away — the person/class chooser only appears when both apply.
const whole = teacher.locator('label', { hasText: 'A whole class' }).locator('input[type="radio"]');
if (await whole.count()) {
    await whole.check();
}
const classSelect = teacher.locator('label', { hasText: 'Class' }).first().locator('select');
check('the teacher may address a whole class', (await classSelect.count()) > 0 && (await classSelect.locator('option').allInnerTexts()).some((label) => label.includes(CLASS)), (await classSelect.count()) ? (await classSelect.locator('option').allInnerTexts()).join(' | ') : 'no class select — the teacher has no class to address');
if (await classSelect.count()) {
    const classOption = (await classSelect.locator('option').allInnerTexts()).find((label) => label.includes(CLASS));
    if (classOption) {
        await classSelect.selectOption({ label: classOption });
    }
    await teacher.locator('label', { hasText: 'Subject' }).first().locator('input').fill(POLL);
    await teacher.locator('label', { hasText: 'Message' }).first().locator('textarea').fill(`${POLL}: please answer below.`);
    await teacher.locator('input[placeholder="e.g. Will your child attend the trip?"]').fill(POLL_QUESTION);
    await teacher.locator('input[placeholder="Option 1"]').fill(YES);
    await teacher.locator('input[placeholder="Option 2"]').fill('SMOKE-No');
    await teacher.locator('button[type="submit"]').last().click();
    await teacher.waitForLoadState('networkidle');
}

await parent.goto(`${BASE}/en/portal/messages`, { waitUntil: 'networkidle' });
const pollRow = parent.locator('li', { hasText: POLL }).first();
check('the family gets the class message as their own thread', (await pollRow.count()) > 0, (await pollRow.count()) ? (await pollRow.innerText()).replace(/\s+/g, ' ').slice(0, 100) : (await text(parent)).slice(0, 160));
// Families in a class thread are not a group: this family is in conversation
// with the teacher, and sees nobody else by name.
const withLine = ((await pollRow.innerText().catch(() => '')).match(/With (.+)/)?.[1] ?? '').trim();
check('the family sees the teacher on it and no other family', withLine === TEACHER_FULL, withLine ? `With ${withLine}` : 'no "With" line');
if (await pollRow.count()) {
    await pollRow.locator('a').first().click();
    await parent.waitForLoadState('networkidle');
    await parent.locator('label', { hasText: YES }).first().locator('input[type="radio"]').check();
    await parent.locator('button:has-text("Answer")').first().click();
    await settles(parent, 'You answered:');
}
check('the parent answers the poll once', (await text(parent)).includes(`You answered: ${YES}`), (await text(parent)).match(/You answered: [^ ]+/)?.[0] ?? (await text(parent)).slice(0, 160));

await teacher.goto(`${BASE}/en/portal/messages`, { waitUntil: 'networkidle' });
const teacherPoll = teacher.locator('li', { hasText: POLL }).first();
if (await teacherPoll.count()) {
    await teacherPoll.locator('a').first().click();
    await teacher.waitForLoadState('networkidle');
}
check('the teacher sees the tally', (await text(teacher)).includes('1 answered so far.'), (await text(teacher)).match(/\d+ answered so far\./)?.[0] ?? (await text(teacher)).slice(0, 160));

await finish();
