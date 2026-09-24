/**
 * Does an Arabic skill activity, tagged to a letter, end up on the skill reports?
 *
 * Arabic Module A (`docs/ARABIC_A_SPEC.md`, SPEC §51.22–51.23) is three slices
 * — the letter/haraka reference, skill tags on the four activity patterns,
 * and the two skill reports — and no AI (rule 8). All three had tests and
 * the §2 row said UNVERIFIED, because nothing had walked them as one loop
 * (Arabic A audit D1, STATUS §5fl). `pronounce.mjs` walks Module B's
 * recording queue; this walks Module A, two logins:
 *
 *   1. the office adds a letter to the reference;
 *   2. the author builds a *reading* activity on the seeded `SMOKE-Course`,
 *      tagged with that letter, on the plain selection pattern — no new
 *      engine, the skill and the letter ride as metadata;
 *   3. the student answers it and is scored by the engine;
 *   4. the student's own Arabic report lists the activity under its skill
 *      with the attempt, and the office's report lists it with the letter.
 *
 * `SmokeMarkerSeeder::arabicCycle()` clears the activity, its attempts and
 * the letter before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/arabic.mjs
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
const LETTER = { key: 'smoke_letter', character: 'ڽ', name: 'SMOKE-Letter' };
const TITLE = 'SMOKE-Arabic-Activity';
const RIGHT = 'SMOKE-Letter-Right';

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
    // Give the row a moment to be there rather than counting at once: the
    // fourth staging run saw the saved activity's title on the page and no
    // row for it yet (STATUS §5fz).
    await row.waitFor({ state: 'attached', timeout: 3000 }).catch(() => {});

    return (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : '';
};

// -------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());

await admin.goto(`${BASE}/en/catalog/arabic`, { waitUntil: 'networkidle' });
if ((await rowText(admin, LETTER.name)) !== '') {
    check('there is a clean reference to walk', false, `${LETTER.name} already exists — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

// 1. a letter in the reference
const letterForm = admin.locator('form', { hasText: 'Save letter' });
await letterForm.locator('input[placeholder="Key"]').fill(LETTER.key);
await letterForm.locator('input[placeholder="ا"]').fill(LETTER.character);
await letterForm.locator('input[placeholder="Display name"]').fill(LETTER.name);
await letterForm.locator('button:has-text("Save letter")').click();
check('a letter is added to the reference', await settles(admin, LETTER.name) && (await rowText(admin, LETTER.name)).includes(LETTER.character), await rowText(admin, LETTER.name) || (await text(admin)).slice(0, 160));

// 2. a reading activity tagged with it, on the ordinary selection pattern
await admin.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const courseId = Number(((await admin.locator('tr', { hasText: COURSE }).locator('a', { hasText: COURSE }).first().getAttribute('href').catch(() => '')) ?? '').match(/courses\/(\d+)/)?.[1] ?? 0);
await admin.goto(`${BASE}/en/catalog/courses/${courseId}/activities`, { waitUntil: 'networkidle' });
const build = admin.locator('form', { hasText: 'Save activity' });
await build.locator('input[placeholder="Title"]').fill(TITLE);
await build.locator('select').nth(0).selectOption('selection');
await build.locator('input[placeholder="Activity type label"]').fill('reading');
await build.locator('select').nth(1).selectOption('reading');
const letterValue = await build.locator('select').nth(2).locator('option', { hasText: LETTER.name }).first().getAttribute('value').catch(() => null);
check('the builder offers the new letter as a tag', Boolean(letterValue), letterValue ?? 'not in the letter list');
if (letterValue) {
    await build.locator('select').nth(2).selectOption(letterValue);
}
await build.locator('input[placeholder="Max score"]').fill('1');
await build.locator('textarea.font-mono').nth(0).fill(JSON.stringify({
    prompt: `SMOKE-Arabic: which is the letter ${LETTER.character}?`,
    options: [{ id: 'a', label: RIGHT }, { id: 'b', label: 'SMOKE-Letter-Wrong' }],
    correct_ids: ['a'],
}));
await build.locator('button:has-text("Save activity")').click();
check('a reading activity is built with the skill and the letter', await settles(admin, TITLE) && /reading/.test(await rowText(admin, TITLE)), await rowText(admin, TITLE) || (await text(admin)).slice(0, 160));

// ------------------------------------------------------------- the student

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

await student.goto(`${BASE}/en/learn/courses/${courseId}`, { waitUntil: 'networkidle' });
const activityHref = await student.locator('li, tr', { hasText: TITLE }).locator('a[href*="/learn/activities/"]').first().getAttribute('href').catch(() => null);
check('the activity is on the course page', Boolean(activityHref), activityHref ?? (await text(student)).slice(0, 160));
await student.goto(new URL(activityHref ?? `/learn/courses/${courseId}`, BASE).href, { waitUntil: 'networkidle' });
check('the player shows the prompt with the letter', (await text(student)).includes(LETTER.character) && (await text(student)).includes(RIGHT), (await text(student)).slice(0, 160));

await student.locator('label', { hasText: RIGHT }).first().click();
await student.locator('button:has-text("Submit"), button[type=submit]').first().click();
await settles(student, 'Try again');
const marked = await text(student);
check('the engine scores it 1/1, no teacher and no AI', /scored/.test(marked) && /1\s*\/\s*1/.test(marked), marked.match(/scored[^A-Za-z]*1\s*\/\s*1/)?.[0] ?? marked.slice(0, 160));

// 3. the two reports
await student.goto(`${BASE}/en/learn/arabic-report`, { waitUntil: 'networkidle' });
const mine = await rowText(student, TITLE);
check('the student\'s Arabic report lists it under reading, with the attempt', mine.includes('reading') && /\b1\b/.test(mine), mine || (await text(student)).slice(0, 160));

await admin.goto(`${BASE}/en/catalog/arabic/reports`, { waitUntil: 'networkidle' });
const theirs = await rowText(admin, TITLE);
check('the office\'s Arabic report lists it with the letter and the attempt', theirs.includes('reading') && theirs.includes(LETTER.character) && /\b1\b/.test(theirs), theirs || (await text(admin)).slice(0, 160));

await finish();
