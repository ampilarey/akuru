/**
 * Can a student hand in a recording, and does a human ear decide it?
 *
 * `OPERATOR_CHECKLIST` §1d, which had been written off as **"needs a mic"** and
 * left for a person ever since. It does not: Chromium can be told to hand
 * `getUserMedia` a synthetic audio stream instead of hardware
 * (`--use-fake-device-for-media-stream`) and to grant the permission without
 * asking (`--use-fake-ui-for-media-stream`). The page calls the real
 * `MediaRecorder` over that stream, posts a real `.webm` file, and the server
 * cannot tell the difference — which is the point. What a fake device does
 * *not* prove is that a human voice through a real microphone produces audio
 * the teacher can hear, so §1g's phone-mic line stays a person's job.
 *
 * The loop, which is the whole of Arabic B with AI off (rule 8):
 *
 *   1. a student picks a letter and a haraka and records an attempt,
 *   2. it is submitted, and **no AI says anything** — the flag is off,
 *   3. the teacher's queue has it, and says why it is there,
 *   4. the teacher gives a verdict with the verified letter + haraka,
 *   5. that verdict becomes a training sample awaiting approval,
 *   6. the admin approves it and the dataset count moves,
 *   7. the export writes a manifest,
 *   8. the model shelf renders — empty is correct, there is no model yet,
 *   9. the two role guards refuse the two people who should be refused.
 *
 * ## Why step 2 is asserted rather than assumed
 *
 * `AI_PRONUNCIATION_ENABLED` is off everywhere and stays off until a model is
 * trained on real approved samples and §51.17 consent is settled
 * (`OPERATOR_CHECKLIST` §2a/§2b). Rule 8 says everything must work with AI off.
 * "Works with AI off" is exactly the kind of claim that is true until somebody
 * adds a call that assumes a prediction exists, so the walk checks that the
 * student is told a teacher will listen, and that the teacher's AI column reads
 * as empty rather than as a confident guess.
 *
 * ## Why step 9 uses the teacher for the admin guard
 *
 * §1d asks for "the admin page 403s without `pronunciation.manage`". A student
 * would 403 on role alone and prove nothing about the permission. The teacher
 * has a staff role and *not* the permission, so they are the person who
 * distinguishes the two gates.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/pronounce.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_TEACHER, SMOKE_STUDENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const HERMETIC_ARGS = [
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter,InterestFeedContentSuggestions',
    '--no-first-run',
    '--no-default-browser-check',
    // The mic. Without these the Record button is either refused permission or
    // handed a dead stream, and the walk tests the error path by accident.
    '--use-fake-ui-for-media-stream',
    '--use-fake-device-for-media-stream',
];

const browser = await chromium.launch({
    args: HERMETIC_ARGS,
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

async function signIn(email) {
    const context = await browser.newContext({ permissions: ['microphone'] });
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

const body = async (page) => (await page.innerText('body')).replace(/\s+/g, ' ');

/**
 * The page once it has actually repainted.
 *
 * Every screen here is Inertia: the POST resolves, props come back, and React
 * repaints some time *after* `networkidle` has gone quiet. Reading the body
 * straight after a click caught the previous render five times in this walk's
 * first run — a submitted recording, an accepted verdict, an approved sample
 * and an export all reported as failures while the database showed every one of
 * them had worked. Same fault the money walk had; keyed on the thing the action
 * is supposed to produce rather than on a timeout.
 */
const settles = async (page, pattern, attempts = 40) => {
    for (let index = 0; index < attempts; index += 1) {
        const text = await body(page);
        if (pattern.test(text)) {
            return text;
        }
        await page.waitForTimeout(250);
    }

    return body(page);
};

// ------------------------------------------------- 1–2. the student records

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

await student.goto(`${BASE}/en/learn/pronounce`, { waitUntil: 'networkidle' });
const practice = await body(student);

// The sound to practise is chosen from what the screen offers, not typed in —
// if the reference tables were empty the selects would be empty and the walk
// should say so here rather than fail three steps later on a missing option.
const letterOptions = await student.locator('select').first().locator('option').count();
const harakaOptions = await student.locator('select').nth(1).locator('option').count();

check(
    'the practice screen offers letters and harakas',
    letterOptions > 0 && harakaOptions > 0,
    `${letterOptions} letters, ${harakaOptions} harakas`,
);

