/**
 * Can a reader read a protected book and keep their place, and does a gift
 * card become wallet money?
 *
 * The L-track's three walks cover the writer (`library.mjs`), the research
 * gate (`peer-review.mjs`) and the writer's money (`earnings.mjs`). The
 * reader's own half — L2's protected reader and L4's gift card — had tests
 * and no walk (L-track audit D1, STATUS §5fo). Two logins and a guest:
 *
 *   1. the office issues a gift card and is shown the code exactly once;
 *   2. the reader redeems it and their wallet is up by that amount, on a
 *      ledger row that says where the money came from;
 *   3. a guest is sent to sign in for a sign-in-only book; the reader opens
 *      it, one page at a time, with their own name in the watermark and no
 *      download path;
 *   4. they bookmark a page and leave; My Library offers to continue from
 *      where they stopped and lists the bookmark; the last page marks it
 *      completed;
 *   5. a second book, `SMOKE-Primer-PDF`, has no body — its pages were made
 *      from the PDF the office uploaded — and reads the same way, page by
 *      page, watermarked, with its Arabic and Dhivehi in reading order.
 *
 * `SmokeMarkerSeeder::readerCycle()` re-plants `SMOKE-Primer` and
 * `SMOKE-Primer-PDF` and clears the reader's progress and bookmarks. The gift card and its credit are money and
 * stay (rule 12): each run issues a fresh card.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/reader.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const SLUG = 'smoke-primer';
const TITLE = 'SMOKE-Primer';
const PDF_SLUG = 'smoke-primer-pdf';
const PDF_TITLE = 'SMOKE-Primer-PDF';
const UPLOAD_TITLE = 'SMOKE-Primer-Upload';
const PDF_FILE = new URL('../../database/seeders/fixtures/smoke-primer.pdf', import.meta.url).pathname;
const AMOUNT = '25.00';

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

const money = (value) => Number(String(value ?? '').replace(/[^0-9.]/g, ''));

// ---------------------------------------------------------------- the reader

// Signed in first for their name, which the watermark must carry and the
// office's gift card is made out to.
const reader = await signIn(STUDENT);
check('the reader signs in', !reader.url().includes('/login'), reader.url());
await reader.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await reader.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the reader has a name', NAME.length > 0, NAME || 'no h2 on /portal/performance');

await reader.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
const before = money((await text(reader)).match(/Balance\s+MVR\s+([\d,.]+)/)?.[1]);
check('the reader reads their wallet', Number.isFinite(before), `balance ${before}`);

// ---------------------------------------------------------------- the office

// 1. a gift card, code shown once
const admin = await signIn(ADMIN);
check('the office signs in', !admin.url().includes('/login'), admin.url());
await admin.goto(`${BASE}/en/admin/commerce`, { waitUntil: 'networkidle' });
const giftForm = admin.locator('form', { has: admin.locator('input[placeholder="Recipient name"]') });
await giftForm.locator('input[placeholder="Amount (MVR)"]').fill(AMOUNT);
await giftForm.locator('input[placeholder="Recipient name"]').fill(NAME);
await giftForm.locator('input[placeholder="Recipient email"]').fill(STUDENT);
await giftForm.locator('button:has-text("Issue")').click();
await settles(admin, 'shown only once');
// The code and "Copy it now" sit in one paragraph with no space between
// them, so no word boundary after the code — its shape is fixed at 3×4.
const CODE = (await text(admin)).match(/(AKG-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4})/)?.[1] ?? null;
check('a gift card is issued and its code shown exactly once', Boolean(CODE) && (await text(admin)).includes('shown only once'), CODE ?? (await text(admin)).slice(0, 160));
await admin.reload({ waitUntil: 'networkidle' });
check('the code is gone after a reload — only its hash is kept', CODE !== null && !(await text(admin)).includes(CODE), CODE ? 'not on the page' : 'no code to look for');

// ------------------------------------------------------- the reader, redeeming

// 2. wallet money
await reader.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
await reader.fill('input[name="code"]', CODE ?? 'AKG-NONE-NONE-NONE');
await reader.click('button:has-text("Redeem gift card")');
await reader.waitForLoadState('networkidle');
const wallet = await text(reader);
const after = money(wallet.match(/Balance\s+MVR\s+([\d,.]+)/)?.[1]);
check('the reader redeems it and is told, in money', wallet.includes('Gift card redeemed:') && wallet.includes(`MVR ${AMOUNT} added`), wallet.match(/Gift card redeemed[^.]*\.\d\d[^.]*\./)?.[0] ?? wallet.slice(0, 160));
check('the wallet is up by exactly the card', Math.abs(after - before - Number(AMOUNT)) < 0.005, `${before} → ${after}`);
const ledger = reader.locator('tr', { hasText: 'credit' }).filter({ hasText: 'gift card' }).first();
check('the ledger says the money came from a gift card', (await ledger.count()) > 0 && (await ledger.innerText()).includes(`+${AMOUNT}`), (await ledger.count()) ? (await ledger.innerText()).replace(/\s+/g, ' ') : wallet.slice(0, 160));

await reader.fill('input[name="code"]', CODE ?? 'AKG-NONE-NONE-NONE');
await reader.click('button:has-text("Redeem gift card")');
await reader.waitForLoadState('networkidle');
check('the same card cannot be redeemed twice', !(await text(reader)).includes('Gift card redeemed:') && money((await text(reader)).match(/Balance\s+MVR\s+([\d,.]+)/)?.[1]) === after, (await text(reader)).match(/(already|used|redeemed|invalid|not found)[^.]*/i)?.[0] ?? (await text(reader)).slice(0, 160));

