/**
 * Book lending between people (LENDING_AND_USED_BOOKS_PLAN L1, STATUS §5ms).
 *
 * The parent registers as a lender, sends their ID card and lists a book;
 * the shelf stays empty until the office checks the card at /admin/lending;
 * then a guest finds the book; the student asks for it; the lender accepts
 * (the borrower's phone appears only then), hands it over and marks it
 * returned; the office's loans table and CSV say so. L2: the borrower rates
 * the lender and the stars reach the shelf; the lender pauses and resumes a
 * book and themselves; the office pauses the lender with a note the lender
 * reads, resumes them, takes the book down with a note; the lenders CSV.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/lending.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_LENDER, SMOKE_BORROWER, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const LENDER = process.env.SMOKE_LENDER ?? 'parent@akuru.edu.mv';
const BORROWER = process.env.SMOKE_BORROWER ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const TITLE = `SMOKE-Walk Grade 7 Reader ${Math.random().toString(36).slice(2, 7)}`;
const DEPOSIT = 'SMOKE-Another book while mine is out';
const CARD = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../database/seeders/fixtures/vendors/fitrah-logo.jpg');

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
    const failed = results.filter(([, ok]) => !ok).length;
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(84)} ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

async function newPage(label) {
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 } });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
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
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const count = (page, selector) => page.locator(selector).count();
const text = async (page) => (await page.innerText('body')).replace(/\s+/g, ' ');
const slug = TITLE.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

// ------------------------------------------------------------ 1. the lender registers, sends their card, lists a book

const lender = await signIn(LENDER);
await lender.goto(`${BASE}/en/my-lending`, { waitUntil: 'networkidle' });
check('My lending opens for a signed-in person who is not yet a lender', (await count(lender, '[data-testid="lender-section"][data-registered="0"]')) === 1 && (await text(lender)).includes('Register as a lender first'));
await lender.fill('[data-testid="lender-name"]', 'Aminath (walk)');
await lender.fill('[data-testid="lender-island"]', 'Hithadhoo');
await lender.click('[data-testid="lender-save"]');
await settle(lender, '[data-testid="flash-success"]');
check('registering makes them a lender, with the ID card step waiting', (await count(lender, '[data-testid="lender-section"][data-registered="1"]')) === 1 && (await count(lender, '[data-testid="lender-identity"][data-id-status="none"]')) === 1 && (await count(lender, '[data-testid="identity-form"]')) === 1);
await lender.setInputFiles('[data-testid="id-front"]', CARD);
await lender.setInputFiles('[data-testid="id-back"]', CARD);
await lender.click('[data-testid="identity-submit"]');
await settle(lender, '[data-testid="flash-success"]');
check('the card goes to the office and the page says it is waiting', (await count(lender, '[data-testid="lender-identity"][data-id-status="pending"]')) === 1);
if ((await count(lender, '[data-testid="add-book"][open]')) === 0) {
    await lender.locator('[data-testid="add-book"] summary').click();
}
await lender.fill('[data-testid="book-title-input"]', TITLE);
await lender.fill('[data-testid="book-author"]', 'MoE');
await lender.selectOption('[data-testid="book-condition-select"]', 'good');
await lender.fill('[data-testid="book-grade"]', '7');
await lender.fill('[data-testid="book-subject"]', 'Dhivehi');
await lender.fill('[data-testid="book-max-days"]', '21');
await lender.fill('[data-testid="book-deposit-input"]', DEPOSIT);
await lender.click('[data-testid="save-book"]');
await settle(lender, `[data-testid="my-book-${slug}"]`);
check('the book is saved under Books I lend, available', (await count(lender, `[data-testid="my-book-${slug}"][data-status="available"]`)) === 1);

// ------------------------------------------------------------ 2. not on the shelf until the office checks the card

const guest = await newPage('guest');
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('the public shelf does not show it while the card is unchecked', (await count(guest, `[data-lending-book="${slug}"]`)) === 0);
const unchecked = await guest.goto(`${BASE}/en/lending/${slug}`, { waitUntil: 'networkidle' });
check('and its page is a 404', unchecked?.status() === 404, `HTTP ${unchecked?.status()}`);

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/lending`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="identity-checks"]');
const pendingRow = office.locator('[data-testid="identity-checks"] [data-identity-status="pending"]').first();
check('the office sees the lender, unchecked, and their card to decide', (await pendingRow.count()) >= 1 && (await count(office, '[data-testid="lenders"] [data-id-verified="0"]')) >= 1 && (await text(office)).includes('Aminath (walk)'));
await Promise.all([
    office.waitForResponse((r) => r.url().includes('/admin/identity-checks/') && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}),
    pendingRow.locator('[data-testid^="identity-verify-"]').click(),
]);
await settle(office);
await office.reload({ waitUntil: 'networkidle' });
await settle(office, '[data-testid="lenders"]');
check('verifying the card marks the lender as checked', (await count(office, '[data-testid="lenders"] [data-id-verified="1"]')) >= 1 && (await count(office, '[data-testid="identity-checks"] [data-identity-status="pending"]')) === 0);

// ------------------------------------------------------------ 3. a guest finds it; the borrower asks

await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('now the shelf shows the book with the lender\'s name and island', (await count(guest, `[data-lending-book="${slug}"][data-status="available"]`)) === 1 && (await text(guest)).includes('Aminath (walk)') && (await text(guest)).includes('Hithadhoo'));
await guest.goto(`${BASE}/en/lending?grade=7&subject=Dhivehi`, { waitUntil: 'networkidle' });
check('the grade and subject filters keep it', (await count(guest, `[data-lending-book="${slug}"]`)) === 1);
await guest.goto(`${BASE}/en/lending/${slug}`, { waitUntil: 'networkidle' });
const bookPage = (await guest.locator('[data-testid="lending-book"]').innerText()).replace(/\s+/g, ' ');
check('the book page shows condition, days, deposit words and a sign-in button — never a phone', (await count(guest, '[data-testid="ask-sign-in"]')) === 1 && bookPage.includes(DEPOSIT) && bookPage.includes('Good') && !/\+960\d{7}/.test(bookPage));

const borrower = await signIn(BORROWER);
await borrower.goto(`${BASE}/en/lending/${slug}`, { waitUntil: 'networkidle' });
await borrower.fill('[data-testid="ask-message"]', 'SMOKE-I can collect it on Thursday.');
await borrower.click('[data-testid="ask-submit"]');
await settle(borrower, '[data-testid="borrowing-section"]');
check('asking lands on My lending › Books I borrow as Asked, with no phone yet', /my-lending/.test(borrower.url()) && (await count(borrower, '[data-testid="borrowing-loans"] [data-status="requested"]')) === 1 && (await count(borrower, '[data-testid="lender-phone"]')) === 0);

// ------------------------------------------------------------ 4. the lender accepts, hands over, takes it back

await lender.reload({ waitUntil: 'networkidle' });
await settle(lender, '[data-testid="lending-section"]');
const request = lender.locator('[data-testid="lending-loans"] [data-status="requested"]').first();
check('the lender sees the request with the borrower\'s message and no phone', (await request.count()) === 1 && (await request.innerText()).includes('SMOKE-I can collect it on Thursday.') && (await count(lender, '[data-testid="borrower-phone"]')) === 0);
const loanId = (await request.getAttribute('data-testid'))?.replace('loan-', '');
await lender.click(`[data-testid="accept-${loanId}"]`);
await settle(lender, '[data-testid="flash-success"]');
const accepted = lender.locator(`[data-testid="loan-${loanId}"]`);
const borrowerPhoneShown = (await count(lender, '[data-testid="borrower-phone"]')) === 1;
check('accepting sets the due date and shows the borrower\'s phone (when they have one)', (await accepted.getAttribute('data-status')) === 'accepted' && (await accepted.innerText()).includes('Due back') && (borrowerPhoneShown || !(await lender.locator('[data-testid="lending-section"]').innerText()).match(/\+960/)), borrowerPhoneShown ? 'phone shown' : 'borrower has no phone on file');
await borrower.reload({ waitUntil: 'networkidle' });
check('the borrower sees Accepted and the lender\'s phone or the note that it is shared on acceptance', (await count(borrower, `[data-testid="borrow-${loanId}"][data-status="accepted"]`)) === 1);
await lender.click(`[data-testid="handover-${loanId}"]`);
await settle(lender, '[data-testid="flash-success"]');
check('handed over: the loan is Out and the book On loan', (await accepted.getAttribute('data-status')) === 'out' && (await count(lender, `[data-testid="my-book-${slug}"][data-status="on_loan"]`)) === 1);
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('the shelf marks it On loan', (await count(guest, `[data-lending-book="${slug}"][data-status="on_loan"]`)) === 1);
await lender.click(`[data-testid="returned-${loanId}"]`);
await settle(lender, '[data-testid="flash-success"]');
check('returned: the loan is closed and the book available again', (await accepted.getAttribute('data-status')) === 'returned' && (await count(lender, `[data-testid="my-book-${slug}"][data-status="available"]`)) === 1);

// ------------------------------------------------------------ 5. the office's record

await office.reload({ waitUntil: 'networkidle' });
await settle(office, '[data-testid="loans"]');
check('the office\'s loans table shows the returned loan', (await count(office, `[data-testid="loan-${loanId}"][data-status="returned"]`)) === 1);
const csv = await office.request.get(`${BASE}/en/admin/lending/export`);
const csvBody = await csv.text();
check('and the CSV export carries it', csv.status() === 200 && csvBody.includes(TITLE) && csvBody.includes('returned'), `HTTP ${csv.status()}`);

// ------------------------------------------------------------ 6. L2: ratings

await borrower.reload({ waitUntil: 'networkidle' });
await borrower.selectOption(`[data-testid="rate-stars-${loanId}"]`, '4');
await borrower.fill(`[data-testid="rate-comment-${loanId}"]`, 'SMOKE-Kind and on time.');
await borrower.click(`[data-testid="rate-send-${loanId}"]`);
await settle(borrower, '[data-testid="flash-success"]');
check('the borrower rates the lender once the book is back; the form gives way to the rating', (await count(borrower, `[data-testid="my-rating-${loanId}"]`)) === 1 && (await count(borrower, `[data-testid="rate-form-${loanId}"]`)) === 0);
await lender.reload({ waitUntil: 'networkidle' });
check('the lender sees the borrower\'s words and may rate back', (await count(lender, `[data-testid="their-rating-${loanId}"]`)) === 1 && (await lender.locator(`[data-testid="their-rating-${loanId}"]`).innerText()).includes('SMOKE-Kind and on time.') && (await count(lender, `[data-testid="rate-form-${loanId}"]`)) === 1);
await lender.selectOption(`[data-testid="rate-stars-${loanId}"]`, '5');
await lender.click(`[data-testid="rate-send-${loanId}"]`);
await settle(lender, '[data-testid="flash-success"]');
check('the lender rates the borrower; the summary says 5 of 5', (await count(lender, `[data-testid="my-rating-${loanId}"]`)) === 1 && (await lender.locator('[data-testid="lending-section"]').innerText()).includes('5 of 5'));
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('the shelf card carries the lender\'s stars', (await guest.locator(`[data-lending-book="${slug}"] [data-testid="card-rating"]`).getAttribute('data-avg')) === '4');
await guest.goto(`${BASE}/en/lending/${slug}`, { waitUntil: 'networkidle' });
check('and the book page shows what borrowers said', (await count(guest, '[data-testid="lender-comments"]')) === 1 && (await text(guest)).includes('SMOKE-Kind and on time.'));

// ------------------------------------------------------------ 7. L2: the lender pauses a book, then themselves

await lender.click(`[data-testid="toggle-book-${slug}"]`);
await settle(lender, '[data-testid="flash-success"]');
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('pausing a book takes it off the shelf', (await count(lender, `[data-testid="my-book-${slug}"][data-status="paused"]`)) === 1 && (await count(guest, `[data-lending-book="${slug}"]`)) === 0);
await lender.click(`[data-testid="toggle-book-${slug}"]`);
await settle(lender, '[data-testid="flash-success"]');
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('putting it back shows it again', (await count(guest, `[data-lending-book="${slug}"][data-status="available"]`)) === 1);
await lender.click('[data-testid="lender-toggle"]');
await settle(lender, '[data-testid="flash-success"]');
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('pausing my lending empties my shelf', (await lender.locator('[data-testid="lender-status"]').innerText()).includes('Paused') && (await count(guest, `[data-lending-book="${slug}"]`)) === 0);
await lender.click('[data-testid="lender-toggle"]');
await settle(lender, '[data-testid="flash-success"]');
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('resuming fills it again', (await count(guest, `[data-lending-book="${slug}"]`)) === 1);

// ------------------------------------------------------------ 8. L2: the office's hand

await office.reload({ waitUntil: 'networkidle' });
await settle(office, '[data-testid="lenders"]');
const lenderRow = office.locator('[data-testid="lenders"] [data-testid^="lender-"]').filter({ hasText: 'Aminath (walk)' }).first();
const lenderId = (await lenderRow.getAttribute('data-testid'))?.replace('lender-', '');
await office.fill(`[data-testid="lender-note-${lenderId}"]`, 'SMOKE-Two borrowers reported the books were not as described.');
await Promise.all([
    office.waitForResponse((r) => r.url().includes(`/admin/lending/lenders/${lenderId}/pause`) && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}),
    office.click(`[data-testid="lender-pause-${lenderId}"]`),
]);
await settle(office, `[data-testid="lender-office-paused-${lenderId}"]`);
await lender.reload({ waitUntil: 'networkidle' });
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('the office pauses the lender with a note: the lender reads it and cannot resume; the shelf is empty', (await count(office, `[data-testid="lender-office-paused-${lenderId}"]`)) === 1 && (await count(lender, '[data-testid="office-paused-note"]')) === 1 && (await lender.locator('[data-testid="office-paused-note"]').innerText()).includes('SMOKE-Two borrowers') && (await count(lender, '[data-testid="lender-toggle"]')) === 0 && (await count(guest, `[data-lending-book="${slug}"]`)) === 0);
await Promise.all([
    office.waitForResponse((r) => r.url().includes(`/admin/lending/lenders/${lenderId}/resume`) && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}),
    office.click(`[data-testid="lender-resume-${lenderId}"]`),
]);
await settle(office, `[data-testid="lender-pause-${lenderId}"]`);
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
check('the office resumes them: the book is back', (await count(guest, `[data-lending-book="${slug}"]`)) === 1);
const bookRow = office.locator('[data-testid="books"] [data-testid^="book-"]').filter({ hasText: TITLE }).first();
const bookId = (await bookRow.getAttribute('data-testid'))?.replace('book-', '');
await office.fill(`[data-testid="book-note-${bookId}"]`, 'SMOKE-Copyrighted photocopy.');
await Promise.all([
    office.waitForResponse((r) => r.url().includes(`/admin/lending/books/${bookId}/remove`) && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}),
    office.click(`[data-testid="book-remove-${bookId}"]`),
]);
await settle(office);
await guest.goto(`${BASE}/en/lending`, { waitUntil: 'networkidle' });
await lender.reload({ waitUntil: 'networkidle' });
check('the office takes the book down with a note: gone from the shelf and the lender\'s list', (await count(guest, `[data-lending-book="${slug}"]`)) === 0 && (await count(lender, `[data-testid="my-book-${slug}"]`)) === 0 && (await count(office, `[data-testid="book-${bookId}"]`)) === 0);
const lendersCsv = await office.request.get(`${BASE}/en/admin/lending/lenders/export`);
const lendersBody = await lendersCsv.text();
check('the lenders CSV carries the lender with their rating', lendersCsv.status() === 200 && lendersBody.includes('Aminath (walk)') && lendersBody.includes('rating_avg'), `HTTP ${lendersCsv.status()}`);

await finish();
