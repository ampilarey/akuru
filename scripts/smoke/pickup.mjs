/**
 * Does a child actually get handed over — and only to the adult who asked?
 *
 * E8 is the highest-stakes loop in the product: at the end of it a child
 * leaves the building with somebody. Every gate lives in `RequestPickupAction`
 * and reads well. Nothing had ever run the five steps in order:
 *
 *   1. the office opens pick-up for today,
 *   2. the guardian sets a PIN,
 *   3. the guardian says "I am ten minutes away", with the PIN,
 *   4. the office sends the child to reception,
 *   5. the guardian confirms they have them.
 *
 * Two actors, two browsers, and the state has to travel between them at every
 * step — a family screen that never learns the child was sent is a parent
 * standing at a gate, and a console that never learns the family asked is a
 * child who is never fetched.
 *
 * ## The refusals are walked too, and they are the point
 *
 * A pick-up loop that works is table stakes. This also checks that a **wrong
 * PIN is refused**, and — separately — that the whole thing is shut when the
 * window is closed. Each refusal is paired with the success that proves the
 * check was reachable at all: a "no" from a screen that says no to everybody
 * proves nothing, which is the lesson `own-data.mjs` records in its own header.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/pickup.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PARENT, SMOKE_STAFF, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const PIN = '8317';
const WRONG_PIN = '0000';

// Two distinct notes, and every console assertion keys off them.
//
// `SmokeMarkerSeeder` already plants a pick-up notice for the **same child**
// under a different guardian. The first version of this walk clicked the first
// "Send to reception" button on the console and sent that one, then reported
// three failures — the family never told, no confirm button, nothing collected
// — which were entirely its own. It also took the seeded notice's presence as
// proof that a refused request had reached the office.
const NOTE = 'SMOKE-Pickup-Note: waiting by the blue gate.';
const REFUSED_NOTE = 'SMOKE-Pickup-Refused: this must never reach the office.';

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
// read can match a menu item rather than the page (OWNER_ACTIONS item 8).
const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

// These forms post over XHR. Neither `networkidle` nor the POST response covers
// Inertia following the redirect and re-rendering, so wait for what the screen
// should say — a timeout still fails the step.
async function settles(page, needle, ms = 5000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await text(page)).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

const staff = await signIn(STAFF);
const parent = await signIn(PARENT);

// ---------------------------------------------- step 0: the window is closed

// Before the office opens anything, the family screen must say so. This is the
// pair for "the loop works" below: if the family could ask at any hour, the
// window would be decoration.
await parent.goto(`${BASE}/en/portal/pickup`, { waitUntil: 'networkidle' });
const beforeOpen = await text(parent);
const closedFirst = !beforeOpen.includes('Tell the school');

check(
    'the family cannot ask before the office opens pick-up',
    closedFirst,
    closedFirst ? beforeOpen.slice(0, 120) : 'the request form was already on the page',
);

// ------------------------------------------- step 1: the office opens pick-up

await staff.goto(`${BASE}/en/academics/pickup`, { waitUntil: 'networkidle' });
const openButton = staff.locator('button:has-text("Open pick-up")').first();

if (await openButton.count()) {
    await openButton.click();
    check('the office opens pick-up for today', await settles(staff, 'Close'), (await text(staff)).slice(0, 140));
} else {
    // Already open from an earlier run — not a failure, but say so.
    check('the office opens pick-up for today', (await text(staff)).includes('Close'), 'already open');
}

// ------------------------------------------------ step 2: the family\'s PIN

await parent.goto(`${BASE}/en/portal/pickup`, { waitUntil: 'networkidle' });

// The request form appears only once a PIN exists — the screen says so ("Set a
// PIN above before you can ask for your child"), and it is the right order: a
// form that collects a credential the account does not have yet is a form that
// can only be got wrong.
check(
    'an open window alone is not enough — a PIN comes first',
    (await text(parent)).includes('Set a PIN above'),
    (await text(parent)).slice(0, 140),
);

const pinForm = parent.locator('form', { hasText: 'pick-up PIN' }).first();
await pinForm.locator('input[type=password]').fill(PIN);
await pinForm.locator('button:has-text("Save PIN")').click();
check('the family sets a PIN', await settles(parent, 'Your pick-up PIN is saved.'), (await text(parent)).slice(0, 140));

await parent.goto(`${BASE}/en/portal/pickup`, { waitUntil: 'networkidle' });
check('and then the request form is there', (await text(parent)).includes('Tell the school'), (await text(parent)).slice(0, 140));

// ------------------------------------- step 3a: the wrong PIN is refused

await parent.goto(`${BASE}/en/portal/pickup`, { waitUntil: 'networkidle' });
const requestForm = parent.locator('form', { hasText: 'I am on my way' }).first();
const childSelect = requestForm.locator('select');
const childName = await childSelect.evaluate((el) => el.options[1]?.text || '');

check('the family has a child they are allowed to collect', childName !== '', childName || 'the Child select offers nobody');

if (childName !== '') {
    await childSelect.selectOption({ index: 1 });
    await requestForm.locator('input[type=password]').fill(WRONG_PIN);
    await requestForm.locator('input[type=text], input:not([type])').last().fill(REFUSED_NOTE);
    await requestForm.locator('button:has-text("Tell the school")').click();

    const refused = await settles(parent, 'That PIN is not right.');
    check('a wrong PIN is refused', refused, (await text(parent)).slice(0, 160));

    // And nothing reached the office. A refusal that still files the notice
    // would be worse than no refusal at all.
    //
    // Keyed on the note this attempt carried, not on the child's name: the
    // seeded fixture already puts that child on the console under another
    // guardian, so a name check here passes whatever the code does.
    await staff.goto(`${BASE}/en/academics/pickup`, { waitUntil: 'networkidle' });
    check('and nothing reaches the console', !(await text(staff)).includes(REFUSED_NOTE), (await text(staff)).slice(0, 140));
}

// ------------------------------------------- step 3b: the real request

let requested = false;

if (childName !== '') {
    await parent.goto(`${BASE}/en/portal/pickup`, { waitUntil: 'networkidle' });
    const form = parent.locator('form', { hasText: 'I am on my way' }).first();
    await form.locator('select').selectOption({ index: 1 });
    await form.locator('input[type=password]').fill(PIN);
    await form.locator('input[type=text], input:not([type])').last().fill(NOTE);
    await form.locator('button:has-text("Tell the school")').click();

    requested = await settles(parent, 'The school has been told.');
    check('the right PIN is accepted', requested, (await text(parent)).slice(0, 160));
}

// --------------------------------------- step 4: the office sends the child

let sent = false;

if (requested) {
    await staff.goto(`${BASE}/en/academics/pickup`, { waitUntil: 'networkidle' });
    const console_ = await text(staff);
    check('the request reaches the office console', console_.includes(childName), console_.slice(0, 160));
    check('with what the family wrote on it', console_.includes(NOTE), console_.slice(0, 160));

    // **This** family's notice, found by the note they wrote. The console also
    // carries a seeded notice for the same child under another guardian, and
    // clicking the first Send button on the page sent that one instead.
    const card = staff.locator('li, article, tr').filter({ hasText: NOTE }).last();
    const send = card.locator('button:has-text("Send to reception")').first();

    if (await send.count()) {
        await send.click();
        sent = await settles(staff, 'Child sent to reception.');
        check('the office sends the child to reception', sent, (await text(staff)).slice(0, 160));
    } else {
        check('the office sends the child to reception', false, 'no "Send to reception" button on the family\'s own notice');
    }
}

// ------------------------------ step 5: the family is told, and closes the loop

if (sent) {
    await parent.goto(`${BASE}/en/portal/pickup`, { waitUntil: 'networkidle' });
    const waiting = await text(parent);
    check('the family is told the child is at reception', waiting.includes('Your child is at reception.'), waiting.slice(0, 160));

    // The console's own counters, before and after. It never prints the word
    // "collected" — the headings are "Waiting for departure (N)" and
    // "Left (N)" — so an earlier `/collected/i` here failed against a console
    // that was working perfectly.
    //
    // Counting also survives the seeded notice for the same child: a name
    // check in the Left table would pass or fail for reasons that have nothing
    // to do with this family.
    const counts = async () => {
        const body = await text(staff);
        return {
            waiting: Number(body.match(/Waiting for departure \((\d+)\)/)?.[1] ?? -1),
            left: Number(body.match(/Left \((\d+)\)/)?.[1] ?? -1),
        };
    };

    await staff.goto(`${BASE}/en/academics/pickup`, { waitUntil: 'networkidle' });
    const before = await counts();

    const confirm = parent.locator('button:has-text("I have my child")').first();
    if (await confirm.count()) {
        await confirm.click();
        check('the family confirms they have them', await settles(parent, 'Collected at'), (await text(parent)).slice(0, 160));
    } else {
        check('the family confirms they have them', false, 'no "I have my child" button');
    }

    // And the office sees the loop closed rather than a child still waiting.
    await staff.goto(`${BASE}/en/academics/pickup`, { waitUntil: 'networkidle' });
    const after = await counts();

    check(
        'the office sees the child has left',
        after.left === before.left + 1,
        `Left ${before.left} → ${after.left}`,
    );
    check(
        'and no longer waiting for departure',
        after.waiting === before.waiting - 1,
        `Waiting ${before.waiting} → ${after.waiting}`,
    );
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