// --------------------------------------------------------------- the reading

// 3. a guest is sent to sign in; the reader reads with their name on the page
const guest = await newPage('guest');
await guest.goto(`${BASE}/en/library/${SLUG}/read`, { waitUntil: 'networkidle' });
check('a guest is sent to sign in for a sign-in-only book', guest.url().includes('/login'), guest.url().replace(BASE, ''));

await reader.goto(`${BASE}/en/library/${SLUG}`, { waitUntil: 'networkidle' });
check('the item page offers to read online', (await text(reader)).includes(TITLE) && (await text(reader)).includes('Read online'), (await text(reader)).slice(0, 160));
await reader.locator('a', { hasText: 'Read online' }).first().click();
await reader.waitForLoadState('networkidle');
const pageOne = await text(reader);
// The watermark carries the *account* (name • email • time), which is not
// the pupil record's name — the email is the part that is certainly theirs.
check('page one is served alone, watermarked with the reader', pageOne.includes('SMOKE-Primer-Page-One') && !pageOne.includes('SMOKE-Primer-Page-Two') && pageOne.includes(`• ${STUDENT} •`) && /Page 1 \/ 3/.test(pageOne), pageOne.match(new RegExp(`[^•]*• ${STUDENT} • [^ ]+ [^ ]+`))?.[0] ?? pageOne.slice(0, 160));
check('there is no download on the reader', (await reader.locator('a[href*="download"], a[download]').count()) === 0);

await reader.locator('a', { hasText: 'Next' }).first().click();
await reader.waitForLoadState('networkidle');
const pageTwo = await text(reader);
check('page two follows', pageTwo.includes('SMOKE-Primer-Page-Two') && !pageTwo.includes('SMOKE-Primer-Page-One'), pageTwo.match(/Page 2 \/ 3/)?.[0] ?? pageTwo.slice(0, 160));

// 4. a bookmark, and the place kept
await reader.click('button:has-text("Bookmark this page")');
await reader.waitForLoadState('networkidle');
check('the page is bookmarked', (await text(reader)).includes('Remove bookmark'), (await text(reader)).slice(0, 120));

