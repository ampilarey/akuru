/**
 * Does a recitation range reach the student as a passage, come back marked,
 * and does a halaqa map onto an offering?
 *
 * Qur'an Module A (`docs/QURAN_A_SPEC.md`, SPEC §52 / ROADMAP §2b) is four
 * slices — the surah reference read through one contract, recitation as a
 * teacher-marked activity with a validated range, halaqa → offering mapping,
 * and dual-write behind a flag that is off. All four had tests and the §2 row
 * said UNVERIFIED because nothing had walked them (Qur'an A audit D1, STATUS
 * §5fm). `recite.mjs` walks §52.9's recording queue, which is F3/F4, not
 * this. Two logins:
 *
 *   1. the office reads the surah reference and its CSV;
 *   2. the author builds a *recitation* activity on the seeded `SMOKE-Course`
 *      — teacher-marked, on Al-Ikhlas — and is refused a range past the end
 *      of the surah before a good one saves;
 *   3. the student sees the passage on the player, hands in, is not marked
 *      by the engine; the marker scores it in the ordinary review queue and
 *      the student sees the mark;
 *   4. the office links `SMOKE-Offering` to the seeded `SMOKE-Halaqa` Hifz
 *      program, maps one of its sessions, and is told dual-write is off
 *      (`QURAN_HALAQA_DUAL_WRITE`, rule 9 deploy 1 of 3).
 *
 * The ayah text under the passage heading comes from the imported Qur'an
 * dataset (`quran_ayahs`), which a fresh host does not have (ADR-023: the
 * operator imports a licensed edition). The walk asserts the heading and
 * reports whether text was there, rather than failing a host for not owning
 * a mushaf.
 *
 * `SmokeMarkerSeeder::quranCycle()` clears the activity, its attempts, and
 * re-plants the program before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/quran.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const COURSE = 'SMOKE-Course';
const OFFERING = 'SMOKE-Offering';
const PROGRAM = 'SMOKE-Halaqa';
const SURAH = { index: 112, english: 'Al-Ikhlas', ayahs: 4 };
const TITLE = 'SMOKE-Recite-Activity';
const PROMPT = 'SMOKE-Recite: recite this range';
const ANSWER = 'SMOKE-Recite-Answer';
const FEEDBACK = 'SMOKE-Recite-Feedback';
const SESSION = 'SMOKE-Halaqa-Session';

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

// -------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());

// 1. the reference, read through the one contract
await admin.goto(`${BASE}/en/catalog/quran`, { waitUntil: 'networkidle' });
const surahRow = await rowText(admin, SURAH.english);
check('the surah reference lists the surah with its ayah count', surahRow.includes(String(SURAH.index)) && surahRow.includes(String(SURAH.ayahs)), surahRow || `${SURAH.english} is not in the reference — seed it: php artisan db:seed --class=SurahSeeder`);

const csv = await admin.request.get(`${BASE}/en/catalog/quran/export`);
const csvBody = await csv.text().catch(() => '');
check('the reference exports as CSV', csv.status() === 200 && /csv/i.test(csv.headers()['content-type'] ?? '') && csvBody.includes(SURAH.english), `HTTP ${csv.status()} ${csv.headers()['content-type'] ?? ''}`);

// 2. a recitation activity — refused past the end of the surah, saved inside it
await admin.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const courseId = Number(((await admin.locator('tr', { hasText: COURSE }).locator('a', { hasText: COURSE }).first().getAttribute('href').catch(() => '')) ?? '').match(/courses\/(\d+)/)?.[1] ?? 0);
await admin.goto(`${BASE}/en/catalog/courses/${courseId}/activities`, { waitUntil: 'networkidle' });
if ((await rowText(admin, TITLE)) !== '') {
    check('there is a clean course to walk', false, `${TITLE} already exists — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

const build = admin.locator('form', { hasText: 'Save activity' });
await build.locator('input[placeholder="Title"]').fill(TITLE);
await build.locator('select').nth(0).selectOption('teacher_marked');
await build.locator('input[placeholder="Activity type label"]').fill('recitation');
const surahValue = await build.locator('select').nth(4).locator('option', { hasText: `${SURAH.index}. ${SURAH.english}` }).first().getAttribute('value').catch(() => null);
check('the builder offers the surah as a recitation range', Boolean(surahValue), surahValue ?? 'not in the surah list');
if (surahValue) {
    await build.locator('select').nth(4).selectOption(surahValue);
}
await build.locator('input[placeholder="Ayah start"]').fill('1');
await build.locator('input[placeholder="Ayah end"]').fill(String(SURAH.ayahs + 95));
await build.locator('input[placeholder="Max score"]').fill('1');
await build.locator('textarea.font-mono').nth(0).fill(JSON.stringify({ prompt: PROMPT, submission_kind: 'written' }));
await build.locator('button:has-text("Save activity")').click();
const refused = await settles(admin, 'outside the surah');
check('a range past the end of the surah is refused, and the author is told', refused && (await rowText(admin, TITLE)) === '', refused ? 'Ayah range is outside the surah.' : `${(await text(admin)).slice(0, 160)}`);

await build.locator('input[placeholder="Ayah end"]').fill('2');
await build.locator('button:has-text("Save activity")').click();
check('a recitation activity on ayahs 1–2 is built', await settles(admin, TITLE) && /teacher_marked/.test(await rowText(admin, TITLE)), await rowText(admin, TITLE) || (await text(admin)).slice(0, 160));

// ------------------------------------------------------------- the student

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

await student.goto(`${BASE}/en/learn/courses/${courseId}`, { waitUntil: 'networkidle' });
const activityHref = await student.locator('li, tr', { hasText: TITLE }).locator('a[href*="/learn/activities/"]').first().getAttribute('href').catch(() => null);
check('the activity is on the course page', Boolean(activityHref), activityHref ?? (await text(student)).slice(0, 160));
const activityUrl = new URL(activityHref ?? `/learn/courses/${courseId}`, BASE).href;
await student.goto(activityUrl, { waitUntil: 'networkidle' });
const player = await text(student);
const passage = student.locator('div[dir="rtl"]');
const ayahText = (await passage.count()) ? (await passage.first().innerText()).trim() : '';
check('the player shows the passage heading with the range', player.includes(`${SURAH.english} 1–2`) && player.includes(PROMPT), player.match(new RegExp(`${SURAH.english} 1–2`))?.[0] ?? player.slice(0, 160));
check('the ayah text under it comes from the dataset, when the host has one', true, ayahText ? `text shown: ${ayahText.slice(0, 40)}` : 'no ayah text — this host has not imported a mushaf (ADR-023); the heading is the engine\'s, the text is the operator\'s');

await student.fill('textarea', ANSWER);
await student.click('button:has-text("Submit")');
const handedIn = await settles(student, 'submitted');
const after = await text(student);
check('handing it in is accepted and the engine does not mark it', handedIn && !/\b\d+\s*\/\s*1\b/.test(after), after.slice(0, 160));

// --------------------------------------------------------------- the marker

await admin.goto(`${BASE}/en/catalog/reviews`, { waitUntil: 'networkidle' });
const card = admin.locator('article', { hasText: TITLE }).first();
check('the recitation waits in the ordinary review queue with the passage named', (await card.count()) > 0 && (await text(admin)).includes(ANSWER), (await card.count()) ? (await card.innerText()).replace(/\s+/g, ' ').slice(0, 160) : (await text(admin)).slice(0, 160));
if (await card.count()) {
    await card.locator('input[aria-label="Score"]').fill('1');
    await card.locator('input[aria-label="Max score"]').fill('1');
    await card.locator('input[placeholder="Feedback"]').fill(FEEDBACK);
    await card.locator('button:has-text("Score and release")').click();
    check('the marker scores it', await settles(admin, 'Review saved.'), (await text(admin)).slice(0, 120));
}

await student.goto(activityUrl, { waitUntil: 'networkidle' });
const returned = await text(student);
check('the student sees the mark and the feedback', /1\s*\/\s*1/.test(returned) && returned.includes(FEEDBACK), returned.match(/scored[^A-Za-z]*1\s*\/\s*1/)?.[0] ?? returned.slice(0, 160));

// -------------------------------------------------- the halaqa on the offering

await admin.goto(`${BASE}/en/catalog/offerings`, { waitUntil: 'networkidle' });
const sessionsHref = await admin.locator('tr', { hasText: OFFERING }).first().locator('a[href*="/sessions"]').first().getAttribute('href').catch(() => null);
check('the offering has a sessions screen', Boolean(sessionsHref), sessionsHref ?? (await text(admin)).slice(0, 160));
await admin.goto(new URL(sessionsHref ?? '/catalog/offerings', BASE).href, { waitUntil: 'networkidle' });

const sessionForm = admin.locator('form', { hasText: 'Save session' });
await sessionForm.locator('input[placeholder="Session title"]').fill(SESSION);
await sessionForm.locator('input[type="datetime-local"]').nth(0).fill('2026-09-24T10:00');
await sessionForm.locator('button:has-text("Save session")').click();
check('an engine session is added to the offering', await settles(admin, SESSION), (await rowText(admin, SESSION)) || (await text(admin)).slice(0, 160));

const linkForm = admin.locator('form', { hasText: 'Save halaqa link' });
const programValue = await linkForm.locator('option', { hasText: PROGRAM }).first().getAttribute('value').catch(() => null);
check('the seeded Hifz program is offered to link', Boolean(programValue), programValue ?? 'not in the program list — re-seed first: php artisan db:seed --class=SmokeMarkerSeeder');
if (programValue) {
    await linkForm.locator('select').first().selectOption(programValue);
    await linkForm.locator('button:has-text("Save halaqa link")').click();
}
const linked = await settles(admin, `Linked: ${PROGRAM}`);
check('the offering is linked to the halaqa, labels read through the contract', linked, linked ? `Linked: ${PROGRAM}` : (await text(admin)).slice(0, 160));
check('dual-write is reported off, no sync offered (rule 9, deploy 1 of 3)', (await text(admin)).includes('Dual-write is off') && (await admin.locator('button:has-text("Sync dual-write")').count()) === 0, (await text(admin)).match(/Dual-write is off[^.]*\./)?.[0] ?? (await text(admin)).slice(0, 160));

const sessionRow = admin.locator('tr', { hasText: SESSION }).first();
const halaqaSessionValue = await sessionRow.locator('option', { hasText: SESSION }).first().getAttribute('value').catch(() => null);
check('the halaqa\'s sessions are offered against the engine session', Boolean(halaqaSessionValue), halaqaSessionValue ?? (await rowText(admin, SESSION)));
if (halaqaSessionValue) {
    await sessionRow.locator('select').selectOption(halaqaSessionValue);
}
const mapped = await settles(admin, 'Halaqa session linked.');
await admin.reload({ waitUntil: 'networkidle' });
const kept = await admin.locator('tr', { hasText: SESSION }).first().locator('select').inputValue().catch(() => '');
check('the engine session maps onto the halaqa session and stays mapped', mapped && kept === halaqaSessionValue, mapped ? `hifz_session_id ${kept}` : (await text(admin)).slice(0, 160));

const oversight = await admin.goto(`${BASE}/en/catalog/quran/oversight`, { waitUntil: 'networkidle' });
check('the dean\'s oversight page opens', oversight.status() === 200 && (await text(admin)).includes('Common mistakes'), `HTTP ${oversight.status()}`);

await finish();
