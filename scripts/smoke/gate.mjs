/**
 * Does a pupil's gate card get them recorded, and does the family see it?
 *
 * E18 gate cards (owner decision 11, STATUS §5ge). Four people, and the
 * camera is a real camera as far as the page can tell:
 *
 *   1. the office makes sure the pupil's class has cards, replaces the
 *      pupil's card (so there is an old one to refuse), and prints the sheet;
 *   2. at the gate, the new code is typed and Enter pressed — exactly what a
 *      handheld USB or Bluetooth scanner does — and the arrival is recorded;
 *   3. the old card is scanned and refused, by name of what happened to it;
 *   4. the departure is scanned through the camera: Chromium's fake camera is
 *      fed a video frame of the printed QR, and the page's own decoder reads
 *      it — nothing in the page knows it is not a real card in front of a lens;
 *   5. the parent's arrivals page shows both, recorded by QR.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/gate.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_STUDENT, SMOKE_PARENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const ARGS = ['--no-first-run', '--no-default-browser-check', '--disable-background-networking'];
const executable = process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {};
const browser = await chromium.launch({ args: ARGS, ...executable });

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

async function signIn(on, email) {
    const context = await on.newContext({ viewport: { width: 1280, height: 900 }, permissions: ['camera'] });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${email}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) problems.push(`${email}: HTTP ${response.status()} ${response.url()}`);
    });
    page.on('dialog', (dialog) => dialog.accept());
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

const text = async (page) => (await page.innerText('main').catch(() => page.innerText('body'))).replace(/\s+/g, ' ');

async function settles(page, needle, ms = 15000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        const body = await text(page);
        if (typeof needle === 'string' ? body.includes(needle) : needle.test(body)) return true;
        await page.waitForTimeout(150);
    }
    return false;
}

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

// ------------------------------------------------------------------ the pupil

const student = await signIn(browser, STUDENT);
await student.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const NAME = ((await student.locator('main h2').first().innerText().catch(() => '')) || '').trim();
check('the pupil has a name', NAME !== '', NAME || 'no h2 on /portal/performance');
if (NAME === '') await finish();

// ------------------------------------------------------------------ the office

const office = await signIn(browser, STAFF);
await office.goto(`${BASE}/en/academics/gate/cards`, { waitUntil: 'networkidle' });

// The pupil's class: the option whose roll lists them. Read, not assumed.
const options = await office.locator('main select option').evaluateAll((os) => os.map((o) => o.value));
let classId = '';
for (const value of options) {
    await office.goto(`${BASE}/en/academics/gate/cards?class_id=${value}`, { waitUntil: 'networkidle' });
    if ((await office.locator('tbody tr', { hasText: NAME }).count()) > 0) {
        classId = value;
        break;
    }
}
check('the office finds the pupil\'s class on the cards screen', classId !== '', classId ? `class ${classId}` : `${NAME} on no class roll`);
if (classId === '') await finish();

const issue = office.locator('button:has-text("Issue missing cards")');
if (await issue.isEnabled()) {
    await issue.click();
    await settles(office, /cards? issued\./);
    await office.goto(`${BASE}/en/academics/gate/cards?class_id=${classId}`, { waitUntil: 'networkidle' });
}
const row = () => office.locator('tbody tr', { hasText: NAME }).first();
const oldReadable = ((await row().locator('td').nth(1).innerText()) || '').trim();
check('the pupil has a card', /^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/.test(oldReadable), oldReadable);

await row().locator('button:has-text("Card lost — replace")').click();
check('a lost card is replaced', await settles(office, 'New card issued. The old one no longer works.'), (await text(office)).slice(0, 120));
await office.goto(`${BASE}/en/academics/gate/cards?class_id=${classId}`, { waitUntil: 'networkidle' });
const newReadable = ((await row().locator('td').nth(1).innerText()) || '').trim();
check('with a different code', newReadable !== oldReadable && /^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/.test(newReadable), `${oldReadable} → ${newReadable}`);

// The sheet: the card is on it, with its QR drawn and its code printed.
await office.goto(`${BASE}/en/academics/gate/cards/print?class_id=${classId}`, { waitUntil: 'networkidle' });
const printed = office.locator("[data-gate-card]", { hasText: NAME }).first();
await printed.locator("[data-qr=\"drawn\"]").waitFor({ timeout: 10000 }).catch(() => {});
const printedCode = await printed.getAttribute('data-gate-card').catch(() => null);
check(
    'the print sheet has the new card, with a QR and the code under it',
    printedCode !== null && (await printed.locator('svg').count()) === 1 && (await printed.innerText()).includes(newReadable),
    printedCode ?? 'no card on the sheet',
);

// A video frame of the printed QR, for the camera step: drawn in the page from
// the sheet's own SVG, converted to the Y4M the fake camera plays.
const frame = await printed.evaluate(async (card) => {
    // The drawn QR has a viewBox and no size; give it one so it rasterises
    // at full resolution rather than a browser default.
    const svg = new XMLSerializer().serializeToString(card.querySelector('svg'))
        .replace(/^<svg /, '<svg width="360" height="360" ');
    const img = new Image();
    await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = reject;
        img.src = `data:image/svg+xml;base64,${btoa(svg)}`;
    });
    const W = 640;
    const H = 480;
    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, W, H);
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(img, (W - 360) / 2, (H - 360) / 2, 360, 360);
    const rgb = ctx.getImageData(0, 0, W, H).data;
    const y = new Uint8Array(W * H);
    const u = new Uint8Array((W / 2) * (H / 2));
    const v = new Uint8Array((W / 2) * (H / 2));
    for (let j = 0; j < H; j++) {
        for (let i = 0; i < W; i++) {
            const k = (j * W + i) * 4;
            y[j * W + i] = Math.round(0.299 * rgb[k] + 0.587 * rgb[k + 1] + 0.114 * rgb[k + 2]);
        }
    }
    u.fill(128);
    v.fill(128);
    const all = new Uint8Array(y.length + u.length + v.length);
    all.set(y, 0);
    all.set(u, y.length);
    all.set(v, y.length + u.length);
    let binary = '';
    for (let n = 0; n < all.length; n += 0x8000) binary += String.fromCharCode(...all.subarray(n, n + 0x8000));
    return btoa(binary);
}).catch(() => null);

// -------------------------------------------------------------------- the gate

await office.goto(`${BASE}/en/academics/gate`, { waitUntil: 'networkidle' });
await office.locator('button[aria-pressed]', { hasText: 'Arriving' }).click();

// A handheld scanner types the code and presses Enter.
const box = office.locator('input[name="gate-code"]');
await box.fill(newReadable.toLowerCase());
await box.press('Enter');
const arrived = await settles(office, new RegExp(`${NAME} arrived at \\d\\d:\\d\\d\\.`));
check('typed like a handheld scanner, the arrival is recorded by name', arrived, (await text(office)).slice(0, 160));
check('and the box is empty and ready for the next card', (await box.inputValue()) === '' && (await box.evaluate((el) => el === document.activeElement)), `value "${await box.inputValue()}"`);

await box.fill(oldReadable);
await box.press('Enter');
check('the replaced card is refused, and says why', await settles(office, 'was replaced on'), (await text(office)).slice(0, 160));

// The camera: a second browser whose camera plays the printed QR.
let left = false;
if (frame) {
    const dir = mkdtempSync(join(tmpdir(), 'akuru-gate-'));
    const y4m = join(dir, 'card.y4m');
    const body = Buffer.from(frame, 'base64');
    writeFileSync(y4m, Buffer.concat([Buffer.from('YUV4MPEG2 W640 H480 F10:1 Ip A1:1 C420jpeg\n'), Buffer.from('FRAME\n'), body]));

    const camera = await chromium.launch({
        args: [...ARGS, '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', `--use-file-for-fake-video-capture=${y4m}`],
        ...executable,
    });
    const gate = await signIn(camera, STAFF);
    await gate.goto(`${BASE}/en/academics/gate`, { waitUntil: 'networkidle' });
    await gate.locator('button[aria-pressed]', { hasText: 'Leaving' }).click();
    await gate.locator('button:has-text("Use camera")').click();
    left = await settles(gate, new RegExp(`${NAME} left at \\d\\d:\\d\\d\\.`), 20000);
    check('shown to the camera, the printed QR records the departure', left, (await text(gate)).slice(0, 160));
    await camera.close();
} else {
    check('shown to the camera, the printed QR records the departure', false, 'could not draw a frame from the printed QR');
}

// The office's log says how each was recorded.
await office.goto(`${BASE}/en/academics/gate`, { waitUntil: 'networkidle' });
const logged = office.locator('tbody tr', { hasText: NAME });
check('the gate log marks both scans as QR', (await logged.filter({ hasText: 'QR' }).count()) >= 2, `${await logged.filter({ hasText: 'QR' }).count()} QR rows`);

// ------------------------------------------------------------------ the family

const parent = await signIn(browser, PARENT);
await parent.goto(`${BASE}/en/portal/movements`, { waitUntil: 'networkidle' });
const family = await text(parent);
// The page lists times, newest first; both of today's scans are on it.
check(
    'the parent sees today\'s arrival and departure, recorded by QR',
    family.includes(NAME) && new RegExp(`Recorded: QR Left \\d\\d:\\d\\d`).test(family) && new RegExp(`Recorded: QR Arrived \\d\\d:\\d\\d`).test(family),
    family.slice(0, 200),
);

await finish();
