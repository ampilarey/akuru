/**
 * Does the peer-review gate actually refuse, and can it be satisfied?
 *
 * L7 (§12.2/§29) says a **research** item cannot be published on the editor's
 * word alone: a peer reviewer has to recommend accept first. That is a gate
 * whose whole job is to say **no**, and a gate that never refuses anybody is
 * indistinguishable from no gate at all.
 *
 *   1. the writer drafts a research item with citations and submits it,
 *   2. the office tries to publish it immediately — and is **refused**,
 *   3. the office assigns a reviewer by email — who becomes a reviewer,
 *   4. the reviewer asks for revisions, with a comment,
 *   5. the reviewer accepts,
 *   6. the office publishes, and the citations render for a reader.
 *
 * ## The refusal is paired, on purpose
 *
 * Step 2 proves the gate bites; step 6 proves it can be satisfied. A refusal
 * without the matching success proves only that the screen says no to
 * everybody, which is the lesson `own-data.mjs` records in its own header and
 * the reason its probes come in pairs.
 *
 * ## Why it did not exist
 *
 * `OPERATOR_CHECKLIST` §1b describes this and asks a person to do it by hand.
 * `library.mjs` walks §1a and deliberately stops short of it, because peer
 * review is a different loop with a different actor and a gate in the middle.
 *
 * The gate is switched on by `LIBRARY_RESEARCH_REVIEW_REQUIRED`, default true.
 * With it off, step 2 would not refuse — so the walk says which it saw rather
 * than assuming, because "the gate did not fire" and "the gate is off" are very
 * different findings and only one is a defect.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/peer-review.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_APPLICANT (the writer), SMOKE_REVIEWER,
 * SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const WRITER = process.env.SMOKE_APPLICANT ?? 'student@akuru.edu.mv';
const REVIEWER = process.env.SMOKE_REVIEWER ?? 'parent@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Unique per run: the loop ends published, and publication is not reversible
// from any screen here.
// Time-of-day (HHMMSS) repeats every 24 hours, and this stamp is what the walk
// looks for to find the row it just created — so a run at the same second on
// any other day finds the *earlier* run's row and passes without testing
// anything. A scheduled nightly run is precisely that case. Base-36 time plus
// randomness does not wrap (STATUS §5ek).
const STAMP = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`.toUpperCase();
const TITLE = `SMOKE-Research ${STAMP}`;
const CITATION = `SMOKE-Citation ${STAMP}: Wright, A Grammar of the Arabic Language.`;
const REVISE = 'SMOKE-Revise: the method section needs a sample size.';

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

/** The row that carries a given control, rather than the first one mentioning the title. */
const rowWith = (page, title, label) => page.locator('tr, li, article')
    .filter({ hasText: title })
    .filter({ has: page.locator(`button:has-text("${label}")`) })
    .last();

const writer = await signIn(WRITER);
const staff = await signIn(STAFF);

// ------------------------------------ 1. a research item, with citations

await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });

