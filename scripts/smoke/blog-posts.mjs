/**
 * Can a student write a series of posts, their class teacher check them,
 * and the office publish one — and do readers find it on the blogs? (BLOG_PLAN
 * BL2a–BL2c, STATUS §5sf–§5sh.)
 *
 * The seeded pupil (student@) is given a student blog for the run, and the
 * seeded class teacher (teacher@) their class. The pupil writes a two-part
 * series under /dv in Dhivehi and sends both. The teacher approves part 1
 * and sends part 2 back with a note. The office approves part 1 on the
 * Library office. Part 1 is readable under /ar, right to left; part 2 is
 * not, and the pupil reads the teacher's note on *My blog*. BL2b: part 1 is
 * on the blogs' front, the pupil's author page reads as their blog with the
 * series, the post says *part 1 of 1*, and the site search finds it. BL2c:
 * the pupil edits part 1; readers see the old title until the teacher and
 * the office approve, then the new one at the same address.
 *
 * The walk puts everything back: the posts, the series, the profile, the
 * role and the class teacher. Local server only.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_TEACHER, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const OFFICE = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('blog-posts changes records with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale = 'dv') => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

// The pupil, their class and the class teacher's account.
const setup = JSON.parse(tinker(`$u = DB::table('users')->where('email', '${STUDENT}')->first(); $t = DB::table('users')->where('email', '${TEACHER}')->first(); $s = DB::table('students')->where('user_id', $u->id)->orderBy('id')->first(); $c = DB::table('class_student')->where('student_id', $s->id)->where('status', 'active')->orderByDesc('id')->value('class_id') ?? $s->class_id; echo json_encode(['user' => $u->id, 'teacher' => $t?->id, 'class' => $c, 'class_teacher' => $c ? DB::table('classes')->where('id', $c)->value('class_teacher_id') : null, 'writer' => DB::table('writer_profiles')->where('user_id', $u->id)->exists()]);`));
if (!setup.teacher || !setup.class || setup.writer) {
    console.log(`blog-posts needs the seeded pupil on a class, the seeded teacher, and no blog of the pupil's yet. ${JSON.stringify(setup)} 0/1 steps passed`);
    process.exit(1);
}
const cleanUp = () => {
    tinker(`$p = DB::table('writer_profiles')->where('user_id', ${setup.user})->where('kind', 'student')->value('id'); if ($p) { $ids = DB::table('library_items')->where('writer_id', $p)->pluck('id'); DB::table('library_item_reviews')->whereIn('library_item_id', $ids)->delete(); DB::table('library_item_pages')->whereIn('library_item_id', $ids)->delete(); DB::table('library_items')->whereIn('id', $ids)->delete(); DB::table('library_post_series')->where('writer_id', $p)->delete(); DB::table('writer_profiles')->where('id', $p)->delete(); } \\App\\Domains\\Identity\\Models\\User::find(${setup.user})?->removeRole('writer'); DB::table('classes')->where('id', ${setup.class})->update(['class_teacher_id' => ${setup.class_teacher ?? 'null'}]); echo 'ok';`);
};
tinker(`DB::table('classes')->where('id', ${setup.class})->update(['class_teacher_id' => ${setup.teacher}]); \\App\\Domains\\Library\\Models\\WriterProfile::query()->create(['user_id' => ${setup.user}, 'kind' => 'student', 'display_name' => 'ތަރި', 'blog_title' => 'މާލޭގެ ނޯޓްތައް', 'slug' => 'walk-tari-${setup.user}', 'status' => 'active', 'approved_at' => now()]); \\App\\Domains\\Identity\\Models\\User::find(${setup.user})->assignRole('writer'); echo 'ok';`);

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
const postId = (title) => tinker(`echo DB::table('library_items')->where('title', '${title}')->where('content_type', 'post')->value('id');`);

const PART1 = 'ބަގީޗާ ފެށުން';
const PART2 = 'ފުރަތަމަ މާ';
try {
    // The pupil writes a two-part series, in Dhivehi, and sends both.
    const pupil = await signIn(STUDENT, 'dv');
    const write = async (title, text, series) => {
        await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
        await pupil.fill('[data-testid="post-title"]', title);
        const editor = pupil.locator('[data-testid="post-body"] [contenteditable="true"]');
        await editor.waitFor();
        await editor.click();
        await pupil.keyboard.type(text);
        if (series.startsWith('new:')) await pupil.fill('[data-testid="post-new-series"]', series.slice(4));
        else await pupil.selectOption('[data-testid="post-series"]', { label: series });
        await pupil.selectOption('[data-testid="post-language"]', 'dv');
        await pupil.check('[data-testid="post-own-work"]');
        await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="post-save"]')]);
        const id = postId(title);
        await pupil.waitForSelector(`[data-testid="post-${id}"]`);
        await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click(`[data-testid="post-submit-${id}"]`)]);
        await pupil.waitForFunction((pid) => document.querySelector(`[data-testid="post-${pid}"]`)?.dataset.status === 'submitted', id);
        return id;
    };
    check('My blog opens under /dv right to left, with the posts in Dhivehi', await (async () => {
        await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
        return (await pupil.locator('html').getAttribute('dir')) === 'rtl' && flat(await pupil.locator('[data-testid="blog-posts"] h2').textContent()) === say('blog.posts_heading');
    })());
    const one = await write(PART1, 'މިއަދު އަހަރެމެން ބަގީޗާއެއް ފެށީމު.', 'new:ބަގީޗާގެ ޑައިރީ');
    const two = await write(PART2, 'ފުރަތަމަ މާ ފުރުވީ.', 'ބަގީޗާގެ ޑައިރީ');
    check('both parts wait for the class teacher, in Dhivehi', flat(await pupil.locator(`[data-testid="post-status-${two}"]`).textContent()) === say('blog.post_status_submitted_teacher') && (await pupil.locator(`[data-testid="post-${one}"]`).getAttribute('data-stage')) === 'teacher');
    check('the series numbers them part 1 and part 2', flat(await pupil.locator(`[data-testid="post-${two}"]`).textContent()).includes('ބަގީޗާގެ ޑައިރީ') && tinker(`echo DB::table('library_items')->whereIn('id', [${one}, ${two}])->orderBy('id')->pluck('series_position')->implode(',');`) === '1,2');

    // The teacher approves part 1 and sends part 2 back.
    const teacher = await signIn(TEACHER, 'en');
    await teacher.goto(`${BASE}/en/teach/blog-posts`, { waitUntil: 'networkidle' });
    check('the teacher sees both, by pen name and the pupil\'s name', (await teacher.locator(`[data-testid="teacher-post-${one}"]`).count()) === 1 && flat(await teacher.locator(`[data-testid="teacher-post-${two}"]`).textContent()).includes('ތަރި'));
    await Promise.all([teacher.waitForLoadState('networkidle'), teacher.click(`[data-testid="teacher-post-approve-${one}"]`)]);
    await teacher.waitForFunction((pid) => !document.querySelector(`[data-testid="teacher-post-${pid}"]`), one);
    await teacher.click(`[data-testid="teacher-post-changes-${two}"]`);
    const noNote = say('blog.error_teacher_note', 'en');
    await teacher.waitForFunction(([pid, text]) => document.querySelector(`[data-testid="teacher-post-${pid}"]`)?.textContent?.includes(text), [two, noNote], { timeout: 10000 }).catch(() => {});
    check('sending back without a note is refused, on the post', (await teacher.locator(`[data-testid="teacher-post-${two}"]`).textContent()).includes(noNote), flat(await teacher.locator(`[data-testid="teacher-post-${two}"]`).textContent()).slice(-160));
    await teacher.fill(`[data-testid="teacher-post-note-${two}"]`, 'Say which flower it was.');
    await Promise.all([teacher.waitForLoadState('networkidle'), teacher.click(`[data-testid="teacher-post-changes-${two}"]`)]);
    await teacher.waitForSelector('[data-testid="teacher-posts-empty"]');
    check('the teacher\'s queue is empty after both', (await teacher.locator('[data-testid="teacher-posts-empty"]').count()) === 1);

    // The office publishes part 1.
    const office = await signIn(OFFICE, 'en');
    await office.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    // The submissions queue: the rows with *Approve & publish* (the items list below shows every item, posts too).
    const queued = (title) => office.locator('tr', { hasText: title }).filter({ has: office.locator('[data-testid="approve-publish"]') });
    const row = queued(PART1);
    check('the office has part 1, and not part 2', (await row.count()) === 1 && (await queued(PART2).count()) === 0, `${await row.count()} / ${await queued(PART2).count()}`);
    await Promise.all([office.waitForLoadState('networkidle'), row.locator('[data-testid="approve-publish"]').click()]);
    await office.waitForFunction((title) => ![...document.querySelectorAll('tr')].some((tr) => tr.textContent.includes(title) && tr.querySelector('[data-testid="approve-publish"]')), PART1);
    check('approving publishes it', tinker(`echo DB::table('library_items')->where('id', ${one})->value('status');`) === 'published');

    // Readers: part 1 under /ar, right to left; part 2 not at all.
    const reader = await (await browser.newContext()).newPage();
    const slugs = JSON.parse(tinker(`echo json_encode(DB::table('library_items')->whereIn('id', [${one}, ${two}])->orderBy('id')->pluck('slug'));`));
    const live = await reader.goto(`${BASE}/ar/library/${slugs[0]}`, { waitUntil: 'networkidle' });
    check('part 1 reads under /ar, right to left', live.status() === 200 && (await reader.locator('html').getAttribute('dir')) === 'rtl' && (await reader.content()).includes(PART1), `${live.status()} ${await reader.locator('html').getAttribute('dir')} ${(await reader.content()).includes(PART1)}`);
    const hidden = await reader.goto(`${BASE}/ar/library/${slugs[1]}`, { waitUntil: 'networkidle' });
    check('part 2, sent back, is not public', hidden.status() === 404, String(hidden.status()));
    await reader.goto(`${BASE}/en/library`, { waitUntil: 'networkidle' });
    check('posts stay off the book shelf', !(await reader.content()).includes(PART1));

    // BL2b: the blogs' front, the blog on the author page, and the series on the post.
    await reader.goto(`${BASE}/ar/library/blogs`, { waitUntil: 'networkidle' });
    check('the blogs\' front lists part 1 under /ar, in Arabic, and not part 2', (await reader.locator('h1').textContent())?.trim() === say('public.blogs_title', 'ar') && (await reader.locator(`[data-post="${slugs[0]}"]`).count()) === 1 && (await reader.locator(`[data-post="${slugs[1]}"]`).count()) === 0);
    await reader.goto(`${BASE}/en/library/authors/walk-tari-${setup.user}`, { waitUntil: 'networkidle' });
    check('the pupil\'s page is a blog: its title, the post and its series', flat(await reader.locator('[data-testid="blog-title"]').textContent()) === 'މާލޭގެ ނޯޓްތައް' && (await reader.locator('[data-testid="blog-section-latest"]').textContent()).includes(PART1) && (await reader.locator('[data-testid="author-series"]').textContent()).includes('ބަގީޗާގެ ޑައިރީ'));
    await reader.goto(`${BASE}/en/library/${slugs[0]}`, { waitUntil: 'networkidle' });
    check('the post says its part of the series, counting only what is published', flat(await reader.locator('[data-testid="post-series"] p').textContent()) === say('public.blogs_series_part', 'en').replace(':n', '1').replace(':count', '1').replace(':series', 'ބަގީޗާގެ ޑައިރީ') && (await reader.locator('[data-testid="series-next"]').count()) === 0, flat(await reader.locator('[data-testid="post-series"]').textContent()));
    await reader.goto(`${BASE}/en/search?q=${encodeURIComponent('ބަގީޗާ')}`, { waitUntil: 'networkidle' });
    check('the site search finds the post', (await reader.content()).includes(PART1));

    // The pupil reads the note and may edit part 2.
    await pupil.goto(`${BASE}/dv/my-blog`, { waitUntil: 'networkidle' });
    check('the pupil reads the teacher\'s note on part 2, and may edit it', flat(await pupil.locator(`[data-testid="post-note-${two}"]`).textContent()).includes('Say which flower it was.') && (await pupil.locator(`[data-testid="post-edit-${two}"]`).count()) === 1);
    check('part 1 shows published', flat(await pupil.locator(`[data-testid="post-status-${one}"]`).textContent()) === say('blog.post_status_published'));

    // BL2c: the pupil edits published part 1; readers see the old text until the teacher and the office approve.
    const EDITED = 'ބަގީޗާ ފެށުން — އިތުރު';
    await pupil.click(`[data-testid="post-edit-${one}"]`);
    await pupil.waitForSelector('[data-testid="post-change-help"]');
    await pupil.fill('[data-testid="post-title"]', EDITED);
    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click('[data-testid="post-save"]')]);
    await pupil.waitForSelector(`[data-testid="post-change-${one}"]`);
    await Promise.all([pupil.waitForLoadState('networkidle'), pupil.click(`[data-testid="post-submit-${one}"]`)]);
    await pupil.waitForFunction((pid) => document.querySelector(`[data-testid="post-change-${pid}"]`)?.dataset.stage === 'teacher', one);
    check('an edit to part 1 waits for the class teacher, in Dhivehi', flat(await pupil.locator(`[data-testid="post-change-${one}"]`).textContent()) === say('blog.change_status_teacher'));
    await reader.goto(`${BASE}/en/library/${slugs[0]}`, { waitUntil: 'networkidle' });
    check('readers still see part 1 as it was', flat(await reader.locator('h1').textContent()) === PART1);
    await teacher.goto(`${BASE}/en/teach/blog-posts`, { waitUntil: 'networkidle' });
    check('the teacher sees it marked as an edit, with the new title', (await teacher.locator(`[data-testid="teacher-post-edit-${one}"]`).count()) === 1 && flat(await teacher.locator(`[data-testid="teacher-post-${one}"] h2`).textContent()) === EDITED);
    await Promise.all([teacher.waitForLoadState('networkidle'), teacher.click(`[data-testid="teacher-post-approve-${one}"]`)]);
    await teacher.waitForFunction((pid) => !document.querySelector(`[data-testid="teacher-post-${pid}"]`), one);
    await office.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const editRow = queued(PART1);
    check('the office sees the edit, marked as one', (await editRow.count()) === 1 && (await office.locator(`[data-testid="submission-edit-${one}"]`).count()) === 1);
    await Promise.all([office.waitForLoadState('networkidle'), editRow.locator('[data-testid="approve-publish"]').click()]);
    await office.waitForFunction(() => !document.querySelector('[data-testid^="submission-edit-"]'));
    await reader.goto(`${BASE}/en/library/${slugs[0]}`, { waitUntil: 'networkidle' });
    check('once approved, readers see the new title at the same address', flat(await reader.locator('h1').textContent()) === EDITED);
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
