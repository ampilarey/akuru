/**
 * Can a school ask Akuru for a price on its name tags, and pay the quoted
 * price? On a phone, in Dhivehi. (PRINT_PRODUCTS_PLAN PP6b, STATUS §5to.)
 *
 * With the print desk on and a paper priced (set with tinker), an approved
 * 12-sheet name-tag job is put in the customer's basket — proofed by this
 * server's Chrome, so `DOCUMENTS_CHROME_PATH` must be set. At 390 × 844
 * under /dv the customer asks Akuru for a quote from the basket; the office
 * finds it under the print desk's *School quotes* and prices the sheets at
 * 2.50; the customer accepts it, and the basket shows the line at the quoted
 * price, MVR 30.00. Nothing scrolls sideways. Everything is put back. Local
 * server only.
 *
 *   node scripts/smoke/print-quote.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_CUSTOMER, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const CUSTOMER = process.env.SMOKE_CUSTOMER ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const MARK = 'SMOKE PP6b';

if (!LOCAL) {
    console.log('print-quote changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo 'ok';`);
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
const before = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'product' => DB::table('products')->where('slug', 'name-tags-a4')->value('status'), 'vendor' => DB::table('vendors')->where('kind', 'akuru')->value('status'), 'paper' => DB::table('print_paper_options')->where('kind', 'plain')->where('sheet_size', 'a4')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`));
const restore = () => tinker(`$jobs = DB::table('print_jobs')->where('name', '${MARK}')->pluck('id'); DB::table('cart_items')->whereIn('print_job_id', $jobs)->delete(); $quotes = DB::table('quote_items')->whereIn('print_job_id', $jobs)->pluck('quote_request_id'); DB::table('quote_items')->whereIn('quote_request_id', $quotes)->delete(); DB::table('quote_requests')->whereIn('id', $quotes)->delete(); DB::table('print_jobs')->whereIn('id', $jobs)->delete(); DB::table('print_paper_options')->where('id', ${before.paper.id})->update(['price_colour' => ${before.paper.price_colour ?? 0}, 'price_bw' => ${before.paper.price_bw ?? 0}, 'is_active' => ${before.paper.is_active ? 1 : 0}]); DB::table('products')->where('slug', 'name-tags-a4')->update(['status' => '${before.product}']); DB::table('vendors')->where('kind', 'akuru')->update(['status' => '${before.vendor}']); ${before.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${before.enabled}']);`} Cache::flush(); echo 'ok';`);
restore();
tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); DB::table('products')->where('slug', 'name-tags-a4')->update(['status' => 'active']); DB::table('vendors')->where('kind', 'akuru')->update(['status' => 'active']); DB::table('print_paper_options')->where('id', ${before.paper.id})->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]); Cache::flush(); echo 'ok';`);
const jobId = tinker(`$save = app(\\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintJobAction::class); $j = $save->start(\\App\\Domains\\Bookshop\\Models\\PrintTemplate::query()->where('title', 'Plain')->firstOrFail(), ${customerId}, null, 'dv'); $j = $save->update($j, ['entry' => ['mode' => 'same', 'row' => ['name' => 'ޢާއިޝަތު', 'class' => '3B']], 'sheets' => 12, 'colour' => true, 'name' => '${MARK}'], 'dv'); $p = app(\\App\\Domains\\Bookshop\\Actions\\Print\\ProofPrintJobAction::class); $j = $p->approve($p->proof($j, ${customerId}, 'dv'), 'dv'); app(\\App\\Domains\\Bookshop\\Actions\\Print\\AddPrintJobToCartAction::class)->execute(\\App\\Domains\\Bookshop\\Models\\Cart::query()->firstOrCreate(['user_id' => ${customerId}]), $j, ${customerId}); echo $j->id;`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const signIn = async (who) => {
    const page = await (await browser.newContext(phone)).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    return page;
};

try {
    check('an approved 12-sheet job is in the customer\'s basket', Number(jobId) > 0, jobId);
    const school = await signIn(CUSTOMER);
    await school.goto(`${BASE}/dv/shop/cart`, { waitUntil: 'networkidle' });
    const akuru = tinker(`echo DB::table('vendors')->where('kind', 'akuru')->value('slug');`);
    const form = school.locator(`[data-testid="quote-form-${akuru}"]`);
    check('/dv the basket offers to ask Akuru for a quote', await form.count() === 1);
    await form.locator('summary').click();
    await form.locator('[data-testid="quote-organisation"]').fill('ހިރިޔާ ސްކޫލް');
    await Promise.all([school.waitForLoadState('networkidle'), form.locator('[data-testid="quote-submit"]').click()]);
    const number = tinker(`echo DB::table('quote_requests')->whereIn('id', DB::table('quote_items')->where('print_job_id', ${jobId})->pluck('quote_request_id'))->value('number');`);
    check('the quote is asked, its line the print job', number.startsWith('QT-'), number);

    const office = await signIn(STAFF);
    await office.goto(`${BASE}/dv/admin/bookshop/print/quotes`, { waitUntil: 'networkidle' });
    const card = office.locator(`[data-testid="quote-${number}"]`);
    check('/dv the office finds it under the print desk\'s school quotes', await card.count() === 1);
    check('/dv the office page does not scroll sideways at 390 px', await sideways(office) <= 1, `${await sideways(office)} px`);
    if (SHOTS) { await card.scrollIntoViewIfNeeded(); await office.screenshot({ path: `${SHOTS}/pp6b-office-dv.png` }); }
    await card.locator('[data-testid^="quote-price-"]').fill('2.50');
    await Promise.all([office.waitForLoadState('networkidle'), card.locator('[data-testid="quote-send"]').click()]);
    await office.waitForTimeout(400);
    check('priced at 2.50 a sheet: MVR 30.00 for the 12', tinker(`echo DB::table('quote_requests')->where('number', '${number}')->value('quoted_total');`) === '30.00');

    await school.goto(`${BASE}/dv/my-quotes/${number}`, { waitUntil: 'networkidle' });
    await Promise.all([school.waitForLoadState('networkidle'), school.locator('[data-testid="quote-accept"]').click()]);
    await school.goto(`${BASE}/dv/shop/cart`, { waitUntil: 'networkidle' });
    const line = school.locator('[data-testid="cart-quoted"]').first();
    check('/dv accepted: the basket marks the line quoted', await line.count() === 1);
    check('and charges the quoted price, MVR 30.00', flat(await school.locator('main').textContent()).includes('30.00'));
    check('/dv the basket does not scroll sideways at 390 px', await sideways(school) <= 1, `${await sideways(school)} px`);
    if (SHOTS) { await line.scrollIntoViewIfNeeded(); await school.screenshot({ path: `${SHOTS}/pp6b-cart-dv.png` }); }
} finally {
    await browser.close();
    restore();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
