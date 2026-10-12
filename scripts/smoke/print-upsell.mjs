/**
 * Are name tags offered beside a school's book list, can a design be
 * ordered again, and does the office see print sales? (NAME_TAGS_PLAN §3.9,
 * slice NT7, STATUS §5ru.)
 *
 * The walk switches the print desk on and makes a Grade 3 book list for
 * itself, then: a parent opens the list under /dv and finds the name-tag
 * card in Dhivehi; *Add name tags* opens a design with Grade 3's subjects
 * already ticked; *Add all* puts the books in the cart, which offers the
 * card again. A design the walk approves is ordered again from *My designs*
 * as a new draft at today's price. The office sees *Print sales by paper
 * and month* with its CSV. Everything is put back. Local server only.
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
    console.log('print-upsell changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv', vars = '[]') => tinker(`echo __('${key}', ${vars}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const saved = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->where('kind', 'glossy')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`));
const paperId = saved.paper.id;
tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => 4.50, 'price_bw' => 2.00, 'is_active' => true]); DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->update(['status' => 'active']); Cache::flush(); echo 'ok';`);
const productWas = tinker(`echo \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->getOriginal('status');`);
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
// A Grade 3 book list on an active shop, with one book in stock.
const list = JSON.parse(tinker(`$v = \\App\\Domains\\Bookshop\\Models\\Vendor::query()->where('status', 'active')->where('kind', '!=', 'akuru')->whereHas('products', fn ($q) => $q->where('status', 'active')->where('stock', '>', 0))->first(); $p = $v->products()->where('status', 'active')->where('stock', '>', 0)->first(); $c = \\App\\Domains\\Bookshop\\Models\\VendorCollection::query()->create(['vendor_id' => $v->id, 'slug' => 'smoke-grade-3-list', 'name' => 'Smoke Grade 3 list', 'book_list' => true, 'school' => 'Smoke School', 'grade' => 'Grade 3', 'is_active' => true]); $c->products()->attach($p->id, ['sort_order' => 0, 'quantity' => 1]); echo json_encode(['vendor' => $v->slug, 'list' => $c->slug, 'id' => $c->id]);`));
const cleanUp = () => {
    tinker(`DB::table('vendor_collection_products')->where('vendor_collection_id', ${list.id})->delete(); DB::table('vendor_collections')->where('id', ${list.id})->delete(); DB::table('print_jobs')->where('user_id', ${customerId})->where('created_at', '>=', now()->subHours(3))->delete(); $c = DB::table('carts')->where('user_id', ${customerId})->value('id'); if ($c) { DB::table('cart_items')->where('cart_id', $c)->whereNull('saved_at')->delete(); } DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => ${saved.paper.price_colour ?? 0}, 'price_bw' => ${saved.paper.price_bw ?? 0}, 'is_active' => ${saved.paper.is_active ? 1 : 0}]); ${saved.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${saved.enabled}']);`} \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->update(['status' => '${productWas}']); Cache::flush(); echo 'ok';`);
};

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const signIn = async (who, locale) => {
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/${locale}/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(who);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    return page;
};

try {
    const parent = await signIn(CUSTOMER, 'dv');
    const listUrl = tinker(`echo route('public.shop.vendor.collection', ['${list.vendor}', '${list.list}'], false);`).replace(/^\/(en|dv|ar)\//, '/');
    await parent.goto(`${BASE}/dv${listUrl.startsWith('/') ? listUrl : `/${listUrl}`}`, { waitUntil: 'networkidle' });
    const card = parent.locator('[data-testid="print-upsell"]');
    check('/dv the book list offers name tags for this child, in Dhivehi', (await card.count()) === 1 && flat(await card.textContent()).includes(say('print.upsell_heading')), parent.url().replace(BASE, ''));

    await Promise.all([parent.waitForURL(/\/shop\/print\/jobs\/\d+$/), card.locator('[data-testid="print-upsell-start"]').click()]);
    await parent.waitForLoadState('networkidle');
    const subjectSet = await parent.locator('[data-testid="print-subject-set"]').inputValue().catch(() => '');
    const gradeSet = tinker(`echo \\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintJobAction::subjectSetFor('Grade 3')?->id;`);
    const ticked = await parent.locator('[data-testid="print-subjects"] input[type=checkbox]:checked').count();
    check('Add name tags opens a design with Grade 3\'s subjects chosen', subjectSet === gradeSet && ticked > 0, `${subjectSet}/${gradeSet} ticked ${ticked}`);

    await parent.goto(`${BASE}/dv${listUrl.startsWith('/') ? listUrl : `/${listUrl}`}`, { waitUntil: 'networkidle' });
    await Promise.all([parent.waitForURL(/\/shop\/cart$/), parent.locator('[data-testid="book-list-add"]').click()]);
    await parent.waitForLoadState('networkidle');
    check('after Add all, the cart offers the card again', (await parent.locator('[data-testid="print-upsell-start"]').count()) === 1);

    // Order again: an approved design, ordered again from My designs.
    const approvedId = tinker(`$t = \\App\\Domains\\Bookshop\\Models\\PrintTemplate::query()->where('kind', 'name_tag')->where('status', 'active')->orderBy('sort_order')->firstOrFail(); $s = app(\\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintJobAction::class); $j = $s->update($s->start($t, ${customerId}, null, 'en'), ['entry' => ['mode' => 'same', 'row' => ['name' => 'Ali']], 'sheets' => 1, 'colour' => true, 'paper_id' => ${paperId}], 'en'); $p = app(\\App\\Domains\\Bookshop\\Actions\\Print\\ProofPrintJobAction::class); echo $p->approve($p->proof($j, ${customerId}, 'en'), 'en')->id;`);
    await parent.goto(`${BASE}/dv/my-designs`, { waitUntil: 'networkidle' });
    const again = parent.locator(`[data-testid="print-design-again-${approvedId}"]`);
    check('My designs offers Order again on the approved design, in Dhivehi', (await again.count()) === 1 && flat(await again.textContent()) === say('print.order_again'));
    await Promise.all([parent.waitForURL(/\/shop\/print\/jobs\/\d+$/), again.click()]);
    await parent.waitForLoadState('networkidle');
    const newId = Number(new URL(parent.url()).pathname.split('/').pop());
    check('it opens a new draft copy, saying it is at today\'s price', newId !== Number(approvedId) && flat(await parent.locator('[data-testid="print-job-status"]').textContent()) === say('print.job_status_draft') && (await parent.content()).includes(say('print.flash_order_again')), `${newId}`);

    // The office's print sales.
    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
    const sales = office.locator('[data-testid="office-print-sales"]');
    check('the office sees Print sales by paper and month, with a CSV', (await sales.count()) === 1 && flat(await sales.locator('h2').textContent()) === say('shop.print_sales_heading', 'en') && (await sales.locator('[data-testid="export-print-sales"]').count()) === 1);
    const csv = await office.request.get(`${BASE}/en/admin/bookshop/insights/print-sales/export`);
    check('and the CSV downloads', csv.status() === 200 && (await csv.text()).startsWith('month,paper,jobs,sheets,revenue'), String(csv.status()));
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
    cleanUp();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
