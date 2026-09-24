/**
 * Can a family ask the school for something and get an answer?
 *
 * E5's acceptance line (`docs/EDUPAGE_FEATURES_PLAN.md`): "a parent files a
 * leave request, the class teacher approves it, both are notified … rejected
 * requests state a reason; CSV export." The staff half — leave, approval,
 * cover — is `hr.mjs`. Nobody had walked the family half (STATUS §5fw).
 * Three logins:
 *
 *   1. the parent files a request about their child, from the screen, and
 *      sees it pending with the child's name on it;
 *   2. the class teacher and the office are each told in their notification
 *      centre that a request is waiting;
 *   3. the office opens it — it says who asked and about whom — tries to
 *      reject it with no reason and is refused, rejects it with one, and the
 *      card shows the decision with its date and reason;
 *   4. the parent sees the rejection and its reason, and is told in their
 *      notification centre;
 *   5. the parent files a second request; the office approves it; the parent
 *      sees it approved and is told;
 *   6. the CSV carries both.
 *
 * Who reviews: the office. Teachers and parents hold `requests.submit` only;
 * the acceptance line's "class teacher approves" is an approver-rule the
 * owner has not decided (§5fw), so the class teacher is *told* here and
 * the office decides.
 *
 * `SmokeMarkerSeeder::requestsCycle()` clears the family's requests and the
 * notices they raised before each run.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/requests.mjs
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

const FIRST = 'SMOKE-Family-Request: away for a family wedding';
const SECOND = 'SMOKE-Family-Request-2: leaving early on Thursday';
const REJECTION = 'SMOKE-Rejected: term exams that week';

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

const card = (page, needle) => page.locator('section', { hasText: needle }).first();
const cardText = async (page, needle) => ((await card(page, needle).count()) ? (await card(page, needle).innerText()).replace(/\s+/g, ' ') : '');
// The school's own date, not the walker's: the app keeps Indian/Maldives
// time, and the fourth staging run, walked from a UTC host after Maldives
// midnight, looked for yesterday's date on today's cards (STATUS §5fz).
const TZ = process.env.SMOKE_TZ ?? 'Indian/Maldives';
const isoDaysFromNow = (days) => new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(Date.now() + days * 86400000));
const today = isoDaysFromNow(0);

// Every screen is loaded fresh before a post: the flash from the last post
// stays on the screen after Inertia re-renders, so waiting for it again would
// return at once and read the page before the new post has landed.
async function file(page, reason, childName) {
    await page.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
    const form = page.locator('form', { hasText: 'Submit request' });
    await form.locator('label:has-text("Type") select').selectOption('parent_general');
    const regarding = form.locator('label:has-text("Regarding") select');
    if (await regarding.count()) {
        await regarding.selectOption({ label: childName });
    }
    await form.locator('input[type=date]').nth(0).fill(isoDaysFromNow(7));
    await form.locator('input[type=date]').nth(1).fill(isoDaysFromNow(8));
    await form.locator('label:has-text("Reason") input').fill(reason);
    await form.locator('button:has-text("Submit request")').click();

    return settles(page, 'Request submitted.');
}

async function review(page, reason, status, notes) {
    await page.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
    const item = card(page, reason);
    await item.locator('select').selectOption(status);
    await item.locator('input[placeholder="Notes"]').fill(notes);
    await item.locator('button:has-text("Review")').click();
}

// ---------------------------------------------------------------- the pupil

const student = await signIn(STUDENT);
check('the pupil signs in', !student.url().includes('/login'), student.url());
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the pupil has a name', NAME.length > 0, NAME || 'no h2 on /portal/performance');

// ---------------------------------------------------------------- the parent

// 1. a request about the child
const parent = await signIn(PARENT);
check('the parent signs in', !parent.url().includes('/login'), parent.url());
await parent.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
if ((await text(parent)).includes('SMOKE-Family-Request')) {
    check('there is a clean slate to walk', false, 'a SMOKE-Family-Request is already there — left over from an earlier run. Re-seed first: php artisan db:seed --class=SmokeMarkerSeeder');
    await finish();
}
const offered = await parent.locator('form label:has-text("Type") select option').allTextContents();
check('the family is offered family types, not staff leave', offered.length > 0 && !offered.some((option) => /leave/i.test(option)), offered.join(', ') || 'no type options');
check('and is asked which child it is about', (await parent.locator('form label:has-text("Regarding") select option', { hasText: NAME }).count()) > 0, (await parent.locator('form label:has-text("Regarding") select option').allTextContents()).join(', ') || 'no Regarding select');

check('the parent files a request from the screen', await file(parent, FIRST, NAME), (await text(parent)).slice(0, 160));
let mine = await cardText(parent, FIRST);
check('it is pending, and names the child', /pending/i.test(mine) && mine.includes(`about ${NAME}`) && mine.includes(`Submitted ${today}`), mine || (await text(parent)).slice(0, 160));

// ------------------------------------------------ the class teacher is told

const teacher = await signIn(TEACHER);
check('the class teacher signs in', !teacher.url().includes('/login'), teacher.url());
await teacher.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the class teacher is told a request is waiting', (await text(teacher)).includes('New request from') && (await text(teacher)).includes(FIRST), (await text(teacher)).slice(0, 200));

// ---------------------------------------------------------------- the office

const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());
await admin.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the office is told too', (await text(admin)).includes('New request from') && (await text(admin)).includes(FIRST), (await text(admin)).slice(0, 200));

// 3. who asked, about whom; a rejection needs a reason
await admin.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
let theirs = await cardText(admin, FIRST);
check('the office sees who asked and about whom', /From .+ · Submitted/.test(theirs) && theirs.includes(`about ${NAME}`), theirs || (await text(admin)).slice(0, 160));

await review(admin, FIRST, 'rejected', '');
check('a rejection with no reason is refused, and says so', await settles(admin, 'Say why'), (await cardText(admin, FIRST)) || (await text(admin)).slice(0, 160));
theirs = await cardText(admin, FIRST);
check('and the request is still pending', /pending/i.test(theirs), theirs);

await review(admin, FIRST, 'rejected', REJECTION);
check('a rejection with a reason goes through', await settles(admin, 'Request reviewed.'), (await text(admin)).slice(0, 160));
theirs = await cardText(admin, FIRST);
check('the card shows the decision, its date and the reason', /rejected/i.test(theirs) && theirs.includes(`on ${today}`) && theirs.includes(REJECTION), theirs);

// 4. the family sees the answer
await parent.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
mine = await cardText(parent, FIRST);
check('the parent sees the rejection and its reason', /rejected/i.test(mine) && mine.includes(REJECTION), mine || (await text(parent)).slice(0, 160));
await parent.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('and is told in their notification centre, reason included', /rejected/i.test(await text(parent)) && (await text(parent)).includes(REJECTION), (await text(parent)).slice(0, 200));

// 5. a second request, approved
check('the parent files a second request', await file(parent, SECOND, NAME), (await text(parent)).slice(0, 160));
await review(admin, SECOND, 'approved', '');
check('the office approves it', await settles(admin, 'Request reviewed.'), (await text(admin)).slice(0, 160));
await parent.goto(`${BASE}/en/academics/requests`, { waitUntil: 'networkidle' });
mine = await cardText(parent, SECOND);
check('the parent sees it approved, with the date', /approved/i.test(mine) && mine.includes(`on ${today}`), mine || (await text(parent)).slice(0, 160));
await parent.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('and is told it was approved', /approved/i.test(await text(parent)), (await text(parent)).slice(0, 200));

// 6. the CSV
const csv = await admin.request.get(`${BASE}/en/academics/requests/export`);
const body = await csv.text();
check('the CSV carries both requests with their decisions', csv.status() === 200 && body.includes(FIRST) && body.includes(SECOND) && body.includes(REJECTION), `HTTP ${csv.status()}, ${body.split('\n').length} lines`);

await finish();