await reader.goto(`${BASE}/en/my-library`, { waitUntil: 'networkidle' });
const mine = await text(reader);
check('My Library offers to continue from page two', mine.includes(`${TITLE} Page 2 · 67%`) && mine.includes('Continue'), mine.match(new RegExp(`${TITLE} Page 2[^C]*`))?.[0] ?? mine.slice(0, 160));
check('My Library lists the bookmark', /SMOKE-Primer — Page 2/.test(mine), mine.match(/SMOKE-Primer — Page 2/)?.[0] ?? '');

await reader.goto(`${BASE}/en/library/${SLUG}/read?page=3`, { waitUntil: 'networkidle' });
await reader.goto(`${BASE}/en/my-library`, { waitUntil: 'networkidle' });
check('the last page marks it completed', (await text(reader)).includes('100% · Completed'), (await text(reader)).match(new RegExp(`${TITLE} Page 3[^R]*`))?.[0] ?? (await text(reader)).slice(0, 160));

// ----------------------------------------------------------- a PDF original

// 5. `SMOKE-Primer-PDF` has no body: its pages come from the PDF the seeder
//    uploaded (a browser-printed file with English, Arabic and Dhivehi). The
//    same reader, the same watermark, still no download.
await reader.goto(`${BASE}/en/library/${PDF_SLUG}`, { waitUntil: 'networkidle' });
check('a PDF-only book offers to read online', (await text(reader)).includes(PDF_TITLE) && (await text(reader)).includes('Read online') && (await text(reader)).includes('3 pages'), (await text(reader)).match(/\d+ pages/)?.[0] ?? (await text(reader)).slice(0, 160));
await reader.goto(`${BASE}/en/library/${PDF_SLUG}/read`, { waitUntil: 'networkidle' });
const pdfOne = await text(reader);
check('page one of the PDF is its text, alone, watermarked', pdfOne.includes('Chapter One') && pdfOne.includes('The quick brown fox jumps over the lazy dog.') && !pdfOne.includes('Chapter Two') && pdfOne.includes(`• ${STUDENT} •`) && /Page 1 \/ 3/.test(pdfOne), pdfOne.match(/Page 1 \/ 3/)?.[0] ?? pdfOne.slice(0, 160));
check('there is no download on the PDF reader either', (await reader.locator('a[href*="download"], a[download], a[href$=".pdf"], embed, iframe, object').count()) === 0);
await reader.locator('a', { hasText: 'Next' }).first().click();
await reader.waitForLoadState('networkidle');
const pdfTwo = await text(reader);
check('page two carries the Arabic and Dhivehi in reading order', pdfTwo.includes('Chapter Two begins here on page two.') && pdfTwo.includes('بسم الله الرحمن الرحيم') && pdfTwo.includes('ދިވެހި ބަސް') && !pdfTwo.includes('Chapter One'), pdfTwo.match(/Dhivehi:[^\s]*\s[^\s]*\s[^\s]*/)?.[0] ?? pdfTwo.slice(0, 160));
const rtlParagraphs = await reader.locator('.prose p[dir="auto"]').count();
check('PDF paragraphs are direction-aware', rtlParagraphs >= 3, `${rtlParagraphs} paragraphs with dir=auto`);

// 6. the office uploads a PDF through the form and is told what readers get.
await admin.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
const itemForm = admin.locator('form', { has: admin.locator('input[type=file][accept="application/pdf"]') }).first();
await itemForm.locator('input[placeholder="Title"]').fill(UPLOAD_TITLE);
await itemForm.locator('select').first().selectOption('book');
await itemForm.locator('input[type=file]').setInputFiles(PDF_FILE);
await itemForm.locator('button:has-text("Save item")').click();
const told = await settles(admin, 'reader pages ready');
check('the office uploads a PDF and is told 3 reader pages are ready', told && (await text(admin)).includes('3 reader pages ready'), (await text(admin)).match(/Library item saved[^.]*\.[^.]*\./)?.[0] ?? (await text(admin)).slice(0, 160));

await finish();
