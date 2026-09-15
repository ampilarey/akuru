/**
 * Can somebody become a writer and get something published?
 *
 * The L-track's whole editorial loop, walked: three actors, six states, and a
 * role that is **granted by the flow itself** rather than seeded.
 *
 *   1. a plain reader applies to write,
 *   2. the office approves the application — and the applicant becomes a writer,
 *   3. the writer drafts an item and submits it,
 *   4. the office asks for changes, with a comment,
 *   5. the writer reads the comment and resubmits,
 *   6. the office approves, and it appears in the public library.
 *
 * ## Why it did not exist
 *
 * `docs/OPERATOR_CHECKLIST.md` §1a describes exactly this and asks a person to
 * do it by hand. Nothing automated covered any of it — `all.mjs` said so in its
 * own header — and **every table in the L-track holds zero rows**, so an
 * operator opening these screens sees empty lists everywhere and cannot tell an
 * empty table from a broken reader. That is the same trap `sweep.mjs` was built
 * for, over an entire track.
 *
 * ## What it proves, and what it does not
 *
 * It proves the editorial loop: applying, approving, the **role grant**,
 * drafting, submitting, a changes-requested round trip with the comment
 * reaching the writer, and publication reaching `/library`.
 *
 * It does **not** cover peer review (§1b) or earnings and payouts (§1c). Those
 * are separate loops with their own gates, and one of them has money in it.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/library.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_APPLICANT, SMOKE_STAFF, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const APPLICANT = process.env.SMOKE_APPLICANT ?? 'student@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Unique per run: the loop ends in a **published** item, and publication is not
// reversible from any screen here. A fixed title would mean the second run
// walked past an item the first run had already put in the library.
// Time-of-day (HHMMSS) repeats every 24 hours, and this stamp is what the walk
// looks for to find the row it just created — so a run at the same second on
// any other day finds the *earlier* run's row and passes without testing
// anything. A scheduled nightly run is precisely that case. Base-36 time plus
// randomness does not wrap (STATUS §5ek).
const STAMP = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`.toUpperCase();
const TITLE = `SMOKE-Library-Item ${STAMP}`;
const BODY = 'SMOKE-Library-Body: the sun letters assimilate the laam of the definite article.';
const CHANGES = 'SMOKE-Changes: please add a citation for the assimilation rule.';

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

async function signIn(email) {
    const context = await browser.newContext();
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
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

// `main`, not `body`: the shell puts ~90 links above the content, so a body
// read can match a menu item rather than the page.
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

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

const writer = await signIn(APPLICANT);
const staff = await signIn(STAFF);

// ------------------------------------------------- 1. a reader applies to write

await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
const opening = await text(writer);

// Two shapes are legitimate here: a first run finds the application form, a
// later run finds the portal because the role was already granted and cannot be
// taken away from any screen. Both are walked; only "neither" is a failure.
const alreadyWriter = !opening.includes('Apply to publish');

if (!alreadyWriter) {
    await writer.fill('input[placeholder*="Display name"]', `SMOKE-Writer ${STAMP}`);
    await writer.fill('textarea[placeholder="Bio"]', 'SMOKE-Bio: teaches Arabic.');
    await writer.fill('input[placeholder*="Expertise"]', 'SMOKE-Expertise');
    await writer.check('input[type=checkbox]');
    await writer.click('button:has-text("Submit application")');

    check('a reader can apply to write', await settles(writer, 'pending'), (await text(writer)).slice(0, 160));

    // ------------------------------------------- 2. the office approves it

    await staff.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const queue = await text(staff);
    check('the application reaches the office queue', queue.includes(`SMOKE-Writer ${STAMP}`), queue.slice(0, 200));

    const approve = staff.locator('button:has-text("Approve")').first();
    if (await approve.count()) {
        await approve.click();
        await staff.waitForTimeout(1500);
        check('the office approves it', true);
    } else {
        check('the office approves it', false, 'no Approve button in the applications queue');
    }
} else {
    check('a reader can apply to write', true, 'already a writer from an earlier run — role grants are one-way');
    check('the application reaches the office queue', true, 'skipped: already approved');
    check('the office approves it', true, 'skipped: already approved');
}

// --------------------------------- 3. the applicant is now a writer, and drafts

await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
const portal = await text(writer);

// The role grant is the point of steps 1–2. Asserting the portal replaced the
// application form is what proves approving somebody actually made them a
// writer, rather than only writing a row.
check('approving them made them a writer', !portal.includes('Apply to publish'), portal.slice(0, 160));

let drafted = false;

if (!portal.includes('Apply to publish')) {
    // The editor is behind a New draft toggle rather than always on the page.
    const open = writer.locator('button:has-text("New draft")').first();
    if (await open.count()) {
        await open.click();
        await writer.waitForTimeout(300);
    }

    const hasEditor = (await writer.locator('input[placeholder="Title"]').first().count()) > 0;
    check('the writer portal offers a draft editor', hasEditor, hasEditor ? '' : 'no Title field after New draft');

    await writer.fill('input[placeholder="Title"]', TITLE);
    await writer.fill('textarea[placeholder="Abstract"]', 'SMOKE-Abstract');
    await writer.fill('textarea[placeholder*="Body"]', BODY);
    await writer.click('button:has-text("Save draft")');

    drafted = await settles(writer, TITLE);
    check('the writer can save a draft', drafted, (await text(writer)).slice(0, 160));
}

// ------------------------------------------------ 4. and submit it for review

let submitted = false;

if (drafted) {
    const row = writer.locator('tr').filter({ hasText: TITLE }).first();
    const send = row.locator('button:has-text("Submit for review")').first();

    if (await send.count()) {
        await send.click();
        submitted = await settles(writer, 'submitted');
        check('the writer submits it for review', submitted, (await text(writer)).slice(0, 160));
    } else {
        check('the writer submits it for review', false, 'no "Submit for review" button on the draft');
    }
}

// --------------------------------- 5. the office asks for changes, with a note

let askedForChanges = false;

if (submitted) {
    await staff.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const subs = await text(staff);
    check('the submission reaches the office', subs.includes(TITLE), subs.slice(0, 200));

    // The row that actually carries the control, not the first or last element
    // mentioning the title — a title appears in several nested elements and
    // picking by position found one with no buttons in it. Same lesson as the
    // meetings walk booking an already-booked slot.
    const card = staff.locator('tr, li, article')
        .filter({ hasText: TITLE })
        .filter({ has: staff.locator('button:has-text("Request changes")') })
        .last();

    // By placeholder, not by type. The editor-comment field is a bare
    // `<input>` with no `type` attribute, so `input[type=text]` does not match
    // it — the walk filled nothing, the review saved a NULL comment, and the
    // step that checks the writer was told what to change failed against a
    // product that was fine. Checked against the database before writing it up
    // as a defect.
    const comment = card.locator('input[placeholder="Editor comment"]').first();
    const hasComment = (await comment.count()) > 0;
    check('the office has somewhere to write the reason', hasComment, hasComment ? '' : 'no editor-comment field beside the buttons');
    if (await comment.count()) {
        await comment.fill(CHANGES);
    }

    const ask = card.locator('button:has-text("Request changes")').first();
    if (await ask.count()) {
        await ask.click();
        askedForChanges = await settles(staff, 'changes_requested');
        check('the office can ask for changes', askedForChanges, (await text(staff)).slice(0, 160));
    } else {
        check('the office can ask for changes', false, 'no "Request changes" button on the submission');
    }
}

// ------------------------------------- 6. the writer reads it and resubmits

let resubmitted = false;

if (askedForChanges) {
    await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
    const back = await text(writer);

    // The comment is the whole point of a changes-requested round trip. A
    // status that flips without the reason reaching the writer is a dead end
    // dressed as a workflow.
    check('the writer is told what to change', back.includes(CHANGES), back.slice(0, 200));

    const row = writer.locator('tr').filter({ hasText: TITLE }).first();
    const again = row.locator('button:has-text("Submit for review")').first();
    if (await again.count()) {
        await again.click();
        resubmitted = await settles(writer, 'submitted');
        check('and can send it back', resubmitted, (await text(writer)).slice(0, 160));
    } else {
        check('and can send it back', false, 'no "Submit for review" button after changes were requested');
    }
}

// ------------------------------------- 7. the office approves, and it publishes

if (resubmitted) {
    await staff.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const card = staff.locator('tr, li, article')
        .filter({ hasText: TITLE })
        .filter({ has: staff.locator('button:has-text("Approve")') })
        .last();
    const approve = card.locator('button:has-text("Approve")').first();

    if (await approve.count()) {
        await approve.click();
        await staff.waitForTimeout(2000);
        check('the office approves and publishes it', true);
    } else {
        check('the office approves and publishes it', false, 'no "Approve & publish" button');
    }

    // The sentence, not the column: a reader finds it in the public library.
    const reader = await signIn(APPLICANT);
    await reader.goto(`${BASE}/en/library`, { waitUntil: 'networkidle' });
    const shelf = await text(reader);
    check('and a reader finds it in the library', shelf.includes(TITLE), shelf.slice(0, 200));
}

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
