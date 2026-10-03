/**
 * Does a photo of the ID card fill the registration form? (C17 slice R2,
 * STATUS §5od.) The owner, 2026-10-03: "when id card is uploaded it reads the
 * data and auto fills".
 *
 * The walk draws two documents in the browser and photographs them: a card
 * laid out like a Maldivian ID front (name, "ID No. A……", sex, date of birth,
 * expiry), and a passport photo page with its machine-readable lines. It then
 * hands each photo to a form the way a phone would:
 *
 *   1. the checkout form, signed out — the photo fills the empty details;
 *   2. the details form, signed in, parent enrolling a new child — choosing
 *      the child's card as the ID card upload fills the child's details;
 *   3. a field already typed differently is not overwritten; the form says
 *      so and *Use the card's details* replaces it.
 *
 * Recognition runs on the self-hosted reader, so this also proves every file
 * the browser asks for is served by the site.
 *
 *   node scripts/smoke/id-scan.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const COURSE = process.env.SMOKE_COURSE_SLUG ?? 'smoke-course';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

/** Photograph an HTML document the way a phone would photograph the real one. */
async function photo(html) {
    const page = await browser.newPage({ viewport: { width: 900, height: 560 } });
    await page.setContent(`<body style="margin:0;background:#cfd8dc;display:flex;align-items:center;justify-content:center;height:560px">${html}</body>`);
    const shot = await page.locator('#doc').screenshot();
    await page.close();
    return { name: 'card.png', mimeType: 'image/png', buffer: shot };
}

const idCard = await photo(`<div id="doc" style="width:820px;padding:28px 36px;background:#fdfdf8;border-radius:18px;font-family:Arial,Helvetica,sans-serif;color:#111">
  <div style="font-size:26px;font-weight:700;letter-spacing:1px">REPUBLIC OF MALDIVES</div>
  <div style="font-size:20px;margin-bottom:22px">NATIONAL IDENTITY CARD</div>
  <div style="font-size:18px;color:#444">Name</div>
  <div style="font-size:30px;font-weight:700;margin-bottom:14px">AISHATH SHAZNA ALI</div>
  <div style="font-size:24px;margin-bottom:8px">ID No. A654321</div>
  <div style="font-size:24px;margin-bottom:8px">Sex: F</div>
  <div style="font-size:24px;margin-bottom:8px">Date of Birth: 14-02-2012</div>
  <div style="font-size:24px">Date of Expiry: 01-01-2032</div>
</div>`);

const passport = await photo(`<div id="doc" style="width:860px;padding:26px 30px;background:#fbfbf4;font-family:Arial,sans-serif;color:#111">
  <div style="font-size:24px;font-weight:700;margin-bottom:14px">PASSPORT &middot; REPUBLIC OF MALDIVES</div>
  <div style="font-size:20px;margin-bottom:60px">Surname HASSAN &nbsp; Given names MOHAMED AHMED</div>
  <div style="font-family:'Courier New',monospace;font-size:27px;font-weight:700;letter-spacing:1px;line-height:1.5">P&lt;MDVHASSAN&lt;&lt;MOHAMED&lt;AHMED&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;<br>LA12345675MDV9005053M3001019&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;02</div>
</div>`);

async function waitForReading(page) {
    const status = page.locator('[data-testid="id-scan-status"]').first();
    await page.waitForFunction(() => {
        const root = document.querySelector('[data-id-scan]');
        return root && ['done', 'failed'].includes(root.dataset.state);
    }, null, { timeout: 120000 }).catch(() => {});
    return (await status.innerText().catch(() => '')).replace(/\s+/g, ' ');
}

const value = (page, selector) => page.locator(selector).first().inputValue().catch(() => '');

// ------------------------------------------------ 1. checkout, signed out

