/**
 * Does a question a teacher writes become a mark a student gets?
 *
 * Phase 2's definition of done (SPEC §47): *"Assessments can be attached to
 * lessons, modules, or courses … Auto-marked activities work … Question
 * editing does not affect existing attempts."* `learn.mjs` walks one
 * selection *activity*; `review.mjs` walks the teacher-marked kind. Nobody
 * had walked the **assessment** — the question bank, the builder, the
 * attach, the player, the auto-mark, the snapshot — and the §2 row said so
 * (Phase 2 audit D1, STATUS §5fi). This walks it, two logins:
 *
 *   1. the author writes two bank questions — a multiple-choice one and a
 *      short-answer one — builds `SMOKE-Assessment` on the seeded
 *      `SMOKE-Course`, published, marks shown, and attaches both;
 *   2. the student opens it from the course page, answers both, submits,
 *      and is scored 2/2 by the engine, no teacher involved;
 *   3. the author then edits the first question's text — and the student's
 *      attempt still shows the text they answered, at the same mark (§21:
 *      the attempt snapshots the question);
 *   4. the student tries again where the assessment allows it, and the new
 *      attempt starts unscored.
 *
 * `SmokeMarkerSeeder::assessCycle()` clears the assessment, its attempts and
 * the two bank questions before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/assess.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_AUTHOR, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const AUTHOR = process.env.SMOKE_AUTHOR ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const COURSE = 'SMOKE-Course';
const ASSESSMENT = 'SMOKE-Assessment';
const Q1 = 'SMOKE-Q1: Is the sky blue on a clear day?';
const Q1_EDITED = 'SMOKE-Q1 (edited): Is the sky blue on a clear day?';
const Q2 = 'SMOKE-Q2: Which city is the capital of the Maldives?';

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
    // Sixty seconds, not thirty: staging behind Cloudflare stalled past thirty on two page loads in one run (STATUS §5fz).
    context.setDefaultNavigationTimeout(60000);
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

const rowText = async (page, needle) => {
    const row = page.locator('tr', { hasText: needle }).first();

    return (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : '';
};

// ------------------------------------------------------------- the author

const author = await signIn(AUTHOR);
check('the author signs in', !author.url().includes('/login'), author.url());

await author.goto(`${BASE}/en/catalog/questions`, { waitUntil: 'networkidle' });
if ((await text(author)).includes('SMOKE-Q1')) {
    check('there is a clean bank to walk', false, 'SMOKE-Q1 is already in the bank — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder');
    await finish();
}

// 1. two bank questions
const bankForm = () => author.locator('form', { has: author.locator('textarea[placeholder="Question text"]') }).first();
const typeSelect = () => bankForm().locator('select').nth(0);

await bankForm().locator('textarea[placeholder="Question text"]').fill(Q1);
await bankForm().locator('button:has-text("Save question")').click();
check('a multiple-choice question is saved to the bank', await settles(author, 'SMOKE-Q1') && /mcq_single/.test(await rowText(author, 'SMOKE-Q1')) && /selection/.test(await rowText(author, 'SMOKE-Q1')), await rowText(author, 'SMOKE-Q1') || (await text(author)).slice(0, 160));

await typeSelect().selectOption('short_answer');
await bankForm().locator('textarea[placeholder="Question text"]').fill(Q2);
// The two JSON boxes: options (none for a text answer) and the accepted answer.
await bankForm().locator('textarea.font-mono').nth(0).fill('[]');
await bankForm().locator('textarea.font-mono').nth(1).fill('["Male"]');
await bankForm().locator('button:has-text("Save question")').click();
check('a short-answer question is saved, marked by text comparison', await settles(author, 'SMOKE-Q2') && /text_input/.test(await rowText(author, 'SMOKE-Q2')), await rowText(author, 'SMOKE-Q2') || (await text(author)).slice(0, 160));

// 2. the assessment on the course, published with marks shown
await author.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const courseId = Number(((await author.locator('tr', { hasText: COURSE }).locator('a', { hasText: COURSE }).first().getAttribute('href')) ?? '').match(/courses\/(\d+)/)?.[1] ?? 0);
check('the seeded course is in the catalog', courseId > 0, String(courseId));
await author.goto(`${BASE}/en/catalog/courses/${courseId}/assessments`, { waitUntil: 'networkidle' });

const buildForm = author.locator('form', { hasText: 'Save assessment' });
await buildForm.locator('input[placeholder="Title"]').fill(ASSESSMENT);
await buildForm.locator('select').nth(1).selectOption('published');
for (const label of ['Show correct answers', 'Show the mark to the student']) {
    const box = buildForm.locator('label', { hasText: label }).locator('input[type=checkbox]');
    if (!(await box.isChecked())) {
        await box.check();
    }
}
await buildForm.locator('button:has-text("Save assessment")').click();
const card = () => author.locator('section, div.rounded-lg', { hasText: ASSESSMENT }).filter({ has: author.locator('h2', { hasText: ASSESSMENT }) }).first();
check('an assessment is built on the course, published', await settles(author, ASSESSMENT) && /published/i.test(await card().innerText()), (await card().count()) ? (await card().innerText()).replace(/\s+/g, ' ').slice(0, 120) : (await text(author)).slice(0, 160));

// 3. attach both questions
const attachForm = author.locator('form', { hasText: 'Attach question' });
for (const needle of ['SMOKE-Q1', 'SMOKE-Q2']) {
    await attachForm.locator('select').nth(0).selectOption({ label: ASSESSMENT });
    const value = await attachForm.locator('select').nth(1).locator('option', { hasText: needle }).first().getAttribute('value');
    await attachForm.locator('select').nth(1).selectOption(value);
    await attachForm.locator('button:has-text("Attach question")').click();
    // Wait for this one to be on the card before attaching the next: a fixed
    // pause was long enough locally and not on staging, where the second
    // attach posted while the first was still re-rendering and was lost
    // (STATUS §5fz, second run).
    const deadline = Date.now() + 6000;
    while (Date.now() < deadline && !(await card().innerText()).includes(needle)) {
        await author.waitForTimeout(150);
    }
}
const built = (await card().innerText()).replace(/\s+/g, ' ');
check('both questions are attached, in order', built.indexOf('SMOKE-Q1') > -1 && built.indexOf('SMOKE-Q2') > built.indexOf('SMOKE-Q1'), built.slice(0, 200));

// ------------------------------------------------------------- the student

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

await student.goto(`${BASE}/en/learn/courses/${courseId}`, { waitUntil: 'networkidle' });
const openHref = await student.locator('li', { hasText: ASSESSMENT }).locator('a:has-text("Open")').first().getAttribute('href').catch(() => null);
check('the assessment is on the course page', Boolean(openHref), openHref ?? (await text(student)).slice(0, 160));
await student.goto(new URL(openHref ?? `/learn/courses/${courseId}`, BASE).href, { waitUntil: 'networkidle' });
check('the player shows both questions, unanswered', (await text(student)).includes(Q1) && (await text(student)).includes(Q2), (await text(student)).slice(0, 200));

// 4. answer and submit — scored by the engine
await student.locator('label', { hasText: /^Yes$/ }).locator('input[type=checkbox]').check();
await student.locator('input.form-input:not([type=checkbox])').last().fill('male');
await student.locator('button:has-text("Submit")').click();
check('submitting is acknowledged', await settles(student, 'Assessment submitted.'), (await text(student)).slice(0, 160));
const scored = await text(student);
const mark = scored.match(/scored · (\d+\/\d+)/)?.[1] ?? null;
check('and the engine scores it 2/2 with no teacher', /scored/.test(scored) && mark === '2/2', mark ?? scored.slice(0, 160));

// 5. the author edits the first question; the attempt keeps its snapshot
await author.goto(`${BASE}/en/catalog/questions`, { waitUntil: 'networkidle' });
await author.locator('tr', { hasText: 'SMOKE-Q1' }).locator('button:has-text("Edit")').click();
await bankForm().locator('textarea[placeholder="Question text"]').fill(Q1_EDITED);
await bankForm().locator('button:has-text("Save changes")').click();
check('the author edits the question in the bank', await settles(author, 'SMOKE-Q1 (edited)'), await rowText(author, 'SMOKE-Q1') || (await text(author)).slice(0, 160));

await student.reload({ waitUntil: 'networkidle' });
const after = await text(student);
const markAfter = after.match(/scored · (\d+\/\d+)/)?.[1] ?? null;
check('the student\'s attempt still shows the question they answered, at the same mark', after.includes(Q1) && !after.includes('(edited)') && markAfter === mark, `${after.match(/SMOKE-Q1[^?]*\?/)?.[0] ?? after.slice(0, 120)} · ${markAfter}`);

// 6. try again, where allowed
const again = student.locator('button:has-text("Try again")');
if (await again.count()) {
    await again.click();
    check('a second attempt starts unscored', await settles(student, 'Started again.') && /in_progress/.test(await text(student)) && !/scored ·/.test(await text(student)), (await text(student)).slice(0, 160));
} else {
    check('a second attempt starts unscored', false, 'no Try again — retake_limit reached or the control is missing');
}

await finish();
