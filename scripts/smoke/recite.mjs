/**
 * Can a student hand in a recitation, and does a teacher mark it?
 *
 * `OPERATOR_CHECKLIST` §1e, which asked a person to *"open the recitation
 * review queue… submissions review normally"* — and could not be done, because
 * **no submission could exist**. `SubmitRecitationAction` had been written,
 * validated and tested since F3, and was reachable from six test files and from
 * nothing a student could press. The queue, the audio route, the mistake
 * marking, the outcomes and the CSV all had nothing to work on.
 *
 * That was a recorded deferral rather than a defect — F4 noted it needed
 * "private media upload + authenticated streaming", both of which shipped
 * afterwards. This walk exists because the way in now does too.
 *
 * The loop, which is §52.9's manual recording mode end to end:
 *
 *   1. a student picks a surah and an ayah range and records,
 *   2. they can play it back before sending it,
 *   3. it submits, and their own list shows it waiting,
 *   4. the teacher's queue has it,
 *   5. the teacher can **hear** it — §36, and the reason F4 could not ship this,
 *   6. **no AI opinion**, because the flag is off (rule 8),
 *   7. the teacher marks a mistake and an outcome,
 *   8. the student sees the outcome and the teacher's note.
 *
 * ## Why step 5 is asserted from the audio route and not the screen
 *
 * A row saying *"audio #41"* proves a column, not a recording. The teacher's
 * screen plays it through `recitations/{id}/audio/{kind}`, which authorizes per
 * request, so the walk fetches that URL and checks it comes back as audio with
 * bytes in it. SPEC §52.9: *"Do not expose private storage paths directly."*
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/recite.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_TEACHER, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const NOTE = `SMOKE-Recitation verdict ${Date.now().toString(36).toUpperCase()}`;

const HERMETIC_ARGS = [
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter,InterestFeedContentSuggestions',
    '--no-first-run',
    '--no-default-browser-check',
    // The same synthetic microphone §1d's walk uses. Nothing about this loop
    // needs real hardware until §1g asks whether a real voice is audible.
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

// Inertia repaints after `networkidle` has gone quiet — the fault that reported
// four working actions as failures in the pronunciation walk.
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

// ------------------------------------------------- 1–3. the student recites

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

await student.goto(`${BASE}/en/learn/quran`, { waitUntil: 'networkidle' });

const recordButton = student.getByRole('button', { name: /^Record$/ });
const canRecord = (await recordButton.count()) > 0 && !(await recordButton.isDisabled());

check(
    'the student is offered a way to record a recitation',
    canRecord,
    canRecord
        ? ''
        : `no usable Record button — ${(await student.locator('p[class*="amber"]').first().innerText().catch(() => 'and no explanation on screen')).replace(/\s+/g, ' ').trim().slice(0, 140)}`,
);

let submitted = false;

if (canRecord) {
    // Al-Fatihah 1–7, chosen from what the page offers rather than typed — an
    // empty surah list would otherwise fail three steps later on a missing
    // option instead of here.
    const surahSelect = student.locator('select').first();
    const options = await surahSelect.locator('option').count();
    check('the surah list is offered to choose from', options > 0, `${options} surahs`);

    await surahSelect.selectOption({ index: 0 });
    const numbers = student.locator('input[type=number]');
    await numbers.nth(0).fill('1');
    await numbers.nth(1).fill('7');

    await recordButton.click();
    // Long enough for MediaRecorder to emit a chunk; a zero-length blob would
    // be refused by the server and look like a validation defect.
    await student.waitForTimeout(1800);
    await student.getByRole('button', { name: /^Stop$/ }).click();

    // §52.9 step 4: "Student can replay recording." The control existing is the
    // blob existing — Submit only renders once there is something to send.
    const replay = student.locator('audio');
    await replay.first().waitFor({ state: 'attached', timeout: 10000 }).catch(() => {});
    check('the student can play it back before sending it', (await replay.count()) > 0);

    const send = student.getByRole('button', { name: /Send to my teacher/i });
    const haveBlob = (await send.count()) > 0;
    check('recording produces something to hand in', haveBlob, haveBlob ? '' : 'no Send button — the recorder produced no blob');

    if (haveBlob) {
        await send.click();
        const after = await settles(student, /your teacher will listen to it/i);
        submitted = /your teacher will listen to it/i.test(after);

        check('handing it in is confirmed', submitted, after.slice(0, 180));

        // It has to land where the student can see it waiting, not only in a
        // flash message that disappears on the next click.
        const mine = await settles(student, /submitted/i);
        check(
            'and it shows in their own list as submitted',
            /Al-Fatihah/i.test(mine) && /submitted/i.test(mine),
            mine.slice(mine.indexOf('My recitation submissions'), mine.indexOf('My recitation submissions') + 200),
        );
    }
}

// --------------------------------------------- 4–7. the teacher marks it

const teacher = await signIn(TEACHER);
await teacher.goto(`${BASE}/en/teach/recitations`, { waitUntil: 'networkidle' });

// Rows carrying a Review control, not every `tbody tr` — an empty queue still
// renders a "Queue is empty." row, which counted as "one waiting" in the
// pronunciation walk and then timed out reading a cell it does not have.
const queueRow = teacher.locator('tbody tr').filter({ has: teacher.getByRole('button', { name: /^Review$/ }) });
const queueRows = await queueRow.count();

check(
    'the recitation is waiting in the teacher\'s queue',
    queueRows > 0,
    `${queueRows} row(s) — the seeder clears this student's submissions, so this should be exactly the one just recorded`,
);

const queueText = await body(teacher);

// §1e's actual question. With AI_PRONUNCIATION_ENABLED off, nothing should be
// offering the teacher a machine opinion on the recitation.
check(
    'and no AI opinion is offered with the flag off',
    !/confidence|predicted|ai said/i.test(queueText),
    queueText.match(/[^.]*(confidence|predicted)[^.]*/i)?.[0]?.slice(0, 140) ?? '',
);

