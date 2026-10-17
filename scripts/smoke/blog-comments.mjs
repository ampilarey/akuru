/**
 * Are blog comments checked before they show, can the writer reply, does a
 * report take one down, and is an under-13's blog closed to comments?
 * (BLOG_PLAN BL5, STATUS §5sl.)
 *
 * Two blogs are made for the run: a 15-year-old's and an 11-year-old's, each
 * with a published post. Under /dv a reader (teacher@) comments on the first;
 * it does not show. The office publishes it on /admin/library/comments; it
 * shows with the reader's first name and initial. The writer replies; the
 * reply shows once the office publishes it. Another reader (student@) reports
 * the comment; it leaves the page and waits for the office as reported. The
 * 11-year-old's post has no comment box.
 *
 * Everything the walk made is removed. Local server only.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_TEACHER, SMOKE_STUDENT, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const READER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const REPORTER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('blog-comments changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const blog = (tag, born) => JSON.parse(tinker(`$s = \\App\\Domains\\People\\Models\\Student::query()->create(['user_id' => ($u = \\App\\Domains\\Identity\\Models\\User::factory()->create(['email' => 'walk-${tag}@example.test', 'password' => bcrypt('${PASSWORD}')]))->id, 'first_name' => 'Walk', 'last_name' => '${tag}', 'date_of_birth' => '${born}', 'gender' => 'female']); $p = \\App\\Domains\\Library\\Models\\WriterProfile::query()->create(['user_id' => $u->id, 'kind' => 'student', 'display_name' => 'ތަރި ${tag}', 'slug' => 'walk-comments-${tag}', 'status' => 'active', 'approved_at' => now()]); $i = \\App\\Domains\\Library\\Models\\LibraryItem::query()->create(['title' => 'Walk ${tag} post', 'slug' => 'walk-comments-${tag}-post', 'content_type' => 'post', 'access_type' => 'free_public', 'language' => 'en', 'status' => 'published', 'published_at' => now(), 'readers_notified_at' => now(), 'writer_id' => $p->id, 'body' => '<p>Beans.</p>']); echo json_encode(['user' => $u->id, 'student' => $s->id, 'profile' => $p->id, 'item' => $i->id, 'email' => $u->email]);`));
const teen = blog('teen', '2011-03-01');
const young = blog('young', '2015-03-01');
const cleanUp = () => {
    for (const b of [teen, young]) {
        tinker(`DB::table('library_comments')->where('library_item_id', ${b.item})->delete(); DB::table('library_item_pages')->where('library_item_id', ${b.item})->delete(); DB::table('library_items')->where('id', ${b.item})->delete(); DB::table('writer_profiles')->where('id', ${b.profile})->delete(); DB::table('students')->where('id', ${b.student})->delete(); DB::table('users')->where('id', ${b.user})->delete(); echo 'ok';`);
    }
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
const POST = `${BASE}/dv/library/walk-comments-teen-post`;
const commentId = (text) => tinker(`echo DB::table('library_comments')->where('body', '${text}')->value('id');`);

try {
    const reader = await signIn(READER, 'dv');
    await reader.goto(POST, { waitUntil: 'networkidle' });
    check('the post under /dv offers a comment box, in Dhivehi', (await reader.locator('html').getAttribute('dir')) === 'rtl' && flat(await reader.locator('[data-testid="comment-send"]').textContent()) === say('blog.comment_send'));
    await reader.fill('[data-testid="comment-body"]', 'Walk: which beans?');
    await Promise.all([reader.waitForLoadState('networkidle'), reader.click('[data-testid="comment-send"]')]);
    const afterSend = await reader.locator('[data-testid="post-comments"]').textContent();
    check('a sent comment does not show yet, and the reader is told it is checked first', !afterSend.includes('Walk: which beans?') && flat(await reader.locator('body').textContent()).includes(say('blog.flash_comment_sent')), `${afterSend.includes('Walk: which beans?')} | ${flat(await reader.locator('body').textContent()).slice(0, 300)}`);

    const office = await signIn(OFFICE, 'en');
    const id = commentId('Walk: which beans?');
    const publish = async (cid) => {
        await office.goto(`${BASE}/en/admin/library/comments`, { waitUntil: 'networkidle' });
        await Promise.all([office.waitForLoadState('networkidle'), office.click(`[data-testid="blog-comment-publish-${cid}"]`)]);
        await office.waitForFunction((c) => !document.querySelector(`[data-testid="blog-comment-${c}"]`), cid);
    };
    await publish(id);
    await reader.goto(POST, { waitUntil: 'networkidle' });
    const readerName = tinker(`echo DB::table('users')->where('email', '${READER}')->value('name');`).trim().split(/\s+/);
    const shown = readerName.length > 1 ? `${readerName[0]} ${readerName.at(-1)[0]}.` : readerName[0];
    check('published, it shows, with the first name and initial only', flat(await reader.locator(`[data-comment="${id}"]`).textContent()).includes('Walk: which beans?') && flat(await reader.locator(`[data-comment="${id}"]`).textContent()).includes(shown), shown);

    // The writer replies; the reply waits for the office too.
    const writer = await signIn(teen.email, 'en');
    await writer.goto(`${BASE}/en/library/walk-comments-teen-post`, { waitUntil: 'networkidle' });
    await writer.fill(`[data-testid="comment-reply-${id}"]`, 'Walk: broad beans!');
    await Promise.all([writer.waitForLoadState('networkidle'), writer.locator(`[data-testid="comment-reply-${id}"] ~ button`).click()]);
    await reader.goto(POST, { waitUntil: 'networkidle' });
    check('the writer\'s reply waits for the office', !(await reader.content()).includes('Walk: broad beans!'));
    await publish(commentId('Walk: broad beans!'));
    await reader.goto(POST, { waitUntil: 'networkidle' });
    check('then shows under the comment, as the blog\'s reply', flat(await reader.locator(`[data-comment="${id}"]`).textContent()).includes('Walk: broad beans!') && flat(await reader.locator(`[data-comment="${id}"]`).textContent()).includes(say('blog.comment_owner_reply')));

    // Another reader reports it.
    const reporter = await signIn(REPORTER, 'en');
    await reporter.goto(`${BASE}/en/library/walk-comments-teen-post`, { waitUntil: 'networkidle' });
    await Promise.all([reporter.waitForLoadState('networkidle'), reporter.click(`[data-testid="comment-report-${id}"]`)]);
    await reader.goto(POST, { waitUntil: 'networkidle' });
    check('reported, it leaves the page', !(await reader.content()).includes('Walk: which beans?'));
    await office.goto(`${BASE}/en/admin/library/comments`, { waitUntil: 'networkidle' });
    check('and waits for the office as reported', flat(await office.locator(`[data-testid="blog-comment-status-${id}"]`).textContent()) === 'Reported');

    // An 11-year-old's post takes no comments.
    await reader.goto(`${BASE}/dv/library/walk-comments-young-post`, { waitUntil: 'networkidle' });
    check('an under-13\'s post has no comment box, and says why', (await reader.locator('[data-testid="comment-form"]').count()) === 0 && flat(await reader.locator('[data-testid="comments-closed"]').textContent()) === say('blog.comments_closed_young'));
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
