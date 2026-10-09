/**
 * Does a wallet payment refused for too small a balance leave nothing
 * behind? (Slice W1, STATUS §5pl.)
 *
 * The student opens SMOKE-Primer-Costly — priced above every seeded wallet —
 * and presses Pay with wallet. The page says why, beside the button, and
 * nothing else changes:
 *   - the wallet holds what it held;
 *   - My Library lists no purchase of the book (before W1 it listed one,
 *     `pending`, for good);
 *   - the book is still for sale to them, not half-bought.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/wallet-refusal.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const READER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SLUG = 'smoke-primer-costly';
const TITLE = 'SMOKE-Primer-Costly';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', READER);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

async function balance() {
    await page.goto(`${BASE}/en/my-wallet`, { waitUntil: 'networkidle' });
    const text = await page.locator('main').innerText();
    const match = text.match(/Balance\s*\n?\s*MVR\s*([\d,]+\.\d{2})/);
    return match ? match[1] : null;
}

async function purchasesListed() {
    await page.goto(`${BASE}/en/my-library`, { waitUntil: 'networkidle' });
    const text = await page.locator('main').innerText();
    const section = text.split('Purchases')[1]?.split('Bookmarks')[0] ?? '';
    return section;
}

const before = await balance();
check('the student\'s wallet shows a balance', before !== null, before ?? 'no balance found');
const listedBefore = await purchasesListed();
check('My Library lists no purchase of SMOKE-Primer-Costly to begin with', !listedBefore.includes(TITLE), listedBefore.slice(0, 200));

const response = await page.goto(`${BASE}/en/library/${SLUG}`, { waitUntil: 'networkidle' });
check('SMOKE-Primer-Costly answers', response?.status() === 200, `HTTP ${response?.status()}`);
const pay = page.locator('form[action$="/checkout"] button[name="pay_with_wallet"]');
check('it is offered to the student to buy', (await pay.count()) === 1, 'no Pay with wallet button');
if (await pay.count()) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), pay.click()]);
    const said = await page.locator('main').innerText();
    check('Pay with wallet is refused, and says why beside the button', said.includes('Insufficient wallet balance.') && page.url().includes(`/library/${SLUG}`), `${page.url()} | ${said.slice(0, 160)}`);
    check('the book is still for sale to them, not half-bought', (await page.locator('form[action$="/checkout"] button[name="pay_with_wallet"]').count()) === 1, 'no checkout form after the refusal');
}

const after = await balance();
check('the wallet holds what it held', after === before, `${before} → ${after}`);
const listedAfter = await purchasesListed();
check('My Library lists no purchase of SMOKE-Primer-Costly after the refusal', !listedAfter.includes(TITLE), listedAfter.slice(0, 200));

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(70)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
