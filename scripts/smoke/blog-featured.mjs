/**
 * Can the office feature a post this month and reward it, does a writer see
 * their readers, and does a student's blog become a writer's at 18?
 * (BLOG_PLAN BL7, STATUS §5so.)
 *
 * Two student blogs are made for the run: a 15-year-old's post with three
 * readers, and an 18-year-old's. Rewards are turned on for the run (cap MVR
 * 100). On the Library office's Blog insights the office sees the post with
 * three readers, types a reward of MVR 50 and features it: a reward earning,
 * Akuru-funded, is written. Under /ar the blogs' front shows it as Featured
 * this month, right to left. The writer sees "3 readers" on My blog. The daily
 * growing-up command moves the 18-year-old's blog to a writer's, page and
 * posts kept.
 *
 * Everything the walk made is removed and the settings put back. Local
 * server only.
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
    console.log('blog-featured changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const settingsBefore = tinker(`echo json_encode(DB::table('settings')->whereIn('key', ['library.reward_enabled', 'library.reward_cap'])->get(['key', 'value']));`);
const blog = (tag, years) => JSON.parse(tinker(`$u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['email' => 'walk-featured-${tag}@example.test', 'password' => bcrypt('${PASSWORD}')]); $s = \\App\\Domains\\People\\Models\\Student::query()->create(['user_id' => $u->id, 'first_name' => 'Walk', 'last_name' => '${tag}', 'date_of_birth' => now()->subYears(${years})->subDay()->toDateString(), 'gender' => 'female']); $p = \\App\\Domains\\Library\\Models\\WriterProfile::query()->create(['user_id' => $u->id, 'kind' => 'student', 'display_name' => 'ތަރި ${tag}', 'slug' => 'walk-featured-${tag}', 'status' => 'active', 'approved_at' => now()]); $i = \\App\\Domains\\Library\\Models\\LibraryItem::query()->create(['title' => 'Walk featured ${tag}', 'slug' => 'walk-featured-${tag}-post', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'dv', 'status' => 'published', 'published_at' => now()->subDay(), 'readers_notified_at' => now(), 'writer_id' => $p->id, 'body' => '<p>ބަގީޗާ</p>']); echo json_encode(['user' => $u->id, 'student' => $s->id, 'profile' => $p->id, 'item' => $i->id]);`));
const teen = blog('teen', 15);
const adult = blog('adult', 18);
for (const session of ['w1', 'w2', 'w3']) {
    tinker(`\\App\\Domains\\Library\\Models\\LibraryReadingEvent::query()->create(['library_item_id' => ${teen.item}, 'page_number' => 1, 'kind' => 'page_view', 'session_hash' => 'walk-${session}', 'occurred_at' => now()]); echo 'ok';`);
}
tinker(`$set = app(\\App\\Domains\\Settings\\Actions\\SetSettingAction::class); $set->execute('library.reward_enabled', '1', 'boolean', 'library'); $set->execute('library.reward_cap', '100', 'integer', 'library'); echo 'ok';`);
const cleanUp = () => {
    for (const b of [teen, adult]) {
        tinker(`DB::table('writer_earnings')->where('writer_id', ${b.profile})->delete(); DB::table('library_reading_events')->where('library_item_id', ${b.item})->delete(); DB::table('library_item_pages')->where('library_item_id', ${b.item})->delete(); DB::table('library_items')->where('id', ${b.item})->delete(); DB::table('user_notifications')->where('user_id', ${b.user})->delete(); DB::table('writer_profiles')->where('id', ${b.profile})->delete(); DB::table('students')->where('id', ${b.student})->delete(); DB::table('users')->where('id', ${b.user})->delete(); echo 'ok';`);
    }
    const was = JSON.parse(settingsBefore);
    tinker(`DB::table('settings')->whereIn('key', ['library.reward_enabled', 'library.reward_cap'])->delete(); ${was.map((row) => `DB::table('settings')->insert(['key' => '${row.key}', 'value' => '${row.value}', 'created_at' => now(), 'updated_at' => now()]);`).join(' ')} Cache::flush(); echo 'ok';`);
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
    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/library/blog-insights`, { waitUntil: 'networkidle' });
    const row = office.locator(`[data-testid="insights-post-${teen.item}"]`);
    check('Blog insights lists the post with its three readers', (await row.count()) === 1 && flat(await row.locator('td').nth(1).textContent()) === '3');
    await office.fill(`[data-testid="insights-reward-${teen.item}"]`, '50');
    await Promise.all([office.waitForLoadState('networkidle'), office.click(`[data-testid="insights-feature-${teen.item}"]`)]);
    await office.waitForSelector(`[data-testid="insights-featured-${teen.item}"]`);
    check('featuring it with a reward marks it, MVR 50', flat(await office.locator(`[data-testid="insights-featured-${teen.item}"]`).textContent()).includes('50.00'));
    check('the reward is an Akuru-funded earning, no payment behind it', tinker(`echo json_encode(DB::table('writer_earnings')->where('writer_id', ${teen.profile})->first(['kind', 'writer_amount', 'bml_amount', 'wallet_amount', 'library_tip_id', 'library_purchase_id']));`) === JSON.stringify({ kind: 'reward', writer_amount: '50.00', bml_amount: '0.00', wallet_amount: '0.00', library_tip_id: null, library_purchase_id: null }));

    const reader = await (await browser.newContext()).newPage();
    await reader.goto(`${BASE}/ar/library/blogs`, { waitUntil: 'networkidle' });
    check('under /ar the blogs\' front shows it as Featured this month, right to left', (await reader.locator('html').getAttribute('dir')) === 'rtl' && flat(await reader.locator('[data-testid="blogs-featured"] h2').textContent()) === say('public.blogs_featured', 'ar') && (await reader.locator(`[data-featured="walk-featured-teen-post"]`).count()) === 1);

    const writer = await signIn('walk-featured-teen@example.test', 'dv');
    await writer.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
    check('the writer sees three readers on My blog, in Dhivehi', flat(await writer.locator(`[data-testid="post-reads-${teen.item}"]`).textContent()) === say('blog.post_reads').replace(':count', '3'));

    // The daily move at 18.
    const out = execFileSync('php', ['artisan', 'library:blogs-growing-up'], { encoding: 'utf8' });
    check('the growing-up run moves the 18-year-old\'s blog to a writer\'s', /1/.test(out) && tinker(`echo DB::table('writer_profiles')->where('id', ${adult.profile})->value('kind');`) === 'writer' && tinker(`echo DB::table('writer_profiles')->where('id', ${teen.profile})->value('kind');`) === 'student', out.trim());
    const page = await reader.goto(`${BASE}/en/library/authors/walk-featured-adult`, { waitUntil: 'networkidle' });
    check('and the page and its post stay', page.status() === 200 && (await reader.content()).includes('Walk featured adult'));
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
