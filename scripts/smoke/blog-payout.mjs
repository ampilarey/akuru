/**
 * Is an under-18 blogger's payout paid into the consenting parent's Akuru
 * wallet, and never a bank? (BLOG_PLAN BL6b, STATUS §5sn.)
 *
 * A 13-year-old's blog is made for the run, with a parent who consented and
 * MVR 120 of available earnings, and payouts are turned on for the run. Under
 * /dv the pupil opens My blog: it says payouts go to the parent's wallet and
 * offers *Ask for a payout*. The office sees the request on the Library
 * office, marked for the parent's wallet, and marks it paid. The parent's
 * wallet holds MVR 120; the pupil's holds nothing.
 *
 * Everything the walk made is removed, and the payouts setting put back.
 * Local server only.
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
    console.log('blog-payout changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const before = tinker(`echo json_encode(DB::table('settings')->where('key', 'library.payouts_enabled')->first());`);
const made = JSON.parse(tinker(`$u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['email' => 'walk-payout-pupil@example.test', 'password' => bcrypt('${PASSWORD}')]); $s = \\App\\Domains\\People\\Models\\Student::query()->create(['user_id' => $u->id, 'first_name' => 'Walk', 'last_name' => 'Payout', 'date_of_birth' => now()->subYears(13)->toDateString(), 'gender' => 'female']); $pu = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'Walk Parent', 'email' => 'walk-payout-parent@example.test']); $g = \\App\\Domains\\People\\Models\\ParentGuardian::query()->create(['user_id' => $pu->id, 'first_name' => 'Walk', 'last_name' => 'Parent', 'relationship' => 'mother', 'phone' => '7000001', 'email' => $pu->email, 'address' => 'Malé']); app(\\App\\Domains\\People\\Actions\\AttachGuardianAction::class)->execute($s, $g, 'mother', true); app(\\App\\Domains\\People\\Actions\\RecordConsentAction::class)->execute('student', $s->id, 'blog_publishing', true, $pu->id, 'portal'); $p = \\App\\Domains\\Library\\Models\\WriterProfile::query()->create(['user_id' => $u->id, 'kind' => 'student', 'display_name' => 'ތަރި', 'slug' => 'walk-payout-blog', 'status' => 'active', 'approved_at' => now(), 'guardian_user_id' => $pu->id]); $t = \\App\\Domains\\Library\\Models\\LibraryTip::query()->create(['user_id' => $pu->id, 'writer_id' => $p->id, 'amount' => 120, 'method' => 'wallet', 'status' => 'paid', 'paid_at' => now()->subDays(30)]); \\App\\Domains\\Library\\Models\\WriterEarning::query()->create(['writer_id' => $p->id, 'kind' => 'tip', 'library_tip_id' => $t->id, 'gross_amount' => 120, 'discount_amount' => 0, 'wallet_amount' => 120, 'bml_amount' => 0, 'platform_commission' => 0, 'writer_amount' => 120, 'status' => 'available', 'available_at' => now()->subDay()]); app(\\App\\Domains\\Settings\\Actions\\SetSettingAction::class)->execute('library.payouts_enabled', '1', 'boolean', 'library'); echo json_encode(['pupil' => $u->id, 'student' => $s->id, 'parent' => $pu->id, 'guardian' => $g->id, 'profile' => $p->id, 'tip' => $t->id]);`));
const cleanUp = () => {
    tinker(`$payouts = DB::table('writer_payouts')->where('writer_id', ${made.profile})->pluck('id'); $wallets = DB::table('wallets')->whereIn('user_id', [${made.pupil}, ${made.parent}])->pluck('id'); DB::table('wallet_transactions')->whereIn('wallet_id', $wallets)->delete(); DB::table('wallets')->whereIn('id', $wallets)->delete(); DB::table('writer_earnings')->where('writer_id', ${made.profile})->delete(); DB::table('writer_payouts')->whereIn('id', $payouts)->delete(); DB::table('library_tips')->where('id', ${made.tip})->delete(); DB::table('writer_profiles')->where('id', ${made.profile})->delete(); DB::table('consents')->where('person_type', 'student')->where('person_id', ${made.student})->delete(); DB::table('guardian_student')->where('student_id', ${made.student})->delete(); DB::table('student_parent')->where('student_id', ${made.student})->delete(); DB::table('user_notifications')->whereIn('user_id', [${made.pupil}, ${made.parent}])->delete(); DB::table('parent_guardians')->where('id', ${made.guardian})->delete(); DB::table('students')->where('id', ${made.student})->delete(); DB::table('users')->whereIn('id', [${made.pupil}, ${made.parent}])->delete(); echo 'ok';`);
    const was = JSON.parse(before);
    tinker(was ? `app(\\App\\Domains\\Settings\\Actions\\SetSettingAction::class)->execute('library.payouts_enabled', '${was.value}', 'boolean', 'library'); echo 'ok';` : `DB::table('settings')->where('key', 'library.payouts_enabled')->delete(); Cache::flush(); echo 'ok';`);
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
    const pupil = await signIn('walk-payout-pupil@example.test', 'dv');
    await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
    check('My blog under /dv says payouts go to the parent\'s wallet, in Dhivehi', (await pupil.locator('html').getAttribute('dir')) === 'rtl' && flat(await pupil.locator('[data-testid="blog-earnings"]').textContent()).includes(say('blog.earnings_to_parent')));
    check('and offers no bank details, only a payout', (await pupil.locator('input[name="account_number"]').count()) === 0 && (await pupil.locator('[data-testid="blog-payout"]').count()) === 1);
    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="blog-payout"]')]);
    const payout = tinker(`echo DB::table('writer_payouts')->where('writer_id', ${made.profile})->value('id');`);
    check('asking makes a wallet payout for the parent', tinker(`echo DB::table('writer_payouts')->where('id', ${payout})->value('wallet_user_id');`) === String(made.parent));

    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const mark = office.locator(`[data-testid="payout-wallet-${payout}"]`);
    check('the office sees it marked for the parent\'s wallet', flat(await mark.textContent()).includes('Walk Parent'));
    await Promise.all([office.waitForLoadState('networkidle'), mark.locator('xpath=ancestor::tr').locator('button.btn-primary').click()]);
    await office.waitForFunction((id) => !document.querySelector(`[data-testid="payout-wallet-${id}"]`), payout);
    check('marked paid, the parent\'s wallet holds MVR 120 and the pupil\'s nothing', tinker(`echo DB::table('wallets')->where('user_id', ${made.parent})->value('balance');`).startsWith('120') && !tinker(`echo DB::table('wallets')->where('user_id', ${made.pupil})->value('balance') ?? 0;`).startsWith('120'));
    await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
    check('My blog shows it paid', flat(await pupil.locator('[data-testid="blog-earnings"]').textContent()).includes('120.00') && (await pupil.locator('[data-testid="blog-payout"]').count()) === 0);
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
