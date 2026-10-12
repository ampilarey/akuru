/**
 * Can a customer buy an approved name-tag design, and can the print desk
 * print it until the customer reads *ready* in Arabic? (NAME_TAGS_PLAN §3.8,
 * §4.8, slice NT4, STATUS §5rr.)
 *
 * The walk switches the print desk on for itself and makes the customer an
 * approved design (the real Chrome proofs it here) and tops up their wallet
 * — the payment path the Bookstore walks already use. The customer adds the
 * design to the basket, finds its sheets locked and no *Save for later*, is
 * offered Akuru's two deliveries only, collects, pays from the wallet. The
 * Bookstore office opens the print queue, downloads the print file, starts
 * and finishes the job; the customer's order then reads *ready* under /ar.
 * The desk is put back as it was. Local server only.
 *
 *   DOCUMENTS_CHROME_PATH=… node scripts/smoke/print-order.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('print-order changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale, vars = '[]') => tinker(`echo __('${key}', ${vars}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

// The desk as this walk needs it; put back afterwards.
const saved = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->where('kind', 'glossy')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`));
const paperId = saved.paper.id;
tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => 4.50, 'price_bw' => 2.00, 'is_active' => true]); DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->update(['status' => 'active']); Cache::flush(); echo 'ok';`);
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
const productWas = tinker(`echo \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->getOriginal('status');`);
// The customer's basket starts empty, so the walk's order is the design alone.
tinker(`$c = DB::table('carts')->where('user_id', ${customerId})->value('id'); if ($c) { DB::table('cart_items')->where('cart_id', $c)->whereNull('saved_at')->delete(); } echo 'ok';`);
// An approved design: two sheets of glossy colour, proofed by the real Chrome.
const jobId = Number(tinker(`$t = \\App\\Domains\\Bookshop\\Models\\PrintTemplate::query()->where('kind', 'name_tag')->where('status', 'active')->orderBy('sort_order')->firstOrFail(); $s = app(\\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintJobAction::class); $j = $s->start($t, ${customerId}, null, 'en'); $j = $s->update($j, ['entry' => ['mode' => 'same', 'row' => ['name' => 'Aishath Nada', 'class' => '3B']], 'sheets' => 2, 'colour' => true, 'paper_id' => ${paperId}], 'en'); $p = app(\\App\\Domains\\Bookshop\\Actions\\Print\\ProofPrintJobAction::class); $j = $p->approve($p->proof($j, ${customerId}, 'en'), 'en'); echo $j->id;`));
tinker(`app(\\App\\Domains\\Commerce\\Actions\\CreditWalletAction::class)->execute(${customerId}, 50, 'admin', null, 'print-order walk top-up'); echo 'ok';`);
const cleanUp = () => {
    tinker(`DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => ${saved.paper.price_colour ?? 0}, 'price_bw' => ${saved.paper.price_bw ?? 0}, 'is_active' => ${saved.paper.is_active ? 1 : 0}]); ${saved.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${saved.enabled}']);`} \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->update(['status' => '${productWas}']); Cache::flush(); echo 'ok';`);
};

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const signIn = async (who) => {
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);

    return page;
};

try {
    check('the walk made an approved design to buy', jobId > 0, String(jobId));
    const customer = await signIn(CUSTOMER);

    // The design's page offers the basket.
    await customer.goto(`${BASE}/en/shop/print/jobs/${jobId}`, { waitUntil: 'networkidle' });
    check('the approved design offers Add to basket', (await customer.locator('[data-testid="print-add-to-cart"]').count()) === 1);
    await Promise.all([customer.waitForURL(/\/shop\/cart$/), customer.locator('[data-testid="print-add-to-cart"]').click()]);
    await customer.waitForLoadState('networkidle');
    const line = customer.locator('[data-testid="cart-line"]').filter({ has: customer.locator('[data-testid="cart-print-design"]') });
    check('the basket has the design, two sheets, the held price', (await line.count()) === 1 && flat(await line.locator('[data-testid="cart-print-sheets"]').textContent()).includes(say('print.cart_sheets_note', 'en', "['sheets' => 2]")) && flat(await line.locator('[data-testid="cart-line-total"]').textContent()).includes('9.00'), flat(await line.textContent()).slice(0, 160));
    check('its sheets are locked and it cannot be saved for later', (await line.locator('[data-testid="cart-qty"]').getAttribute('readonly')) !== null && (await line.locator('[data-testid="cart-save-later"]').count()) === 0);

    // Checkout: Akuru's two deliveries, collect, the wallet.
    await customer.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
    const kinds = await customer.locator('input[type=radio][data-delivery-kind]').evaluateAll((els) => els.map((el) => el.getAttribute('data-delivery-kind')).sort());
    check('the checkout offers collect from Akuru and Akuru courier only', JSON.stringify(kinds) === JSON.stringify(['akuru_courier', 'collect_akuru']), kinds.join(','));
    check('ready in the desk\'s lead time', (await customer.content()).includes(say('print.ready_in_days', 'en', "['days' => \\App\\Domains\\Bookshop\\Support\\PrintDesk::settings()['lead_days']]")));
    await customer.locator('input[type=radio][data-delivery-kind="collect_akuru"]').check();
    await customer.fill('[data-testid="recipient-name"]', 'Aishath Nada').catch(() => {});
    await customer.fill('[data-testid="phone"]', '7700000').catch(() => {});
    await customer.check('[data-testid="pay-wallet"]');
    await Promise.all([customer.waitForLoadState('networkidle'), customer.locator('[data-testid="place-order"]').click()]);
    await customer.waitForLoadState('networkidle');
    check('paid from the wallet', (await customer.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid', customer.url().replace(BASE, ''));
    const orderNumber = await customer.locator('[data-testid="checkout-orders"] [data-order]').first().getAttribute('data-order').catch(() => '');
    check('one order for the print desk', Boolean(orderNumber), orderNumber ?? '');

    // The office's queue.
    const office = await signIn(OFFICE);
    await office.goto(`${BASE}/en/admin/bookshop/print/queue`, { waitUntil: 'networkidle' });
    const card = office.locator(`[data-testid="queue-job-${jobId}"]`);
    check('the paid job is on the print queue, ordered, with a due date', (await card.count()) === 1 && flat(await card.locator(`[data-testid="queue-status-${jobId}"]`).textContent()) === say('print.job_status_ordered', 'en') && /\d{4}-\d{2}-\d{2}/.test(await card.locator(`[data-testid="queue-due-${jobId}"]`).textContent()));
    const [download] = await Promise.all([office.waitForEvent('download'), card.locator(`[data-testid="queue-file-${jobId}"]`).click()]);
    check('the office downloads the print file', (await download.suggestedFilename()) === `print-job-${jobId}.pdf`, await download.suggestedFilename());
    await Promise.all([office.waitForResponse((r) => r.url().includes(`/print/jobs/${jobId}/start`)), card.locator(`[data-testid="queue-start-${jobId}"]`).click()]);
    await office.waitForLoadState('networkidle');
    const printingLabel = say('print.job_status_printing', 'en');
    const printingSeen = await office.waitForFunction(([id, want]) => document.querySelector(`[data-testid="queue-status-${id}"]`)?.textContent?.trim() === want, [jobId, printingLabel], { timeout: 10000 }).then(() => true).catch(() => false);
    check('Start printing marks it printing', printingSeen, flat(await card.locator(`[data-testid="queue-status-${jobId}"]`).textContent()));
    await Promise.all([office.waitForResponse((r) => r.url().includes(`/print/jobs/${jobId}/finish`)), card.locator(`[data-testid="queue-finish-${jobId}"]`).click()]);
    await office.waitForLoadState('networkidle');
    const finishedLabel = say('print.job_status_finished', 'en');
    const finishedSeen = await office.waitForFunction(([id, want]) => document.querySelector(`[data-testid="queue-status-${id}"]`)?.textContent?.trim() === want, [jobId, finishedLabel], { timeout: 10000 }).then(() => true).catch(() => false);
    check('Mark finished marks it finished', finishedSeen, flat(await card.locator(`[data-testid="queue-status-${jobId}"]`).textContent()));

    // The customer's order, in Arabic.
    await customer.goto(`${BASE}/ar/my-orders/${orderNumber}`, { waitUntil: 'networkidle' });
    const status = customer.locator('[data-testid="order-status"]').first();
    check('/ar the customer\'s order reads ready to collect', (await status.getAttribute('data-status')) === 'ready' && flat(await status.textContent()).includes(say('shop.status_ready', 'ar')), flat(await status.textContent()));
    const body = await customer.content();
    check('with the print steps in Arabic and a link to the design', body.includes(say('shop.event_print_printing', 'ar')) && body.includes(say('shop.event_print_finished', 'ar')) && (await customer.locator('[data-testid="order-print-design"]').count()) === 1);

    // The customer never gets the print file.
    const refused = await customer.request.get(`${BASE}/en/admin/bookshop/print/jobs/${jobId}/file`, { maxRedirects: 0 });
    check('the print file is refused to the customer', [302, 403, 404].includes(refused.status()), String(refused.status()));
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    cleanUp();
    await browser.close();
}

const passed = results.filter(([, ok]) => ok).length;
for (const [step, ok, detail] of results) console.log(`${ok ? 'PASS' : 'FAIL'}  ${step}${detail && !ok ? `  — ${detail}` : ''}`);
for (const p of problems) console.log(`PROBLEM  ${p}`);
console.log(`${passed}/${results.length} steps passed`);
process.exit(passed === results.length && problems.length === 0 ? 0 : 1);
