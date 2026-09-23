/**
 * Can the office record what a family has agreed to — and does the answer
 * gate what the public sees?
 *
 * S1.3 (`docs/S1_SPEC.md`): consent is per type, revocable, and its history
 * is kept — a change is a new row, never an update in place. The
 * `photo_media_use` type gates the use of a pupil's photo on the website.
 * `ConsentTest` proved the rows; the sweep saw a seeded one; nobody had
 * recorded a consent from the screen or watched the gate close (STATUS
 * §5fv). This walks it, one office login and the public site:
 *
 *   1. the office finds the pupil in the directory by their whole name,
 *      opens the Consents tab, and the seeded photo consent is there;
 *   2. the public achievements page shows the pupil's award with the
 *      photo on file — the seeded consent holds;
 *   3. the office grants a marketing consent: one row, yes, from admin;
 *   4. revokes it: a second row, revoked — and the first still says yes;
 *   5. revokes it again: nothing changes, no third row (recording the same
 *      answer twice is a no-op, not a duplicate);
 *   6. revokes the photo consent — and the public page no longer offers the
 *      photo, though the award and the name are still there;
 *   7. grants it back, so the seeded state is what the next walk finds.
 *
 * `SmokeMarkerSeeder::consentCycle()` clears the office's rows for the two
 * types and plants the pupil's photo document before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/consent.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const PHOTO = 'photo_media_use';
const MARKETING = 'marketing_messages';

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

// The consents table, newest first: one line per row, `type granted source at`.
const consentRows = async (page, type) => {
    const rows = page.locator('tr', { hasText: type });
    const out = [];
    for (let i = 0; i < (await rows.count()); i += 1) {
        out.push((await rows.nth(i).innerText()).replace(/\s+/g, ' ').trim());
    }

    return out;
};

// A fresh load first. The flash from the last record stays on the screen
// after Inertia re-renders, so waiting for it would return at once; and a
// select changed while that re-render is still landing is reset to its
// default before the click posts — the first version of this walk "revoked"
// a consent by posting Grant again. Loaded fresh there is no flash to wait
// for until this record has landed.
async function record(page, type, granted) {
    await page.goto(`${studentUrl}?tab=consents`, { waitUntil: 'networkidle' });
    const form = page.locator('form', { has: page.locator('select[name="consent_type"]') });
    await form.locator('select[name="consent_type"]').selectOption(type);
    await form.locator('select[name="granted"]').selectOption(granted ? '1' : '0');
    await form.locator('button:has-text("Record")').click();

    return settles(page, 'Consent recorded.');
}

let studentUrl = '';

// ---------------------------------------------------------------- the pupil

const student = await signIn(STUDENT);
check('the pupil signs in', !student.url().includes('/login'), student.url());
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the pupil has a name', NAME.length > 0, NAME || 'no h2 on /portal/performance');

// ---------------------------------------------------------------- the office

// 1. the directory, by whole name, to the Consents tab
const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());
await admin.goto(`${BASE}/en/people/students?search=${encodeURIComponent(NAME)}`, { waitUntil: 'networkidle' });
const link = admin.locator('tr', { hasText: NAME }).locator('a[href*="/people/students/"]').first();
check('the directory finds the pupil by their whole name', (await link.count()) > 0, (await text(admin)).slice(0, 160));
if (!(await link.count())) {
    await finish();
}
studentUrl = new URL(await link.getAttribute('href'), BASE).href;
await admin.goto(`${studentUrl}?tab=consents`, { waitUntil: 'networkidle' });
// The name is the screen's title, above `main`; the tab's own text starts at the pupil number.
check('the Consents tab opens on the pupil', (await admin.innerText('body')).includes(NAME) && (await admin.locator('select[name="consent_type"]').count()) > 0, (await text(admin)).slice(0, 160));

const seeded = await consentRows(admin, PHOTO);
check('the seeded photo consent is on it, granted', seeded.some((row) => /\byes\b/.test(row)), seeded.join(' / ') || 'no photo_media_use row');

if ((await consentRows(admin, MARKETING)).length) {
    check('there is a clean marketing consent to walk', false, 'a marketing_messages row is already there — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder');
    await finish();
}

// 2. the gate, open: the public page offers the photo
const site = await newPage('public');
const achievements = await site.goto(`${BASE}/en/achievements`, { waitUntil: 'networkidle' });
const card = () => site.locator('article', { hasText: NAME }).first();
check('the public achievements page lists the pupil\'s award', achievements.status() === 200 && (await card().count()) > 0, `HTTP ${achievements.status()}: ${(await text(site)).slice(0, 160)}`);
check('and offers the photo while consent stands', (await card().count()) > 0 && /Photo on file/.test(await card().innerText()), (await card().count()) ? (await card().innerText()).replace(/\s+/g, ' ') : 'no card');

// 3. grant
check('a marketing consent is granted from the screen', await record(admin, MARKETING, true), (await text(admin)).slice(0, 160));
let rows = await consentRows(admin, MARKETING);
check('one row: yes, from admin', rows.length === 1 && /\byes\b/.test(rows[0]) && /\badmin\b/.test(rows[0]), rows.join(' / '));

// 4. revoke — history, not an update
check('it is revoked from the screen', await record(admin, MARKETING, false), (await text(admin)).slice(0, 160));
rows = await consentRows(admin, MARKETING);
check('two rows now: the newest revoked, the first still yes', rows.length === 2 && /\brevoked\b/.test(rows[0]) && /\byes\b/.test(rows[1]), rows.join(' / '));

// 5. the same answer twice is a no-op
check('revoking again is accepted', await record(admin, MARKETING, false), (await text(admin)).slice(0, 160));
rows = await consentRows(admin, MARKETING);
check('and adds no row', rows.length === 2, `${rows.length} rows: ${rows.join(' / ')}`);

// 6. the gate, closed
check('the photo consent is revoked', await record(admin, PHOTO, false), (await text(admin)).slice(0, 160));
await site.reload({ waitUntil: 'networkidle' });
check('the public page keeps the award and the name', (await card().count()) > 0, (await text(site)).slice(0, 160));
check('but no longer offers the photo', (await card().count()) > 0 && !/Photo on file/.test(await card().innerText()), (await card().count()) ? (await card().innerText()).replace(/\s+/g, ' ') : 'no card');

// 7. back to the seeded state
check('the photo consent is granted again', await record(admin, PHOTO, true), (await text(admin)).slice(0, 160));
await site.reload({ waitUntil: 'networkidle' });
check('and the photo is offered again', (await card().count()) > 0 && /Photo on file/.test(await card().innerText()), (await card().count()) ? (await card().innerText()).replace(/\s+/g, ' ') : 'no card');

await finish();