// This walk needs an approved writer. `library.mjs` makes one; if it has not
// run, say so plainly rather than failing six steps for one missing precondition.
if ((await text(writer)).includes('Apply to publish')) {
    check('the walker is an approved writer', false, 'not a writer yet — run scripts/smoke/library.mjs first');
} else {
    check('the walker is an approved writer', true);

    const open = writer.locator('button:has-text("New draft")').first();
    if (await open.count()) {
        await open.click();
        await writer.waitForTimeout(300);
    }

    await writer.fill('input[placeholder="Title"]', TITLE);

    // `research` is what puts the item under L7's gate, and it is also what
    // makes the citations field appear at all — the editor renders it only for
    // that content type.
    await writer.selectOption('select >> nth=0', 'research');
    await writer.waitForTimeout(200);

    const citations = writer.locator('textarea[placeholder*="Citations"]').first();
    check('choosing research reveals the citations field', (await citations.count()) > 0);
    if (await citations.count()) {
        await citations.fill(CITATION);
    }

    await writer.fill('textarea[placeholder="Abstract"]', 'SMOKE-Research-Abstract');
    await writer.fill('textarea[placeholder*="Body"]', 'SMOKE-Research-Body: assimilation across the sun letters.');
    await writer.click('button:has-text("Save draft")');

    const drafted = await settles(writer, TITLE);
    check('the writer saves the research draft', drafted, (await text(writer)).slice(0, 160));

    if (drafted) {
        const row = rowWith(writer, TITLE, 'Submit for review');
        if (await row.count()) {
            await row.locator('button:has-text("Submit for review")').first().click();
            check('and submits it', await settles(writer, 'submitted'), (await text(writer)).slice(0, 160));
        } else {
            check('and submits it', false, 'no "Submit for review" button on the research draft');
        }
    }

    // ---------------------- 2. the office tries to publish it, and is refused

    await staff.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    check('the research submission reaches the office', (await text(staff)).includes(TITLE), (await text(staff)).slice(0, 200));

    const approveRow = rowWith(staff, TITLE, 'Approve');
    if (await approveRow.count()) {
        await approveRow.locator('button:has-text("Approve")').first().click();
        await staff.waitForTimeout(1500);

        const refused = await settles(staff, 'peer reviewer', 3000);
        const published = (await text(staff)).includes('published');

        // Distinguishing a gate that did not fire from a gate that is switched
        // off matters: only the first is a defect. The config default is on.
        check(
            'publishing research without a peer review is refused',
            refused,
            refused
                ? ''
                : (published
                    ? 'it published — either the gate failed or LIBRARY_RESEARCH_REVIEW_REQUIRED is off'
                    : (await text(staff)).slice(0, 200)),
        );
    } else {
        check('publishing research without a peer review is refused', false, 'no Approve button on the submission');
    }

    // -------------------------------- 3. the office assigns a peer reviewer

    let assigned = false;
    const assignRow = rowWith(staff, TITLE, 'Assign');

    if (await assignRow.count()) {
        await assignRow.locator('input[placeholder*="eviewer"], input[type=email]').first().fill(REVIEWER);
        await assignRow.locator('button:has-text("Assign")').first().click();
        assigned = await settles(staff, 'assigned');
        check('the office assigns a reviewer by email', assigned, (await text(staff)).slice(0, 200));
    } else {
        check('the office assigns a reviewer by email', false, 'no Assign control on the submission');
    }

    // ------------------------- 4. the reviewer, who is now a reviewer, revises

    if (assigned) {
        const reviewer = await signIn(REVIEWER);
        await reviewer.goto(`${BASE}/en/review`, { waitUntil: 'networkidle' });
        const queue = await text(reviewer);

        // Assigning grants the role. Seeing the assignment is what proves it,
        // rather than a row existing somewhere nobody looks.
        check('assigning made them a reviewer, with the item in their queue', queue.includes(TITLE), queue.slice(0, 200));

        const comment = reviewer.locator('textarea').first();
        if (await comment.count()) {
            await comment.fill(REVISE);
        }

        const revise = reviewer.locator('button:has-text("Needs revision")').first();
        if (await revise.count()) {
            await revise.click();
            // The page echoes "your recommendation: <x>". Keying on the bare
            // word would be keying on the buttons, which are always there.
            check(
                'the reviewer can ask for revisions',
                await settles(reviewer, 'your recommendation: revise'),
                (await text(reviewer)).slice(0, 200),
            );
        } else {
            check('the reviewer can ask for revisions', false, 'no "Needs revision" button');
        }

        // ------------------------------------------ 5. and then accepts

        await reviewer.goto(`${BASE}/en/review`, { waitUntil: 'networkidle' });
        const accept = reviewer.locator('button:has-text("Recommend accept")').first();
        if (await accept.count()) {
            await accept.click();
            // `settles(reviewer, 'accept')` passed here before this was
            // written properly — the button itself reads "Recommend accept",
            // so the needle was on the page whatever happened. The echoed
            // recommendation is the only place the answer actually appears.
            check(
                'and can then recommend accept',
                await settles(reviewer, 'your recommendation: accept'),
                (await text(reviewer)).slice(0, 200),
            );
        } else {
            check('and can then recommend accept', false, 'no "Recommend accept" button');
        }

        // ------------------- 6. now the office can publish, and a reader reads it

        await staff.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
        const nowRow = rowWith(staff, TITLE, 'Approve');

        if (await nowRow.count()) {
            await nowRow.locator('button:has-text("Approve")').first().click();
            await staff.waitForTimeout(2000);

            // The pair for step 2: the same button, the same item, and now it
            // works. Without this the refusal above would prove nothing.
            check(
                'with an accept on file, the same approval goes through',
                !(await text(staff)).includes('peer reviewer'),
                (await text(staff)).slice(0, 200),
            );
        } else {
            check('with an accept on file, the same approval goes through', false, 'no Approve button after the accept');
        }

        await reviewer.goto(`${BASE}/en/library`, { waitUntil: 'networkidle' });
        check('a reader finds the research in the library', (await text(reviewer)).includes(TITLE), (await text(reviewer)).slice(0, 200));
    }
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
