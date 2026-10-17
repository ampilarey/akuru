/**
 * Can a reader follow a blog, hear of its next post, read its feed, and stop
 * following from My Library? (BLOG_PLAN BL4, STATUS §5sk.)
 *
 * A student blog with one published post is made for the run. A signed-in
 * reader (teacher@) opens it under /dv, follows it and asks for email too.
 * The office publishes the blog's next post on the Library office. The reader
 * finds the notice among their notifications; the blog's RSS feed lists the
 * new post and not a draft; My Library lists the blog, and they stop
 * following there.
 *
 * Everything the walk made is removed. Local server only.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_TEACHER, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const READER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('blog-follow changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const SLUG = 'walk-follow-blog';
const reader = Number(tinker(`echo DB::table('users')->where('email', '${READER}')->value('id');`));
const made = JSON.parse(tinker(`$u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['name' => 'Walk blogger']); $p = \\App\\Domains\\Library\\Models\\WriterProfile::query()->create(['user_id' => $u->id, 'kind' => 'student', 'display_name' => 'ތަރި', 'blog_title' => 'މާލޭގެ ނޯޓްތައް', 'slug' => '${SLUG}', 'status' => 'active', 'approved_at' => now()]); \\App\\Domains\\Library\\Models\\LibraryItem::query()->create(['title' => 'Walk first post', 'slug' => '${SLUG}-1', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now(), 'readers_notified_at' => now(), 'writer_id' => $p->id, 'body' => '<p>One.</p>']); $next = \\App\\Domains\\Library\\Models\\LibraryItem::query()->create(['title' => 'Walk next post', 'slug' => '${SLUG}-2', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'submitted', 'review_stage' => 'office', 'submitted_at' => now(), 'writer_id' => $p->id, 'body' => '<p>Two.</p>', 'declarations' => ['copyright' => true]]); \\App\\Domains\\Library\\Models\\LibraryItem::query()->create(['title' => 'Walk draft post', 'slug' => '${SLUG}-3', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'draft', 'writer_id' => $p->id, 'body' => '<p>Three.</p>']); echo json_encode(['user' => $u->id, 'profile' => $p->id, 'next' => $next->id]);`));
const cleanUp = () => {
    tinker(`$ids = DB::table('library_items')->where('writer_id', ${made.profile})->pluck('id'); DB::table('user_notifications')->where('data', 'like', '%${SLUG}%')->delete(); DB::table('library_item_reviews')->whereIn('library_item_id', $ids)->delete(); DB::table('library_item_pages')->whereIn('library_item_id', $ids)->delete(); DB::table('library_items')->whereIn('id', $ids)->delete(); DB::table('library_follows')->where('writer_id', ${made.profile})->delete(); DB::table('writer_profiles')->where('id', ${made.profile})->delete(); DB::table('users')->where('id', ${made.user})->delete(); echo 'ok';`);
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
    // A guest is asked to sign in.
    const guest = await (await browser.newContext()).newPage();
    await guest.goto(`${BASE}/dv/library/authors/${SLUG}`, { waitUntil: 'networkidle' });
    await Promise.all([guest.waitForLoadState('networkidle'), guest.click('[data-testid="follow-button"]')]);
    check('a guest pressing Follow is sent to sign in', /\/login/.test(guest.url()), guest.url());

    const page = await signIn(READER, 'dv');
    await page.goto(`${BASE}/dv/library/authors/${SLUG}`, { waitUntil: 'networkidle' });
    check('the blog under /dv offers Follow, in Dhivehi', (await page.locator('html').getAttribute('dir')) === 'rtl' && flat(await page.locator('[data-testid="follow-button"]').textContent()) === say('public.follow_follow'));
    await Promise.all([page.waitForLoadState('networkidle'), page.click('[data-testid="follow-button"]')]);
    check('following it, the count is one', (await page.locator('[data-testid="follow-box"]').getAttribute('data-following')) === 'yes' && tinker(`echo DB::table('writer_profiles')->where('id', ${made.profile})->value('followers_count');`) === '1');
    await Promise.all([page.waitForLoadState('networkidle'), page.click('[data-testid="follow-email"]')]);
    check('and the reader asks for email too', tinker(`echo (int) DB::table('library_follows')->where('user_id', ${reader})->where('writer_id', ${made.profile})->value('notify_email');`) === '1');

    // The office publishes the next post.
    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const row = office.locator('tr', { hasText: 'Walk next post' }).filter({ has: office.locator('[data-testid="approve-publish"]') });
    await Promise.all([office.waitForLoadState('networkidle'), row.locator('[data-testid="approve-publish"]').click()]);
    check('the office publishes the next post', tinker(`echo DB::table('library_items')->where('id', ${made.next})->value('status');`) === 'published');

    await page.goto(`${BASE}/dv/portal/notifications`, { waitUntil: 'networkidle' });
    check('the reader finds the notice among their notifications', (await page.content()).includes('Walk next post'));
    const feed = await page.request.get(`${BASE}/en/library/authors/${SLUG}/feed`);
    const xml = await feed.text();
    check('the blog\'s RSS feed lists the new post, not the draft', feed.status() === 200 && xml.startsWith('<?xml') && xml.includes('<title>Walk next post</title>') && !xml.includes('Walk draft post'), String(feed.status()));

    // My Library lists the blog; the reader stops following there.
    await page.goto(`${BASE}/dv/my-library`, { waitUntil: 'networkidle' });
    check('My Library lists the blog under Blogs I follow', (await page.locator(`[data-testid="following-${SLUG}"]`).count()) === 1 && (await page.content()).includes(say('public.follow_my_heading')));
    await Promise.all([page.waitForLoadState('networkidle'), page.click(`[data-testid="unfollow-${SLUG}"]`)]);
    check('stopping there leaves no followers', (await page.locator(`[data-testid="following-${SLUG}"]`).count()) === 0 && tinker(`echo DB::table('writer_profiles')->where('id', ${made.profile})->value('followers_count');`) === '0');
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