// §6.3's own refusal path: the page says up front when it cannot record at all.
// If that message is showing, the fake device did not take, and every step
// below would fail for a reason that has nothing to do with the product.
//
// Asked as "is the button usable", not "is the button there". The button is
// always rendered and goes `disabled` when `recordingSupport()` says the
// environment cannot record — so checking for its existence found it, clicked
// it, and hung for thirty seconds on a screen that was explaining itself
// perfectly well. That is what the walk does when the site's own
// `Permissions-Policy` shuts the microphone, which is the defect this walk was
// written to catch, so it has to report it rather than crash on it.
const recordButton = student.getByRole('button', { name: /^Record$/ });
const cannotRecord = (await recordButton.count()) === 0 || (await recordButton.isDisabled());

// Read off the banner the page renders for this, not scraped out of the whole
// body — a match against the body text found the word "Record" in the
// navigation and reported the menu back as the explanation.
const explanation = cannotRecord
    ? await student.locator('p[class*="amber"], p[class*="red-"]').first().innerText().catch(() => '')
    : '';

check(
    'the browser can record — the fake microphone took',
    !cannotRecord,
    cannotRecord ? `Record is disabled — screen says: ${explanation.replace(/\s+/g, ' ').trim() || '(nothing)'}` : '',
);

let submitted = false;

if (!cannotRecord) {
    await student.locator('select').first().selectOption({ index: 1 });
    await student.locator('select').nth(1).selectOption({ index: 0 });

    const chosen = (await student.locator('select').first().inputValue())
        + '/' + (await student.locator('select').nth(1).inputValue());

    await student.getByRole('button', { name: /^Record$/ }).click();
    // Long enough for MediaRecorder to emit a chunk. A zero-length blob still
    // posts, and the server would reject it as an empty file — which would look
    // like a validation defect rather than a walk in too much of a hurry.
    await student.waitForTimeout(1500);
    await student.getByRole('button', { name: /^Stop$/ }).click();

    // The replay control appearing is the blob existing; Submit is only
    // rendered once there is something to submit.
    const submitButton = student.getByRole('button', { name: /Submit recording/i });
    await submitButton.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});

    const haveBlob = (await submitButton.count()) > 0;
    check('recording produces something to hand in', haveBlob, haveBlob ? `letter/haraka ${chosen}` : 'no Submit button — the recorder produced no blob');

    if (haveBlob) {
        await submitButton.click();
        const after = await settles(student, /your teacher will hear it/i);
        submitted = /your teacher will hear it/i.test(after);

        check(
            'handing it in is confirmed, and says a teacher will listen',
            submitted,
            after.slice(0, 200),
        );

        // Rule 8, asserted rather than assumed: with the flag off nothing
        // should be telling the student how they did.
        check(
            'and no AI verdict is offered to the student',
            !/confiden|prediction|ai said|score/i.test(after),
            after.slice(0, 200),
        );
    }
}

// ------------------------------------------- 3–4. the teacher's ear decides

const teacher = await signIn(TEACHER);
await teacher.goto(`${BASE}/en/teach/pronunciation`, { waitUntil: 'networkidle' });
const queue = await body(teacher);

// Rows that carry a verdict control, not every `tbody tr` on the page. An empty
// queue still renders a placeholder row, so a bare count said "1 waiting" over
// an empty queue and then hung reading a second cell that placeholder does not
// have.
const queueRow = teacher.locator('tbody tr').filter({ has: teacher.getByRole('button', { name: /Confirm as selected/i }) });
const queueRows = await queueRow.count();

check(
    'the attempt is waiting in the teacher\'s queue',
    queueRows > 0,
    `${queueRows} row(s) — seeder clears this student's attempts, so this should be exactly the one just recorded`,
);

// The screen explains why everything lands here, which is the honest thing to
// say while the flag is off.
check(
    'the queue says why every attempt needs a human ear',
    /AI checking is off/i.test(queue),
    queue.slice(0, 200),
);

// The AI column exists but has nothing in it. A confident-looking guess here
// with the flag off would be the rule-8 failure this walk is for.
const aiCell = queueRows > 0
    ? (await queueRow.first().locator('td').nth(1).innerText()).trim()
    : '(no attempt in the queue to read)';

check('and the AI column is empty rather than guessing', aiCell === '—', `AI cell: ${JSON.stringify(aiCell)}`);

let reviewed = false;

