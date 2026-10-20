/**
 * Can the office set up an A3 sheet, and does a job on it print on A3 paper?
 * On a phone, in Dhivehi and English. (PRINT_PRODUCTS_PLAN PP1, STATUS §5ti.)
 *
 * At 390 × 844 under /dv the system admin adds an A3 paper and an A3 layout
 * on the print desk and sees both listed as A3. A job is started for them;
 * on its page they choose the A3 layout, and the papers offered become the
 * A3 one only, priced from it. The same page in English names the layout's
 * sheet. Nothing scrolls sideways. The planted rows are removed. Local
 * server only.
 *
 *   node scripts/smoke/print-sizes.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS (a folder for screenshots).
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const MARK = 'SMOKE PP1';

if (!LOCAL) {
    console.log('print-sizes plants rows with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();
const staffId = tinker(`echo DB::table('users')->where('email', '${STAFF}')->value('id');`);

const cleanUp = () => tinker(`DB::table('print_jobs')->where('name', 'like', '${MARK}%')->delete(); DB::table('print_layouts')->where('name', 'like', '${MARK}%')->delete(); DB::table('print_paper_options')->where('name', 'like', '${MARK}%')->delete(); echo 'ok';`);
cleanUp();
tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
const sideways = () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const shot = async (name) => { if (SHOTS) await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false }); };

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(STAFF);
await page.locator('input[name="password"]').fill(PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
check('the system admin signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

// The office: an A3 paper and an A3 layout.
await page.goto(`${BASE}/dv/admin/bookshop/print`, { waitUntil: 'networkidle' });
await page.locator('[data-testid="print-paper-name"]').fill(`${MARK} Gloss A3`);
await page.locator('[data-testid="print-paper-name-dv"]').fill('ގްލޯސް A3');
await page.locator('[data-testid="print-paper-size"]').selectOption('a3');
await page.locator('[data-testid="print-paper-price-bw"]').fill('4');
await page.locator('[data-testid="print-paper-price-colour"]').fill('7.50');
await page.locator('[data-testid="print-paper-active"]').check();
await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="print-paper-save"]').click()]);
await page.waitForTimeout(400);
const paperRow = page.locator('[data-testid="print-papers"] tbody tr', { hasText: `${MARK} Gloss A3` });
check('/dv the A3 paper is listed as A3, with its colour price', await paperRow.count() === 1 && /A3/.test(await paperRow.locator(`[data-label="${say('print.sheet_size', 'dv')}"]`).textContent()) && /7\.50/.test(await paperRow.textContent()), flat(await paperRow.textContent().catch(() => '')).slice(0, 160));

const layoutForm = page.locator('[data-testid="print-layout-form"]');
await page.locator('[data-testid="print-layout-name"]').fill(`${MARK} A3 — 24 tags`);
await page.locator('[data-testid="print-layout-size"]').selectOption('a3');
const fill = async (n, v) => layoutForm.locator('input[type="number"]').nth(n).fill(String(v));
// columns, rows, tag w, tag h, margin top, margin left (the form's order).
for (const [n, v] of [[0, 4], [1, 6], [2, 70], [3, 65], [4, 10], [5, 8]]) await fill(n, v);
await Promise.all([page.waitForLoadState('networkidle'), layoutForm.locator('button[type="submit"]').first().click()]);
await page.waitForTimeout(400);
const layoutRow = page.locator('[data-testid^="print-layout-"]', { hasText: `${MARK} A3` }).first();
check('/dv the A3 layout is listed as A3, 24 tags', await layoutRow.count() === 1 && /A3/.test(await layoutRow.textContent()) && /24/.test(await layoutRow.textContent()), flat(await layoutRow.textContent().catch(() => '')).slice(0, 160));
check('/dv the print desk does not scroll sideways at 390 px', await sideways() <= 1, `${await sideways()} px`);
await layoutRow.scrollIntoViewIfNeeded();
await shot('pp1-desk-dv');

// A job: choose the A3 layout, and the papers become the A3 one.
const ids = JSON.parse(tinker(`$t = \\App\\Domains\\Bookshop\\Models\\PrintTemplate::query()->where('status', 'active')->orderBy('sort_order')->first(); $j = app(\\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintJobAction::class)->start($t, ${staffId}, null, 'dv'); $j->update(['name' => '${MARK} job']); echo json_encode(['job' => $j->id, 'layout' => DB::table('print_layouts')->where('name', 'like', '${MARK}%')->value('id'), 'paper' => DB::table('print_paper_options')->where('name', 'like', '${MARK}%')->value('id')]);`));
await page.goto(`${BASE}/dv/shop/print/jobs/${ids.job}`, { waitUntil: 'networkidle' });
check('/dv before: the A3 paper is not offered on an A4 layout', await page.locator(`[data-testid="print-paper-${ids.paper}"]`).count() === 0);
await page.locator('[data-testid="print-layout"]').selectOption(String(ids.layout));
await page.waitForResponse((r) => r.url().endsWith(`/shop/print/jobs/${ids.job}`) && r.request().method() === 'POST');
await page.waitForTimeout(400);
const offered = await page.locator('[data-testid="print-job-options"] input[name="paper"]').count();
check('/dv after choosing A3: only the A3 paper is offered, and it is chosen', offered === 1 && await page.locator(`[data-testid="print-paper-${ids.paper}"]`).isChecked(), `${offered} offered`);
const saved = tinker(`$j = DB::table('print_jobs')->find(${ids.job}); echo $j->print_layout_id . '|' . $j->print_paper_option_id;`);
check('the job is saved on the A3 layout and the A3 paper', saved === `${ids.layout}|${ids.paper}`, saved);
check('/dv the job page does not scroll sideways at 390 px', await sideways() <= 1, `${await sideways()} px`);
await page.locator('[data-testid="print-job-options"]').scrollIntoViewIfNeeded();
await shot('pp1-job-dv');

await page.goto(`${BASE}/en/shop/print/jobs/${ids.job}`, { waitUntil: 'networkidle' });
const option = flat(await page.locator(`[data-testid="print-layout"] option[value="${ids.layout}"]`).textContent());
check('/en the layout names its sheet: (A3)', option.includes('(A3)'), option);
check('/en the job page does not scroll sideways at 390 px', await sideways() <= 1, `${await sideways()} px`);
await page.locator('[data-testid="print-job-options"]').scrollIntoViewIfNeeded();
await shot('pp1-job-en');

await browser.close();
cleanUp();

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
