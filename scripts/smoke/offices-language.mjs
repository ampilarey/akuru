/**
 * Do the offices refuse in the page's language? (BACKLOG C21, slice OF1,
 * STATUS §5rk.)
 *
 * The system admin opens the feature walk-through under /dv and /ar, marks
 * the first feature broken without saying what is broken, and is told so
 * under the form in the page's language, not in English. Nothing is
 * recorded. Saving it as working still records it; the walk removes the
 * marks it made. Local server only.
 *
 *   node scripts/smoke/offices-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('offices-language reads the database with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const item = tinker(`echo \\App\\Domains\\Settings\\Actions\\ListFeatureWalkthroughAction::itemKeys()[0];`);
const before = tinker(`echo (int) DB::table('feature_test_notes')->max('id');`);
const marks = () => tinker(`echo DB::table('feature_test_notes')->where('id', '>', ${before})->where('item_key', '${item}')->count();`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(STAFF);
await page.locator('input[name="password"]').fill(PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
check('the system admin signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

const openForm = async (locale) => {
    await page.goto(`${BASE}/${locale}/admin/operations/features`, { waitUntil: 'networkidle' });
    const form = page.locator(`[data-testid="feature-form-${item}"]`);
    if (await form.count() === 0) {
        await page.locator('[data-testid="feature-open-all"]').click().catch(() => {});
        await page.locator(`[data-testid="feature-open-${item}"]`).click({ timeout: 5000 }).catch(() => {});
    }
    return form;
};

for (const locale of ['dv', 'ar']) {
    const form = await openForm(locale);
    check(`/${locale} the first feature's form opens`, await form.count() === 1, item);
    await form.locator(`[data-testid="feature-status-${item}-broken"]`).check({ force: true }).catch(() => {});
    await form.locator('[data-testid="feature-save"]').click();
    const refusal = say('admin.feature_test_error_comment', locale);
    const said = await form.getByText(refusal).first().waitFor({ timeout: 8000 }).then(() => true).catch(() => false);
    const text = flat(await form.textContent().catch(() => ''));
    check(`/${locale} a broken mark without a note is refused in the page's language`, said, text.slice(0, 200));
    check(`/${locale} the refusal is not English`, !/Say what is broken/.test(text));
}
check('nothing was recorded by the refusals', marks() === '0');

// A working mark still records.
const form = await openForm('ar');
await form.locator(`[data-testid="feature-status-${item}-works"]`).check({ force: true }).catch(() => {});
await form.locator('[data-testid="feature-save"]').click();
await page.waitForLoadState('networkidle');
await page.waitForTimeout(500);
check('a working mark is recorded', marks() === '1');

await browser.close();
tinker(`DB::table('feature_test_notes')->where('id', '>', ${before})->where('item_key', '${item}')->delete(); echo 'ok';`);

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
