/**
 * Does a card payment that never starts leave the reader's discount code
 * spent? (STATUS §5pn.)
 *
 * The student opens SMOKE-Primer-Unstarted, types SMOKE-LIB-ONCE (good once
 * per reader) and presses Buy. The walk runs where no bank gateway is set up, so
 * the payment cannot start. The page says so, and:
 *   - My Library lists the attempt as *payment did not start*, not as a
 *     purchase still waiting to be paid;
 *   - the same code is accepted again on a second try. Before §5pn the first
 *     attempt's redemption held the code's one use for a day, and the second
 *     try was told *You have already used this code.*
 *
 * Run it against a host with BML set up and the first press goes to the bank
 * instead, so the walk says so and stops.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/abandoned-code.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const READER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SLUG = 'smoke-primer-unstarted';
const TITLE = 'SMOKE-Primer-Unstarted';
const CODE = 'SMOKE-LIB-ONCE';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const context = await browser.newContext();
// Nothing leaves for the bank: a host with BML set up would send the browser there.
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', READER);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

async function buyWithCode() {
    await page.goto(`${BASE}/en/library/${SLUG}`, { waitUntil: 'networkidle' });
    const form = page.locator('form[action$="/checkout"]');
    if ((await form.count()) === 0) return null;
    await form.locator('input[name="discount_code"]').fill(CODE);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
        form.locator('button[type=submit]:not([name])').click(),
    ]);
    return (await page.locator('main').innerText().catch(() => '')).replace(/\s+/g, ' ');
}

async function attemptsListed() {
    await page.goto(`${BASE}/en/my-library`, { waitUntil: 'networkidle' });
    const text = await page.locator('main').innerText();
    const section = text.split('Purchases')[1]?.split('Bookmarks')[0] ?? '';
    return section.split('\n').map((line) => line.trim()).filter(Boolean);
}

const first = await buyWithCode();
const onItem = page.url().includes(`/library/${SLUG}`);
check('Buy with a code, where the payment cannot start, says so on the item\'s page', first !== null && onItem && /could not|not configured|not available/i.test(first), `${page.url()} | ${String(first).slice(0, 160)}`);

if (onItem) {
    const listed = await attemptsListed();
    const mine = listed.filter((line, i) => line.includes(TITLE) || (listed[i - 1] ?? '').includes(TITLE));
    check('My Library lists the attempt as one whose payment did not start', mine.some((line) => line.includes('payment did not start')) && !mine.some((line) => /\bpending\b/.test(line)), mine.join(' / ').slice(0, 200));

    const second = await buyWithCode();
    check('the same once-per-reader code is accepted on a second try', second !== null && !second.includes('You have already used this code.'), String(second).slice(0, 160));
    check('and the second try is told only that the payment could not start', second !== null && /could not|not configured|not available/i.test(second), String(second).slice(0, 160));
} else {
    check('this host started a bank payment, so the walk stops here', false, page.url());
}

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(76)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