{
    const context = await browser.newContext();
    const page = await context.newPage();
    const ocrFiles = [];
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => {
        if (r.url().includes('/vendor/tesseract/')) {
            ocrFiles.push(`${r.status()} ${r.url().split('/vendor/tesseract/')[1]}`);
        }
        if (r.status() >= 500) {
            problems.push(`HTTP ${r.status()} ${r.url()}`);
        }
    });
    await page.goto(`${BASE}/en/courses/${COURSE}/checkout`, { waitUntil: 'networkidle' });
    const block = page.locator('[data-testid="id-scan"]');
    check('the checkout form offers "Fill in from your ID card"', (await block.count()) === 1 && /Fill in from your ID card/.test(await block.innerText()));
    const input = page.locator('[data-testid="id-scan-photo"]');
    check('the photo picker sends nothing with the form (it has no name)', (await input.getAttribute('name')) === null);

    await input.setInputFiles(idCard);
    const said = await waitForReading(page);
    check('it says what it filled and asks to check', /Filled in from the card/.test(said), said);
    check('the first name is filled', (await value(page, 'input[name="first_name"]')) === 'Aishath', await value(page, 'input[name="first_name"]'));
    check('the middle name is filled', (await value(page, 'input[name="middle_name"]')) === 'Shazna', await value(page, 'input[name="middle_name"]'));
    check('the last name is filled', (await value(page, 'input[name="last_name"]')) === 'Ali', await value(page, 'input[name="last_name"]'));
    check('the ID number is filled', (await value(page, 'input[name="national_id"]:not([disabled])')) === 'A654321', await value(page, 'input[name="national_id"]'));
    check('the date of birth is filled', (await value(page, 'input[name="dob"]')) === '2012-02-14', await value(page, 'input[name="dob"]'));
    check('the gender is chosen', (await page.locator('select[name="gender"]').first().inputValue()) === 'female');
    check('the reader is served by this site, every file 200', ocrFiles.length >= 3 && ocrFiles.every((f) => f.startsWith('200')), ocrFiles.join(', '));

    // A passport on a fresh form: the machine-readable lines, the passport option chosen.
    await page.goto(`${BASE}/en/courses/${COURSE}/checkout`, { waitUntil: 'networkidle' });
    await page.locator('[data-testid="id-scan-photo"]').setInputFiles(passport);
    const saidPassport = await waitForReading(page);
    check('a passport photo is read too', /Filled in from the card/.test(saidPassport), saidPassport);
    check('the passport option is chosen and its number filled', (await value(page, 'input[name="passport"]:not([disabled])')) === 'LA1234567', await value(page, 'input[name="passport"]'));
    check('the passport date of birth is filled', (await value(page, 'input[name="dob"]')) === '1990-05-05', await value(page, 'input[name="dob"]'));

    // Typed differently first: not overwritten; the button replaces it.
    await page.goto(`${BASE}/en/courses/${COURSE}/checkout`, { waitUntil: 'networkidle' });
    await page.fill('input[name="first_name"]', 'Aisha');
    await page.locator('[data-testid="id-scan-photo"]').setInputFiles(idCard);
    const saidDiffers = await waitForReading(page);
    check('a field typed differently is kept, and the difference is said', (await value(page, 'input[name="first_name"]')) === 'Aisha' && /different for: first name/.test(saidDiffers), saidDiffers);
    await page.locator('[data-testid="id-scan-use"]').click();
    check('"Use the card\'s details" replaces it', (await value(page, 'input[name="first_name"]')) === 'Aishath');
    await context.close();
}

// ------------------------------------ 2. the details form, a parent's child

{
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', STUDENT);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    await page.goto(`${BASE}/en/courses/${COURSE}/checkout`, { waitUntil: 'networkidle' });
    check('signed in, the checkout goes to the details form', page.url().includes('register/continue'), page.url());

    await page.getByText('I am a parent/guardian enrolling my child').click();
    const addNew = page.locator('input[name="student_mode"][value="new"]');
    if (await addNew.count()) {
        await addNew.check();
    }
    check('the ID card fieldset offers to fill the details', /fills in the empty details/.test(await page.locator('[data-testid="learner-id-card"]').innerText()));
    await page.locator('[data-testid="learner-id-front"]').setInputFiles(idCard);
    const said = await waitForReading(page);
    check('choosing the child\'s card fills the child\'s details', /Filled in from the card/.test(said), said);
    const child = (name) => page.locator(`[x-show="studentMode === 'new'"] [name="${name}"]`).first().inputValue().catch(() => '');
    check('the child\'s name is filled, first, middle and last', (await child('first_name')) === 'Aishath' && (await child('middle_name')) === 'Shazna' && (await child('last_name')) === 'Ali', `${await child('first_name')} / ${await child('middle_name')} / ${await child('last_name')}`);
    check('the child\'s ID and birth date are filled', (await child('national_id')) === 'A654321' && (await child('dob')) === '2012-02-14', `${await child('national_id')} ${await child('dob')}`);
    check('the card stays chosen as the upload the office checks', (await page.locator('[data-testid="learner-id-front"]').evaluate((el) => el.files.length)) === 1);
    await context.close();
}

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(64)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
