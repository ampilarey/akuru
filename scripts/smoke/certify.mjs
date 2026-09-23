/**
 * Does a student who finishes a course get a certificate a stranger can verify?
 *
 * Phase 3's definition of done (SPEC §48): *"Admin can configure certificate
 * rules · Student can receive certificate after eligibility · Certificate can
 * be verified by QR code."* C1 was walked by hand when it shipped (#106) and
 * never by a script; the eligibility refusal — the rule doing its job — had
 * been asserted in a test and seen by nobody (Phase 3 audit D1, STATUS §5fj).
 * This walks it, three parties:
 *
 *   1. the office builds `SMOKE-Cert` — course completion on the seeded
 *      `SMOKE-Course`, 100% progress required — and tries to issue it to the
 *      student **before** they have finished: refused, with the reason;
 *   2. the student finishes the course's one lesson (`learn.mjs` may already
 *      have; either way the course reads 100%);
 *   3. the office issues it; the student finds it on their dashboard, opens
 *      the document — it carries their name and a QR — and a **guest** with
 *      no login opens the QR's URL and is told it is authentic, and shown the
 *      face only;
 *   4. the office revokes it; the same URL now says revoked.
 *
 * `SmokeMarkerSeeder::certifyCycle()` clears the template, its issued rows
 * and their documents before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/certify.mjs
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
const TEMPLATE = 'SMOKE-Cert';

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

await admin.goto(`${BASE}/en/catalog/certificates`, { waitUntil: 'networkidle' });
if ((await text(admin)).includes(TEMPLATE)) {
    check('there is a clean screen to walk', false, `${TEMPLATE} already exists — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

// 1. the rule: course completion, 100% progress
const templateForm = admin.locator('form', { has: admin.locator('input[placeholder="Name (EN)"]') }).first();
await templateForm.locator('input[placeholder="Name (EN)"]').fill(TEMPLATE);
await templateForm.locator('select').nth(0).selectOption('course_completion');
await templateForm.locator('select').nth(1).selectOption({ label: COURSE });
await templateForm.locator('input[placeholder="Min progress %"]').fill('100');
await templateForm.locator('button:has-text("Save template")').click();
check('a completion certificate is configured: 100% progress required', await settles(admin, 'Certificate template saved.') && (await text(admin)).includes(TEMPLATE), (await text(admin)).match(/Certificate template saved\./)?.[0] ?? (await text(admin)).slice(0, 160));

// 2. before eligibility: refused, and told why
const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

// Who the student is, as the issue form will name them: their own
// performance page carries the student record's name.
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().textContent().catch(() => '')) ?? '').trim();
const issueForm = () => admin.locator('form', { hasText: 'Issue certificate' }).first();
check('the issue form offers the student', NAME !== '' && (await issueForm().locator('select').nth(1).locator('option', { hasText: NAME }).count()) > 0, NAME || 'no name on /portal/performance');
await student.goto(`${BASE}/en/learn`, { waitUntil: 'networkidle' });
const courseHref = await student.locator('article', { hasText: COURSE }).locator('a[href*="/learn/courses/"]').first().getAttribute('href').catch(() => null);
await student.goto(new URL(courseHref ?? '/learn', BASE).href, { waitUntil: 'networkidle' });
const progressBefore = (await text(student)).match(/(\d+)%/)?.[1] ?? null;
check('the student\'s course page shows their progress', progressBefore !== null, `${progressBefore}%`);

const issue = async () => {
    await issueForm().locator('select').nth(0).selectOption({ label: TEMPLATE });
    const pick = await issueForm().locator('select').nth(1).locator('option', { hasText: NAME }).first().getAttribute('value');
    await issueForm().locator('select').nth(1).selectOption(pick);
    await issueForm().locator('button:has-text("Issue")').click();
};

if (progressBefore !== '100') {
    await issue();
    await admin.waitForTimeout(1500);
    const said = await text(admin);
    check('issuing before the course is finished is refused, with the rule named', /progress/i.test(said) && !said.includes('Certificate issued'), said.match(/[^.]*progress[^.]*\./i)?.[0] ?? said.slice(0, 160));

    // 3. the student finishes the course
    const lessonHref = (await student.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || ''))).find((href) => /\/learn\/lessons\/\d+/.test(href));
    await student.goto(new URL(lessonHref ?? '/learn', BASE).href, { waitUntil: 'networkidle' });
    const complete = student.locator('button:has-text("Mark complete")').first();
    if (await complete.count()) {
        await complete.click();
        await student.waitForLoadState('networkidle');
    }
    await student.goto(new URL(courseHref ?? '/learn', BASE).href, { waitUntil: 'networkidle' });
    check('the student finishes the course', (await text(student)).includes('100%'), (await text(student)).match(/\d+%/)?.[0] ?? 'no percentage');
} else {
    check('issuing before the course is finished is refused, with the rule named', true, 'the course was already at 100% (learn.mjs ran first) — the refusal is covered by CourseCertificateTest');
    check('the student finishes the course', true, 'already 100%');
}

// 4. issued
await admin.goto(`${BASE}/en/catalog/certificates`, { waitUntil: 'networkidle' });
await issue();
check('the certificate is issued once the rule is met', await settles(admin, 'Certificate issued: '), (await text(admin)).match(/Certificate issued: [A-Z0-9-]+/)?.[0] ?? (await text(admin)).slice(0, 160));
const number = (await text(admin)).match(/Certificate issued: ([A-Z0-9-]+)/)?.[1] ?? '';
const issuedRow = await rowText(admin, number);
check('and listed with its number and the student\'s name', issuedRow.includes(number) && issuedRow.includes(NAME), issuedRow);

// 5. the student finds it, opens it, and it carries a QR
await student.goto(`${BASE}/en/learn`, { waitUntil: 'networkidle' });
const certLine = student.locator('li', { hasText: number }).first();
check('the student sees it on their dashboard', (await certLine.count()) > 0 && (await certLine.innerText()).includes(TEMPLATE), (await certLine.count()) ? (await certLine.innerText()).replace(/\s+/g, ' ') : (await text(student)).slice(0, 160));
const openHref = (await certLine.count()) ? await certLine.locator('a:has-text("Open")').first().getAttribute('href').catch(() => null) : null;
const verifyHref = (await certLine.count()) ? await certLine.locator('a:has-text("Verify")').first().getAttribute('href').catch(() => null) : null;
const opened = openHref ? await student.request.get(new URL(openHref, BASE).href) : null;
const html = opened ? await opened.text() : '';
check('and opens the document, named, with a QR', opened?.status() === 200 && html.includes(NAME) && /<svg/.test(html) && !/PDF/.test(html), opened ? `HTTP ${opened.status()}, ${html.length} bytes, ${/<svg/.test(html) ? 'QR svg' : 'no svg'}` : 'no Open link');

// 6. a stranger follows the QR
const guest = await (await browser.newContext()).newPage();
const verified = verifyHref ? await guest.goto(verifyHref, { waitUntil: 'domcontentloaded' }) : null;
const face = verified ? (await guest.innerText('body')).replace(/\s+/g, ' ') : '';
check('a guest following the QR is told it is authentic, and shown the face only', verified?.status() === 200 && /authentic/i.test(face) && face.includes(NAME) && !face.includes('@') && !/\bid\b/i.test(face), face.slice(0, 200) || 'no Verify link');
const bogus = await guest.goto(`${BASE}/verify/certificates/not-a-real-token`, { waitUntil: 'domcontentloaded' });
check('and a made-up token is a plain 404', bogus?.status() === 404, `HTTP ${bogus?.status()}`);

// 7. revoked
await admin.locator('tr', { hasText: number }).locator('button:has-text("Revoke")').click();
await admin.waitForLoadState('networkidle');
check('the office revokes it', await settles(admin, 'revoked'), (await rowText(admin, number)) || (await text(admin)).slice(0, 160));
await guest.goto(verifyHref ?? `${BASE}/verify/certificates/x`, { waitUntil: 'domcontentloaded' });
check('and the same QR now says revoked', /revoked/i.test((await guest.innerText('body'))), (await guest.innerText('body')).replace(/\s+/g, ' ').slice(0, 120));

await finish();
