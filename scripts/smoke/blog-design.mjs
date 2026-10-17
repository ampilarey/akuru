/**
 * Can a blogger design their page, and does it show only once the office
 * approves? (BLOG_PLAN BL3a and BL3b, STATUS §5si, §5sj.)
 *
 * The seeded pupil (student@) is given a student blog with one published
 * post for the run. Under /dv they open the designer, choose the Night theme,
 * try an accent that does not read and are refused with the reason, choose
 * one that does, write a tagline and an *about*, move *About me* to the top
 * and switch the series off, preview right to left, save and send. Readers
 * still see the old page. The office sees the set beside the live page and
 * approves. Under /ar the page is Night, with the tagline, *About* first.
 * BL3b: a banner chosen in the designer shows in the preview, waits privately
 * for the office (which sees it through its own route), and is public once
 * approved; the pupil, under 18 for the run, is told links need a parent.
 *
 * The walk puts everything back: the post, the profile, the role and the
 * date of birth. Local
 * server only.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('blog-design changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv', replace = '[]') => tinker(`echo __('${key}', ${replace}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const setup = JSON.parse(tinker(`$u = DB::table('users')->where('email', '${STUDENT}')->first(); $s = DB::table('students')->where('user_id', $u->id)->orderBy('id')->first(); echo json_encode(['user' => $u->id, 'student' => $s?->id, 'dob' => $s?->date_of_birth, 'writer' => DB::table('writer_profiles')->where('user_id', $u->id)->exists()]);`));
if (setup.writer) {
    console.log('blog-design needs the seeded pupil without a blog of their own yet. 0/1 steps passed');
    process.exit(1);
}
const SLUG = `walk-design-${setup.user}`;
const mediaBefore = tinker(`echo (int) DB::table('media_files')->max('id');`);
// BL3b: the pupil is under 18 for the run, so links need a parent's yes.
if (setup.student) tinker(`DB::table('students')->where('id', ${setup.student})->update(['date_of_birth' => '2013-06-01']); echo 'ok';`);
const cleanUp = () => {
    if (setup.student) tinker(`DB::table('students')->where('id', ${setup.student})->update(['date_of_birth' => ${setup.dob ? `'${setup.dob}'` : 'null'}]); echo 'ok';`);
    // The banner the walk uploaded, private and public copies both.
    tinker(`DB::table('writer_profiles')->where('slug', '${SLUG}')->update(['banner_media_file_id' => null, 'photo_media_file_id' => null, 'pending_page' => null]); foreach (DB::table('media_files')->where('id', '>', ${mediaBefore})->where(fn ($q) => $q->where('path', 'like', 'blog-pages/%')->orWhere('uploaded_by', ${setup.user}))->get() as $m) { Storage::disk($m->disk)->delete($m->path); DB::table('media_files')->where('id', $m->id)->delete(); } echo 'ok';`);
    tinker(`$p = DB::table('writer_profiles')->where('user_id', ${setup.user})->where('slug', '${SLUG}')->value('id'); if ($p) { $ids = DB::table('library_items')->where('writer_id', $p)->pluck('id'); DB::table('library_item_pages')->whereIn('library_item_id', $ids)->delete(); DB::table('library_items')->whereIn('id', $ids)->delete(); DB::table('writer_profiles')->where('id', $p)->delete(); } \\App\\Domains\\Identity\\Models\\User::find(${setup.user})?->removeRole('writer'); echo 'ok';`);
};
const profileId = tinker(`$p = \\App\\Domains\\Library\\Models\\WriterProfile::query()->create(['user_id' => ${setup.user}, 'kind' => 'student', 'display_name' => 'ތަރި', 'blog_title' => 'މާލޭގެ ނޯޓްތައް', 'slug' => '${SLUG}', 'status' => 'active', 'approved_at' => now(), 'bio' => 'The old about.']); \\App\\Domains\\Library\\Models\\LibraryItem::query()->create(['title' => 'ބަގީޗާ', 'slug' => '${SLUG}-post', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'dv', 'status' => 'published', 'published_at' => now(), 'writer_id' => $p->id, 'body' => '<p>ބަގީޗާ</p>']); \\App\\Domains\\Identity\\Models\\User::find(${setup.user})->assignRole('writer'); echo $p->id;`);

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
    const pupil = await signIn(STUDENT, 'dv');
    await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="blog-design-link"]')]);
    await pupil.waitForSelector('[data-testid="design-form"]');
    check('My blog leads to the designer, under /dv right to left, in Dhivehi', (await pupil.locator('html').getAttribute('dir')) === 'rtl' && (await pupil.locator('[data-testid="design-state"]').getAttribute('data-state')) === 'live' && flat(await pupil.locator('[data-testid="design-state"]').textContent()) === say('blog.page_state_live'));

    await pupil.click('[data-testid="design-theme-night"]');
    await pupil.fill('[data-testid="design-accent"]', '#1E1B4B');
    check('an accent that does not read is flagged at once', (await pupil.locator('[data-testid="design-contrast"]').count()) === 1);
    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="design-save"]')]);
    await pupil.waitForSelector('[data-testid="design-form"] [role="alert"], [data-testid="design-form"] .text-red-700, [data-testid="design-form"] ul');
    // The refusal in Dhivehi, with the ratio the colour reached against the page.
    const reached = tinker(`echo number_format(\\App\\Support\\Theme\\Contrast::ratio('#1E1B4B', '#14122E'), 1);`);
    const refusal = say('blog.error_contrast', 'dv', `['ratio' => '${reached}', 'needed' => '3.0']`);
    check('and refused by the server, in Dhivehi, with the ratio it reached', flat(await pupil.locator('[data-testid="design-form"]').textContent()).includes(refusal), `${refusal} | ${flat(await pupil.locator('[data-testid="design-form"]').textContent()).slice(-200)}`);

    await pupil.fill('[data-testid="design-accent"]', '#F2C778');
    await pupil.fill('[data-testid="design-tagline"]', 'ރަށްރަށުގެ ވާހަކަތައް');
    await pupil.fill('[data-testid="design-about"]', 'އަހަރެން ލިޔަނީ ބަގީޗާތަކާއި ކަނޑުގެ ވާހަކަ.');
    await pupil.check('[data-testid="design-language-dv"]');
    for (let i = 0; i < 4; i++) await pupil.click('[data-testid="design-section-up-about"]');
    await pupil.uncheck('[data-testid="design-section-on-series"]');
    // BL3b: a banner; links wait for a parent's yes.
    const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAEAAAAAQCAIAAAAphe5+AAAAN0lEQVR4nO3PUQkAAAjE0GtiABua3hB+DGGwAG+Z6tcFFzhACxygBQ7QAgdogQO0wAFa4AAtOLbdNhAutsT7YQAAAABJRU5ErkJggg==', 'base64');
    await pupil.setInputFiles('[data-testid="design-banner"]', { name: 'banner.png', mimeType: 'image/png', buffer: PNG });
    check('the banner shows in the preview before it is saved', (await pupil.locator('[data-testid="preview-banner"]').count()) === 1);
    check('links say they need a parent\'s yes, in Dhivehi', flat(await pupil.locator('[data-testid="design-links-not-allowed"]').textContent()) === say('blog.links_no_consent'));
    await pupil.click('[data-testid="preview-rtl"]');
    check('the preview shows the tagline right to left, About me first', (await pupil.locator('[data-testid="design-preview"]').getAttribute('dir')) === 'rtl' && flat(await pupil.locator('[data-testid="preview-tagline"]').textContent()) === 'ރަށްރަށުގެ ވާހަކަތައް' && (await pupil.locator('[data-testid^="preview-section-"]').first().getAttribute('data-testid')) === 'preview-section-about' && (await pupil.locator('[data-testid="preview-section-series"]').count()) === 0);

    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="design-save"]')]);
    await pupil.waitForFunction(() => document.querySelector('[data-testid="design-state"]')?.dataset.state === 'draft');
    await pupil.waitForSelector('[data-testid="design-submit"]');
    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="design-submit"]')]);
    await pupil.waitForFunction(() => document.querySelector('[data-testid="design-state"]')?.dataset.state === 'waiting');
    check('saved and sent, it waits for the office', flat(await pupil.locator('[data-testid="design-state"]').textContent()) === say('blog.page_state_waiting'));

    const reader = await (await browser.newContext()).newPage();
    await reader.goto(`${BASE}/ar/library/authors/${SLUG}`, { waitUntil: 'networkidle' });
    check('readers still see the old page', (await reader.locator('[data-blog-theme]').getAttribute('data-blog-theme')) === 'akuru' && (await reader.locator('[data-testid="blog-tagline"]').count()) === 0);

    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/library/blog-pages`, { waitUntil: 'networkidle' });
    const row = office.locator(`[data-testid="blog-page-${profileId}"]`);
    check('the office sees the set beside the live page', (await row.count()) === 1 && flat(await row.textContent()).includes('Night') && flat(await row.textContent()).includes('ރަށްރަށުގެ ވާހަކަތައް'));
    await office.waitForFunction((id) => document.querySelector(`[data-testid="blog-page-${id}"] [data-testid="change-banner"]`)?.complete, profileId);
    check('and the waiting banner, from the office\'s own route', await row.locator('[data-testid="change-banner"]').evaluate((img) => img.naturalWidth > 0 && img.getAttribute('src').includes('/admin/library/blog-pages/')));
    const csv = await office.request.get(`${BASE}/en/admin/library/blog-pages/export`);
    check('and the list downloads as CSV', csv.status() === 200 && (await csv.text()).startsWith('id,pen_name'));
    await Promise.all([office.waitForLoadState('networkidle'), office.click(`[data-testid="blog-page-approve-${profileId}"]`)]);
    await office.waitForFunction((id) => !document.querySelector(`[data-testid="blog-page-${id}"]`), profileId);
    check('approving takes it off the list', (await row.count()) === 0);

    await reader.goto(`${BASE}/ar/library/authors/${SLUG}`, { waitUntil: 'networkidle' });
    const sections = await reader.locator('[data-testid^="blog-section-"]').evaluateAll((els) => els.map((el) => el.dataset.testid));
    check('under /ar the page is Night, right to left, with the tagline', (await reader.locator('html').getAttribute('dir')) === 'rtl' && (await reader.locator('[data-blog-theme]').getAttribute('data-blog-theme')) === 'night' && flat(await reader.locator('[data-testid="blog-tagline"]').textContent()) === 'ރަށްރަށުގެ ވާހަކަތައް');
    check('About comes first, and the series is off', sections[0] === 'blog-section-about' && !sections.includes('blog-section-series'), sections.join(','));
    await reader.waitForFunction(() => document.querySelector('[data-testid="blog-banner"]')?.complete, null, { timeout: 10000 }).catch(() => {});
    const banner = await reader.locator('[data-testid="blog-banner"]').evaluate((img) => ({ src: img.getAttribute('src'), width: img.naturalWidth })).catch(() => ({ src: 'none', width: 0 }));
    check('the banner is public now, and shows', banner.width > 0 && !banner.src.includes('/my-blog/'), JSON.stringify(banner));
    check('the accent is the one approved', (await reader.locator('[data-blog-theme]').getAttribute('style')).includes('--blog-accent: #F2C778'));

    await pupil.goto(`${BASE}/dv/my-blog/design`, { waitUntil: 'networkidle' });
    check('the designer says it is live', (await pupil.locator('[data-testid="design-state"]').getAttribute('data-state')) === 'live');
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