// The review form is collapsed until the row's Review button is pressed, so the
// audio player and the verdict controls do not exist before this click. The
// first version of this walk looked for them on the closed row and reported
// both as missing from a screen that has had them since F4.
if (queueRows > 0) {
    await queueRow.first().getByRole('button', { name: /^Review$/ }).click();
    await teacher.locator('form audio').first().waitFor({ state: 'attached', timeout: 10000 }).catch(() => {});
}

// §36 and §52.9: the teacher can hear it, through the authorizing route rather
// than a storage path. A row saying "audio #41" proves a column, not a sound.
const audioHref = (await teacher.$$eval('a, audio, source', (els) => els
    .map((el) => el.getAttribute('href') || el.getAttribute('src') || '')
    .filter((value) => /\/recitations\/\d+\/audio\//.test(value))))[0];

if (audioHref) {
    const audio = await teacher.request.get(new URL(audioHref, BASE).href);
    const bytes = audio.ok() ? (await audio.body()).length : 0;

    check(
        'the teacher can actually hear the student',
        audio.ok() && /audio|webm/i.test(audio.headers()['content-type'] ?? '') && bytes > 0,
        `HTTP ${audio.status()} ${audio.headers()['content-type'] ?? ''} ${bytes} bytes`,
    );
} else {
    check('the teacher can actually hear the student', false, 'no /recitations/N/audio/... link on the queue');
}

let reviewed = false;

if (queueRows > 0) {
    const form = teacher.locator('form').filter({ has: teacher.getByRole('button', { name: /Save review/i }) }).first();

    await form.locator('select').first().selectOption('passed');
    await form.locator('input[placeholder="Teacher note"]').fill(NOTE);

    // A mistake as well as an outcome, because marking mistakes is the half of
    // §52.10 the outcome dropdown does not exercise.
    await form.getByRole('button', { name: /\+ Mistake/i }).click();
    await form.locator('input[placeholder="Comment"]').first().fill('SMOKE-madd short on ayah 4');
    await form.locator('input[placeholder="Ayah"]').first().fill('4');

    await form.getByRole('button', { name: /Save review/i }).click();

    // Keyed on the row leaving the queue, not on the word "reviewed" appearing.
    //
    // The first version waited for /reviewed/i in the page body — and the
    // status filter buttons along the top of this screen include
    // "teacher reviewed", so it matched before anything had been saved. It
    // passed while the database still read `status=submitted, note=NULL`: a
    // false green over a review that never happened, and only the *next* step
    // failing gave it away.
    await teacher.waitForTimeout(500);
    const afterRows = await teacher.locator('tbody tr').filter({ has: teacher.getByRole('button', { name: /^Review$/ }) }).count();
    reviewed = afterRows < queueRows;

    check(
        'the teacher\'s verdict is accepted',
        reviewed,
        `queue ${queueRows} → ${afterRows} waiting (the default filter shows submitted only)`,
    );
}

// --------------------------------------------- 8. the student is told

if (reviewed) {
    await student.goto(`${BASE}/en/learn/quran`, { waitUntil: 'networkidle' });
    const mine = await settles(student, new RegExp(NOTE.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'));

    // The point of the whole loop. A verdict the student never sees is a
    // teacher talking to a database.
    check(
        'and the student sees the outcome and the note',
        mine.includes(NOTE),
        mine.slice(mine.indexOf('My recitation submissions'), mine.indexOf('My recitation submissions') + 240),
    );
}

// ------------------------------------------------------------- the guard

const guard = await student.goto(`${BASE}/en/teach/recitations`, { waitUntil: 'domcontentloaded' });
check('a student cannot open the review queue', guard.status() === 403, `HTTP ${guard.status()}`);

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