if (queueRows > 0) {
    const row = queueRow.first();
    await row.locator('input[placeholder*="Notes"]').fill('SMOKE-Verdict: clear enough to keep.');
    await row.getByRole('button', { name: /Confirm as selected/i }).click();
    const after = await settles(teacher, /Attempt reviewed/i);
    reviewed = /Attempt reviewed/i.test(after);

    check('the teacher\'s verdict is accepted', reviewed, after.slice(0, 200));
}

// --------------------------- 5–8. the dataset, the export and the model shelf

const admin = await signIn(STAFF);

// Totals read off the screen's own summary line, before and after, because the
// dataset accumulates across runs and a bare "1" would match whatever was
// already there.
const totalsOf = (text) => {
    const match = text.match(/totals: ([^·]*(?:·[^·]*)*?)\s*Export approved/);
    return match ? match[1].trim() : '';
};

const countOf = (totals, status) => {
    const match = totals.match(new RegExp(`${status} (\\d+)`));
    return match ? Number(match[1]) : 0;
};

await admin.goto(`${BASE}/en/admin/pronunciation`, { waitUntil: 'networkidle' });
const beforeText = await body(admin);
const before = totalsOf(beforeText);

// Scoped the same way: this screen has three tables on it, so a bare `tbody tr`
// count also counts the model shelf's "No model versions registered." row.
const pendingRow = admin.locator('tbody tr').filter({ has: admin.getByRole('button', { name: /^Approve$/ }) });
const pendingRows = await pendingRow.count();

check(
    'the verdict arrives as a training sample awaiting approval',
    pendingRows > 0 && countOf(before, 'pending review') > 0,
    `totals: ${before || 'none'}`,
);

if (pendingRows > 0) {
    await pendingRow.first().getByRole('button', { name: /^Approve$/ }).click();
    const afterText = await settles(admin, /approved \d/i);
    const after = totalsOf(afterText);

    // The delta, not the presence of a number. Approving must move one sample
    // out of pending and into approved; a screen that simply re-rendered would
    // show the same figures and pass a match on "approved".
    check(
        'approving it moves the dataset count',
        countOf(after, 'approved') === countOf(before, 'approved') + 1
            && countOf(after, 'pending review') === countOf(before, 'pending review') - 1,
        `${before || 'none'} → ${after || 'none'}`,
    );

    // §51.16 step 7: Laravel writes a manifest, train.py consumes it. The flash
    // names the count and the path, so it is the manifest being reported and
    // not merely a button that answered.
    await admin.getByRole('button', { name: /Export approved samples/i }).click();
    const exported = await settles(admin, /Exported \d+ samples/i);
    const manifest = exported.match(/Exported (\d+) samples to (\S+\.json)/);

    check(
        'the export writes a manifest for the trainer',
        Boolean(manifest) && Number(manifest[1]) > 0,
        manifest ? `${manifest[1]} samples → ${manifest[2]}` : exported.slice(0, 200),
    );

    // And the exported rows leave the approved pile, which is what stops the
    // next export shipping the same audio again.
    check(
        'and the exported samples are marked used for training',
        /used for training [1-9]/i.test(totalsOf(await body(admin))),
        totalsOf(await body(admin)) || 'none',
    );
}

// Empty is the correct answer today — there is no trained model, and §2b says
// there will not be one until real approved samples exist and consent is
// settled. The shelf still has to render.
const shelf = await body(admin);
check(
    'the model shelf renders, empty or not',
    /No model versions registered\.|Model version/i.test(shelf),
    /No model versions registered/i.test(shelf) ? 'no versions yet, which is correct' : 'versions listed',
);

// --------------------------------------------------------- 9. the two guards

const plain = await signIn(STUDENT);
const teachGuard = await plain.goto(`${BASE}/en/teach/pronunciation`, { waitUntil: 'domcontentloaded' });

check(
    'a student cannot open the teacher\'s review queue',
    teachGuard.status() === 403,
    `HTTP ${teachGuard.status()}`,
);

// The teacher, not the student: they hold a staff role and not
// `pronunciation.manage`, so they are the one who proves the permission is what
// closes this door rather than the role.
const adminGuard = await teacher.goto(`${BASE}/en/admin/pronunciation`, { waitUntil: 'domcontentloaded' });

check(
    'a teacher without pronunciation.manage cannot open the AI admin',
    adminGuard.status() === 403,
    `HTTP ${adminGuard.status()}`,
);

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
