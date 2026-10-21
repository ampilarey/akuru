/**
 * Can a customer have a shop print their name tags, and the shop print them?
 * On a phone, in Dhivehi. (PRINT_PRODUCTS_PLAN PP7c, PD10d, STATUS §5uj.)
 *
 * The walk switches the print desk on, approves Fitrah to print with its own
 * A4 paper, and gives the customer a design. At 390 × 844 under /dv, the
 * design's page offers Akuru first and Fitrah. The customer chooses Fitrah,
 * and the page now offers Fitrah's paper only, at Fitrah's price. The
 * design is approved (the real Chrome proofs it) and paid from the wallet.
 * The checkout offers Fitrah's own deliveries, not Akuru's. The order is
 * Fitrah's. Fitrah's owner finds the job on /dv/vendor/print, downloads the
 * print file, and starts it. Nothing scrolls sideways. The desk and the shop
 * are put back. Local server only.
 *
 *   DOCUMENTS_CHROME_PATH=… node scripts/smoke/print-printer.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const PAPER = 'Smoke PP7c plain';

if (!LOCAL) {
    console.log('print-printer changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const APPROVE = (on) => `app(\\App\\Domains\\Bookshop\\Actions\\Print\\ApproveVendorPrintAction::class)->execute(\\App\\Domains\\Bookshop\\Models\\Vendor::where('slug', 'fitrah')->first(), ${on ? 'true' : 'false'});`;

// The desk and the shop as this walk needs them; put back afterwards.
const saved = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->whereNull('vendor_id')->where('kind', 'glossy')->first(['id', 'price_colour', 'price_bw', 'is_active']), 'prints' => (bool) DB::table('vendors')->where('slug', 'fitrah')->value('print_enabled'), 'agreed' => DB::table('vendor_members')->join('users', 'users.id', '=', 'vendor_members.user_id')->where('users.email', '${VENDOR}')->value('agreement_accepted_at')]);`));
const productWas = tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->getOriginal('status');`);
// Akuru's glossy at 4.50, so Akuru prints too, as in print-order.
tinker(`DB::table('print_paper_options')->where('id', ${saved.paper.id})->update(['price_colour' => 4.50, 'price_bw' => 2.00, 'is_active' => true]); DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->update(['status' => 'active']); $f = DB::table('vendors')->where('slug', 'fitrah')->value('id'); DB::table('print_paper_options')->where('name', '${PAPER}')->delete(); DB::table('print_paper_options')->insert(['vendor_id' => $f, 'name' => '${PAPER}', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1.00, 'price_colour' => 2.00, 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]); ${APPROVE(true)} DB::table('vendor_members')->where('vendor_id', $f)->whereNull('agreement_accepted_at')->update(['agreement_accepted_at' => now()]); Cache::flush(); echo 'ok';`);
const paperId = Number(tinker(`echo DB::table('print_paper_options')->where('name', '${PAPER}')->value('id');`));
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
tinker(`$c = DB::table('carts')->where('user_id', ${customerId})->value('id'); if ($c) { DB::table('cart_items')->where('cart_id', $c)->whereNull('saved_at')->delete(); } echo 'ok';`);
const jobId = Number(tinker(`$t = \\App\\Domains\\Bookshop\\Models\\PrintTemplate::query()->where('kind', 'name_tag')->where('status', 'active')->orderBy('sort_order')->firstOrFail(); $s = app(\\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintJobAction::class); $j = $s->start($t, ${customerId}, null, 'en'); $j = $s->update($j, ['entry' => ['mode' => 'same', 'row' => ['name' => 'Aishath Nada', 'class' => '3B']], 'sheets' => 2, 'colour' => true], 'en'); echo $j->id;`));
tinker(`app(\\App\\Domains\\Commerce\\Actions\\CreditWalletAction::class)->execute(${customerId}, 50, 'admin', null, 'print-printer walk top-up'); echo 'ok';`);
const cleanUp = () => {
    tinker(`${APPROVE(saved.prints)} DB::table('print_paper_options')->where('id', ${saved.paper.id})->update(['price_colour' => ${saved.paper.price_colour ?? 0}, 'price_bw' => ${saved.paper.price_bw ?? 0}, 'is_active' => ${saved.paper.is_active ? 1 : 0}]); DB::table('print_paper_options')->where('name', '${PAPER}')->update(['is_active' => false]); ${saved.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${saved.enabled}']);`} \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->update(['status' => '${productWas}']); ${saved.agreed === null ? `DB::table('vendor_members')->whereIn('user_id', DB::table('users')->where('email', '${VENDOR}')->pluck('id'))->update(['agreement_accepted_at' => null]);` : ''} Cache::flush(); echo 'ok';`);
};

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, acceptDownloads: true };
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const signIn = async (who) => {
    const page = await (await browser.newContext(phone)).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });

    return page;
};

try {
    check('the walk made a design and approved Fitrah to print', jobId > 0 && paperId > 0, `${jobId} ${paperId}`);
    const customer = await signIn(CUSTOMER);
    await customer.goto(`${BASE}/dv/shop/print/jobs/${jobId}`, { waitUntil: 'networkidle' });
    const choice = customer.locator('[data-testid="print-printers"]');
    check('/dv the design offers Akuru first, then Fitrah; Akuru chosen', await choice.count() === 1
        && await choice.locator('input[type=radio]').evaluateAll((els) => els.map((el) => el.dataset.testid).join(',')) === 'print-printer-akuru,print-printer-fitrah'
        && await customer.locator('[data-testid="print-printer-akuru"]').isChecked());
    check('/dv the page does not scroll sideways at 390 px', await sideways(customer) <= 1, `${await sideways(customer)} px`);
    await Promise.all([customer.waitForResponse((r) => r.url().includes(`/print/jobs/${jobId}/printer`)), customer.locator('[data-testid="print-printer-fitrah"]').click()]);
    await customer.waitForLoadState('networkidle');
    await customer.waitForFunction(() => document.querySelector('[data-testid="print-printer-fitrah"]')?.checked, null, { timeout: 10000 }).catch(() => {});
    const papers = await customer.locator('input[name="paper"]').evaluateAll((els) => els.map((el) => el.dataset.testid));
    check('Fitrah chosen: its own paper is the one offered, and chosen', await customer.locator('[data-testid="print-printer-fitrah"]').isChecked() && JSON.stringify(papers) === JSON.stringify([`print-paper-${paperId}`]) && await customer.locator(`[data-testid="print-paper-${paperId}"]`).isChecked(), papers.join(','));
    const total = tinker(`echo \\App\\Domains\\Bookshop\\Models\\PrintJob::find(${jobId})->price_total;`);
    check('priced at Fitrah\'s 2.00 a sheet: 4.00 for two', Number(total) === 4, total);
    if (SHOTS) await customer.screenshot({ path: `${SHOTS}/pp7c-printer-dv.png`, fullPage: true });

    // The real Chrome proofs it; the customer approves and buys it.
    tinker(`$j = \\App\\Domains\\Bookshop\\Models\\PrintJob::find(${jobId}); $p = app(\\App\\Domains\\Bookshop\\Actions\\Print\\ProofPrintJobAction::class); $p->approve($p->proof($j, ${customerId}, 'en'), 'en'); echo 'ok';`);
    await customer.goto(`${BASE}/dv/shop/print/jobs/${jobId}`, { waitUntil: 'networkidle' });
    await Promise.all([customer.waitForURL(/\/shop\/cart$/), customer.locator('[data-testid="print-add-to-cart"]').click()]);
    await customer.goto(`${BASE}/dv/shop/checkout`, { waitUntil: 'networkidle' });
    const kinds = await customer.locator('input[type=radio][data-delivery-kind]').evaluateAll((els) => els.map((el) => el.getAttribute('data-delivery-kind')));
    check('/dv the checkout offers Fitrah\'s own deliveries, not Akuru\'s courier', kinds.includes('collect_vendor') && !kinds.includes('akuru_courier'), kinds.join(','));
    check('/dv the checkout does not scroll sideways at 390 px', await sideways(customer) <= 1, `${await sideways(customer)} px`);
    await customer.locator('input[type=radio][data-delivery-kind="collect_vendor"]').check();
    await customer.fill('[data-testid="recipient-name"]', 'Aishath Nada').catch(() => {});
    await customer.fill('[data-testid="phone"]', '7700000').catch(() => {});
    await customer.check('[data-testid="pay-wallet"]');
    await Promise.all([customer.waitForLoadState('networkidle'), customer.locator('[data-testid="place-order"]').click()]);
    await customer.waitForLoadState('networkidle');
    check('paid from the wallet', (await customer.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid', customer.url().replace(BASE, ''));
    const orderShop = tinker(`echo DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->join('vendors', 'vendors.id', '=', 'orders.vendor_id')->where('order_items.print_job_id', ${jobId})->value('vendors.slug');`);
    check('the order is Fitrah\'s', orderShop === 'fitrah', orderShop);

    // Fitrah prints it.
    const shop = await signIn(VENDOR);
    await shop.goto(`${BASE}/dv/vendor/print`, { waitUntil: 'networkidle' });
    const card = shop.locator(`[data-testid="queue-job-${jobId}"]`);
    check('/dv the job is on Fitrah\'s own print desk', await card.count() === 1);
    const [download] = await Promise.all([shop.waitForEvent('download'), card.locator(`[data-testid="queue-file-${jobId}"]`).click()]);
    check('Fitrah downloads the print file', (await download.suggestedFilename()) === `print-job-${jobId}.pdf`, await download.suggestedFilename());
    await Promise.all([shop.waitForResponse((r) => r.url().includes(`/vendor/print/jobs/${jobId}/start`)), card.locator(`[data-testid="queue-start-${jobId}"]`).click()]);
    await shop.waitForLoadState('networkidle');
    const state = tinker(`echo \\App\\Domains\\Bookshop\\Models\\PrintJob::find(${jobId})->status->value . ' ' . DB::table('orders')->join('order_items', 'order_items.order_id', '=', 'orders.id')->where('order_items.print_job_id', ${jobId})->value('orders.status');`);
    check('started: the job is printing and the order processing', state === 'printing processing', state);
    check('/dv the shop\'s desk does not scroll sideways at 390 px', await sideways(shop) <= 1, `${await sideways(shop)} px`);
    await customer.goto(`${BASE}/en/shop/print/jobs/${jobId}`, { waitUntil: 'networkidle' });
    check('/en the design reads left to right, still Fitrah\'s, nothing sideways', await customer.evaluate(() => document.documentElement.dir) === 'ltr' && await customer.locator('[data-testid="print-printer-fitrah"]').isChecked() && await sideways(customer) <= 1);
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    cleanUp();
    await browser.close();
}

const passed = results.filter(([, ok]) => ok).length;
for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${detail && !ok ? ` — ${detail}` : ''}`);
for (const p of problems) console.log(`PROBLEM  ${p}`);
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length && problems.length === 0 ? 0 : 1);
