/**
 * Can a customer start a business card on a phone, in Dhivehi?
 * (PRINT_PRODUCTS_PLAN PP3, STATUS §5tk.)
 *
 * With the print desk on and Cards on sale (set with tinker), at 390 × 844
 * under /dv a customer opens the Cards door and finds Akuru's five card
 * designs, starts *Business — side band*, and is asked for name, role,
 * phone and email in Dhivehi. They type a Thaana name; the card shows it,
 * and the server prices the job. Nothing scrolls sideways. The desk, the
 * product and the job are put back. Local server only.
 *
 *   node scripts/smoke/print-cards.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CUSTOMER, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_CUSTOMER ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const NAME = 'ޢާއިޝަތު ނަދާ';

if (!LOCAL) {
    console.log('print-cards changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo 'ok';`);
const before = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->where('kind', 'card')->where('sheet_size', 'a4')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`));
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
const restore = () => tinker(`DB::table('print_jobs')->where('user_id', ${customerId})->where('created_at', '>=', now()->subHours(3))->whereIn('product_id', DB::table('products')->where('slug', 'print-cards')->pluck('id'))->delete(); DB::table('products')->where('slug', 'print-cards')->update(['status' => 'draft']); DB::table('print_paper_options')->where('id', ${before.paper.id})->update(['price_colour' => ${before.paper.price_colour ?? 0}, 'price_bw' => ${before.paper.price_bw ?? 0}, 'is_active' => ${before.paper.is_active ? 1 : 0}]); ${before.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${before.enabled}']);`} Cache::flush(); echo 'ok';`);
tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); DB::table('products')->where('slug', 'print-cards')->update(['status' => 'active']); DB::table('print_paper_options')->where('id', ${before.paper.id})->update(['price_colour' => 5.00, 'price_bw' => 2.50, 'is_active' => true]); Cache::flush(); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
const sideways = () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const shot = async (name) => { if (SHOTS) await page.screenshot({ path: `${SHOTS}/${name}.png` }); };

try {
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(CUSTOMER);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    check('the customer signs in', !new URL(page.url()).pathname.endsWith('/login'));

    await page.goto(`${BASE}/dv/shop/print/cards`, { waitUntil: 'networkidle' });
    const gallery = page.locator('[data-testid^="print-door-template-"]');
    check('/dv the Cards door shows Akuru\'s five card designs', await gallery.count() === 5, String(await gallery.count()));
    check('/dv the door does not scroll sideways at 390 px', await sideways() <= 1, `${await sideways()} px`);
    await shot('pp3-door-dv');

    const side = gallery.filter({ hasText: 'ވިޔަފާރީ — ފަރާތު ބޭންޑް' }).first();
    await Promise.all([page.waitForURL(/\/shop\/print\/jobs\/\d+$/), side.locator('button').click()]);
    const jobId = Number(new URL(page.url()).pathname.split('/').pop());
    check('a business card opens as the customer\'s own job', jobId > 0);
    const body = flat(await page.locator('body').textContent());
    const labels = ['print.field_role', 'print.field_phone', 'print.field_email'].map((k) => say(k, 'dv'));
    check('/dv it asks for role, phone and email, in Dhivehi', labels.every((l) => body.includes(l)), labels.join(' | '));

    const nameInput = page.locator('[data-testid="print-field-name"]');
    if (await nameInput.count()) {
        await nameInput.fill(NAME);
        await page.waitForFunction(() => document.querySelector('[data-testid="print-job-saved"]')?.textContent?.length > 0);
        await page.waitForTimeout(600);
        check('the card shows the Thaana name as it is typed', (await page.locator('[data-testid="print-job-tag"]').first().textContent()).includes(NAME));
    } else {
        check('the name field is on the page', false, 'no [data-testid="print-field-name"]');
    }
    const priced = tinker(`echo DB::table('print_jobs')->where('id', ${jobId})->value('price_total');`);
    check('the server prices it from the card paper', Number(priced) > 0, priced);
    check('/dv the job page does not scroll sideways at 390 px', await sideways() <= 1, `${await sideways()} px`);
    await shot('pp3-job-dv');
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
