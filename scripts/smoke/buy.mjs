/**
 * Can a student buy a course — and does paying open it?
 *
 * Phase 4's definition of done (SPEC §49): *"Paid courses/offerings can be
 * sold · Payment status controls access · Students cannot access paid
 * content without eligibility · Coupons."* `money.mjs` walks the office's
 * side — a manual payment against a pending enrolment, and the refund —
 * and `register.mjs` the stranger's free path. Nobody had walked the
 * **student's own purchase**: the price on the catalog, a coupon, the
 * wallet, and the lesson that was locked before and open after (Phase 4
 * audit D1, STATUS §5fk). BML itself cannot be walked anywhere until the
 * owner supplies a webhook secret (`OWNER_ACTIONS` item 2); the wallet is
 * the one way money moves today, and it is the path this walks. Two
 * logins:
 *
 *   1. the office creates a 10% coupon `SMOKE-OFF`;
 *   2. the student reads their wallet, finds `SMOKE-Wallet-Course` priced
 *      at MVR 100 in the catalog, and is refused the lesson (403) before
 *      paying — the course page says preview only;
 *   3. the student enters the coupon and pays with the wallet: enrolled at
 *      once, no gateway; the lesson opens; the wallet is down by 90 and
 *      the ledger names the course.
 *
 * `SmokeMarkerSeeder::buyCycle()` keeps the course and its lesson, clears
 * the student's enrolment on it and the coupon, and tops the student's
 * wallet back up.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/buy.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const COURSE = 'SMOKE-Wallet-Course';
const BODY = 'SMOKE-Wallet-Lesson-Body';
const CODE = 'SMOKE-OFF';
const PRICE = 100;
const DISCOUNTED = 90;

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

const balanceOf = async (page) => {
    await page.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
    const m = (await page.innerText('body')).match(/MVR\s*([\d,]+\.\d\d)/);

    return m ? Number(m[1].replace(/,/g, '')) : null;
};

// -------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());

await admin.goto(`${BASE}/en/admin/commerce`, { waitUntil: 'networkidle' });
if ((await rowText(admin, CODE)) !== '') {
    check('there is a clean coupon list to walk', false, `${CODE} already exists — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder`);
    await finish();
}

const couponForm = admin.locator('form', { has: admin.locator('input[placeholder="CODE"]') }).first();
await couponForm.locator('input[placeholder="CODE"]').fill(CODE);
await couponForm.locator('select').first().selectOption('percentage');
await couponForm.locator('input[placeholder="Value"]').fill('10');
await couponForm.locator('input[placeholder="Name"]').fill('SMOKE ten percent off');
await couponForm.locator('button:has-text("Save")').click();
check('a 10% coupon is created', await settles(admin, 'Discount code saved.') && (await rowText(admin, CODE)).includes(CODE), await rowText(admin, CODE) || (await text(admin)).slice(0, 160));

// The course's id and its lesson's, off the catalog and outline the office
// works from: a learner who has not paid is shown no lesson links at all,
// so the lock has to be tried against a URL the walk knows some other way.
await admin.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const courseId = Number(((await admin.locator('tr', { hasText: COURSE }).locator('a', { hasText: COURSE }).first().getAttribute('href').catch(() => '')) ?? '').match(/courses\/(\d+)/)?.[1] ?? 0);
await admin.goto(`${BASE}/en/catalog/courses/${courseId}/outline`, { waitUntil: 'networkidle' });
const lessonId = Number(((await admin.locator('a[href*="/catalog/player/"]').first().getAttribute('href').catch(() => '')) ?? '').match(/player\/(\d+)/)?.[1] ?? 0);
const lessonHref = lessonId > 0 ? `/en/learn/lessons/${lessonId}` : null;
check('the priced course and its lesson are in the catalog', courseId > 0 && lessonId > 0, `course ${courseId}, lesson ${lessonId}`);

// ------------------------------------------------------------- the student

const student = await signIn(STUDENT);
check('the student signs in', !student.url().includes('/login'), student.url());

const before = await balanceOf(student);
check('the student has a wallet balance to spend', before !== null && before >= PRICE, before === null ? 'no balance on /my-wallet' : `MVR ${before.toFixed(2)}`);

await student.goto(`${BASE}/en/learn/catalog`, { waitUntil: 'networkidle' });
const row = student.locator('tr', { hasText: COURSE }).first();
check('the course is offered at its price', (await row.count()) > 0 && (await row.innerText()).includes(`MVR ${PRICE}`), (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ') : (await text(student)).slice(0, 160));

// Before paying: the course is preview only and the lesson is refused.
await student.goto(`${BASE}/en/learn/courses/${courseId}`, { waitUntil: 'networkidle' });
const previewPage = await text(student);
check('before paying, the course page is preview only, with no lesson to open', /Preview only/i.test(previewPage) && !/\/learn\/lessons\/\d+/.test(await student.content()), previewPage.slice(0, 160));
const locked = lessonHref ? await student.request.get(new URL(lessonHref, BASE).href, { maxRedirects: 0 }) : null;
check('and the lesson is refused', locked !== null && locked.status() === 403, locked ? `HTTP ${locked.status()} ${lessonHref}` : 'no lesson link on the course page');

// Pay: coupon, then wallet.
await student.goto(`${BASE}/en/learn/catalog`, { waitUntil: 'networkidle' });
await row.locator('input[placeholder="Discount code"]').fill(CODE);
await row.locator('button:has-text("Pay with wallet")').click();
check('paying from the wallet with the coupon enrols the student at once', await settles(student, 'you are enrolled') && /\/learn\/courses\/\d+/.test(student.url()), `${student.url()} ${(await text(student)).slice(0, 80)}`);

const opened = lessonHref ? await student.request.get(new URL(lessonHref, BASE).href) : null;
check('and the lesson now opens', opened?.status() === 200 && (await opened.text()).includes(BODY), opened ? `HTTP ${opened.status()}` : 'no lesson link');

const after = await balanceOf(student);
check(`the wallet is down by MVR ${DISCOUNTED} (${PRICE} less 10%)`, before !== null && after !== null && Math.abs(before - after - DISCOUNTED) < 0.005, `MVR ${before} → MVR ${after}`);
const ledger = (await student.innerText('body')).replace(/\s+/g, ' ');
check('and the ledger carries the purchase', /debit purchase −90\.00/.test(ledger), ledger.match(/debit purchase[^A-Za-z]{0,30}/)?.[0] ?? ledger.slice(0, 160));

// The office's record of the sale, and the coupon used once.
await admin.goto(`${BASE}/en/admin/enrollments`, { waitUntil: 'networkidle' });
const sale = await rowText(admin, COURSE);
check('the office sees the enrolment active and its payment confirmed', /\bActive\b/i.test(sale) && /Confirmed/i.test(sale), sale || (await text(admin)).slice(0, 160));

await finish();
