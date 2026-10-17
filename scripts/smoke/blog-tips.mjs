/**
 * Can a reader tip a blog's writer, does the writer see their share, and is
 * the thank-you note checked first? (BLOG_PLAN BL6a, STATUS §5sm.)
 *
 * A student blog with tips on and one post is made for the run, and a reader
 * with MVR 30 in their wallet. Under /dv the reader tips MVR 10 from the
 * wallet on the post, with a note. A card tip goes to the bank, or says
 * plainly that the payment could not start (no gateway keys on this host:
 * BML's webhook is the only way a card tip is paid, and the feature tests
 * drive it with the payment fake). The writer sees the tip on My blog with
 * their share and no note; the office publishes the note on Blog tips; the
 * writer then reads it, and the writer's CSV carries it.
 *
 * Everything the walk made is removed: the blog, the reader and their
 * synthetic wallet. Local server only.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('blog-tips changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const SLUG = 'walk-tips-blog';
const made = JSON.parse(tinker(`$w = \\App\\Domains\\Identity\\Models\\User::factory()->create(['email' => 'walk-tips-writer@example.test', 'password' => bcrypt('${PASSWORD}')]); $r = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'Walk Reader', 'email' => 'walk-tips-reader@example.test', 'password' => bcrypt('${PASSWORD}')]); $p = \\App\\Domains\\Library\\Models\\WriterProfile::query()->create(['user_id' => $w->id, 'kind' => 'writer', 'display_name' => 'ތަރި', 'blog_title' => 'މާލޭގެ ނޯޓްތައް', 'slug' => '${SLUG}', 'status' => 'active', 'approved_at' => now(), 'tips_enabled' => true]); $i = \\App\\Domains\\Library\\Models\\LibraryItem::query()->create(['title' => 'Walk tip post', 'slug' => '${SLUG}-post', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now(), 'readers_notified_at' => now(), 'writer_id' => $p->id, 'body' => '<p>Beans.</p>']); app(\\App\\Domains\\Commerce\\Actions\\CreditWalletAction::class)->execute($r->id, 30, 'gift_card', null, 'Walk wallet'); echo json_encode(['writer' => $w->id, 'reader' => $r->id, 'profile' => $p->id, 'item' => $i->id]);`));
const cleanUp = () => {
    tinker(`$tips = DB::table('library_tips')->where('writer_id', ${made.profile})->orderByDesc('id')->pluck('id'); DB::table('writer_earnings')->whereIn('library_tip_id', $tips)->delete(); foreach ($tips as $t) { DB::table('library_tips')->where('id', $t)->delete(); } DB::table('payments')->where('user_id', ${made.reader})->delete(); DB::table('user_notifications')->whereIn('user_id', [${made.writer}, ${made.reader}])->delete(); DB::table('library_item_pages')->where('library_item_id', ${made.item})->delete(); DB::table('library_items')->where('id', ${made.item})->delete(); DB::table('writer_profiles')->where('id', ${made.profile})->delete(); $wallets = DB::table('wallets')->where('user_id', ${made.reader})->pluck('id'); DB::table('wallet_transactions')->whereIn('wallet_id', $wallets)->delete(); DB::table('wallets')->whereIn('id', $wallets)->delete(); DB::table('users')->whereIn('id', [${made.writer}, ${made.reader}])->delete(); echo 'ok';`);
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
    const reader = await signIn('walk-tips-reader@example.test', 'dv');
    await reader.goto(`${BASE}/dv/library/${SLUG}-post`, { waitUntil: 'networkidle' });
    check('the post under /dv offers a tip box, in Dhivehi', (await reader.locator('html').getAttribute('dir')) === 'rtl' && flat(await reader.locator('[data-testid="tip-box"] summary').textContent()) === say('blog.tip_heading'));
    await reader.click('[data-testid="tip-box"] summary');
    await reader.check('[data-testid="tip-amount-10"]');
    await reader.fill('[data-testid="tip-note"]', 'Walk: keep writing!');
    await reader.check('[data-testid="tip-wallet"]');
    await Promise.all([reader.waitForLoadState('networkidle'), reader.click('[data-testid="tip-send"]')]);
    check('a wallet tip is paid at once, and the reader thanked', flat(await reader.locator('body').textContent()).includes(say('blog.flash_tip_thanks')) && tinker(`echo DB::table('library_tips')->where('writer_id', ${made.profile})->where('status', 'paid')->count();`) === '1');
    check('it is on the post, and the wallet paid MVR 10', tinker(`echo DB::table('library_tips')->where('writer_id', ${made.profile})->value('library_item_id');`) === String(made.item) && tinker(`echo DB::table('wallets')->where('user_id', ${made.reader})->value('balance');`).startsWith('20'));

    // By card: the bank, or plainly told it could not start.
    await reader.goto(`${BASE}/dv/library/authors/${SLUG}`, { waitUntil: 'networkidle' });
    await reader.click('[data-testid="tip-box"] summary');
    const offSite = [];
    reader.on('request', (r) => { if (!r.url().startsWith(BASE)) offSite.push(r.url()); });
    await Promise.all([reader.waitForLoadState('networkidle'), reader.click('[data-testid="tip-send"]')]).catch(() => {});
    const wentToBank = offSite.some((url) => /bml|bankofmaldives|connect/i.test(url));
    const toldPlainly = (await reader.locator('[data-testid="tip-box"] [role="alert"]').count()) > 0;
    check('a card tip goes to the bank, or says plainly the payment could not start', wentToBank || toldPlainly);
    check('and grants nothing on the way', tinker(`echo DB::table('library_tips')->where('writer_id', ${made.profile})->where('status', 'paid')->count();`) === '1' && tinker(`echo DB::table('writer_earnings')->where('writer_id', ${made.profile})->count();`) === '1');

    // The writer sees their share, not yet the note.
    const writer = await signIn('walk-tips-writer@example.test', 'en');
    await writer.goto(`${BASE}/en/my-blog`, { waitUntil: 'networkidle' });
    const tipId = tinker(`echo DB::table('library_tips')->where('writer_id', ${made.profile})->where('status', 'paid')->value('id');`);
    const row = () => writer.locator(`[data-testid="blog-tip-${tipId}"]`);
    check('My blog lists the tip with the writer\'s share, and no note yet', flat(await row().textContent()).includes('MVR 10.00') && flat(await row().textContent()).includes('9.00') && !flat(await row().textContent()).includes('Walk: keep writing!'));

    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/library/tips?notes=1`, { waitUntil: 'networkidle' });
    check('the office sees the note waiting', flat(await office.locator(`[data-testid="admin-tip-${tipId}"]`).textContent()).includes('Walk: keep writing!'));
    await Promise.all([office.waitForLoadState('networkidle'), office.click(`[data-testid="admin-tip-publish-${tipId}"]`)]);
    await writer.goto(`${BASE}/en/my-blog`, { waitUntil: 'networkidle' });
    check('published, the writer reads the note', flat(await row().textContent()).includes('Walk: keep writing!'));
    const csv = await writer.request.get(`${BASE}/en/my-blog/tips/export`);
    check('and the writer\'s CSV carries it', csv.status() === 200 && (await csv.text()).includes('Walk: keep writing!'));
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
