/**
 * Can the office put a course on the website from the CMS alone?
 *
 * BACKLOG C16 slice N2 (STATUS §5nx), from the owner's walk of the live
 * form: "Slug * what is this?", "Cover Image URL *", "there is no cat",
 * "in the website it doesnt show any course".
 *
 *   1. the system admin opens Course categories and adds one;
 *   2. opens the new-course form: the category is offered, typing the title
 *      fills the web address, the cover is a file picker; saves with a cover;
 *   3. the list shows the course as a Draft with a Publish button; Publish
 *      puts it on the website and the list says so;
 *   4. the public courses page lists it with its cover;
 *   5. cleanup: the course is deleted, the category removed.
 *
 *   node scripts/smoke/cms-courses.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const STAMP = Date.now().toString(36).slice(-5);
const CATEGORY = `SMOKE Walk ${STAMP}`;
const TITLE = `SMOKE Course ${STAMP}`;
const SLUG = `smoke-course-${STAMP}`;

// A 1×1 PNG: enough for the picker and the public disk.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--no-first-run', '--no-default-browser-check'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}
for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => { check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200)); await finish(); });
}

const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
context.setDefaultNavigationTimeout(60000);
const page = await context.newPage();
page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
page.on('response', (response) => { if (response.status() >= 500) problems.push(`HTTP ${response.status()} ${response.url()}`); });
page.on('dialog', (dialog) => dialog.accept());
const text = async () => (await page.innerText('main').catch(() => page.innerText('body'))).replace(/\s+/g, ' ');

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
check('the system admin signs in', !page.url().includes('/login'), page.url());

// ------------------------------------------------------------ 1. a category
await page.goto(`${BASE}/en/admin/public-site/courses`, { waitUntil: 'networkidle' });
await Promise.all([page.waitForURL(/courses\/categories$/), page.click('[data-testid="courses-categories"]')]);
await page.waitForSelector('[data-testid="course-category-add"]');
check('the courses list links to Course categories', /courses\/categories$/.test(page.url()), page.url().replace(BASE, ''));
await page.fill('[data-testid="course-category-name"]', CATEGORY);
await page.click('[data-testid="course-category-submit"]');
const categoryRow = page.locator('[data-testid="course-category-row"]', { hasText: CATEGORY });
await categoryRow.waitFor({ timeout: 15000 }).catch(() => {});
check('a category is added and listed', (await categoryRow.count()) === 1 && (await text()).includes('Category saved.'), (await text()).slice(0, 160));

// ------------------------------------------------------------ 2. the form
await page.goto(`${BASE}/en/admin/public-site/courses/create`, { waitUntil: 'networkidle' });
const offered = await page.locator('#course-course_category_id option', { hasText: CATEGORY }).count();
check('the new-course form offers the category', offered === 1);
await page.selectOption('#course-course_category_id', { label: CATEGORY });
await page.fill('#course-title', TITLE);
const slug = await page.inputValue('[data-testid="course-slug"]');
check('typing the title fills the web address', slug === SLUG, slug);
check('the address field is not required and says what it is', (await page.getAttribute('[data-testid="course-slug"]', 'required')) === null && (await text()).includes(`akuru.edu.mv/courses/${SLUG}`));
check('the cover is a file picker, not a URL box', (await page.getAttribute('[data-testid="course-cover"]', 'type')) === 'file' && (await page.locator('input[name="cover_image"]').count()) === 0);
await page.fill('#course-short_desc', 'A walk-planted course.');
await page.fill('#course-body', '<p>Hello from the walk.</p>');
await page.setInputFiles('[data-testid="course-cover"]', { name: 'cover.png', mimeType: 'image/png', buffer: PNG });
await Promise.all([page.waitForURL(/admin\/public-site\/courses$/, { timeout: 30000 }), page.click('[data-testid="course-save"]')]);
await page.waitForLoadState('networkidle');
const row = page.locator('[data-testid="course-row"]', { hasText: TITLE });
check('the course is saved and listed', (await row.count()) === 1 && (await text()).includes('Course created successfully.'));

// ------------------------------------------------------------ 3. publish
check('it is a Draft with a Publish button, and the list says a draft is not on the website', (await row.locator('[data-testid="course-workflow"]').innerText()) === 'Draft' && (await row.locator('[data-testid="course-publish"]').count()) === 1 && (await text()).includes('A new course starts as a draft'));
await row.locator('[data-testid="course-publish"]').click();
const after = page.locator('[data-testid="course-row"]', { hasText: TITLE });
await after.locator('[data-testid="course-on-website"]').waitFor({ timeout: 20000 }).catch(() => {});
check('Publish puts it on the website', (await after.locator('[data-testid="course-workflow"]').innerText()) === 'Published' && (await after.locator('[data-testid="course-on-website"]').count()) === 1 && (await after.locator('[data-testid="course-publish"]').count()) === 0, (await text()).slice(0, 160));

// The edit form shows the current cover.
await after.locator('[data-testid="course-edit"]').click();
await page.waitForSelector('[data-testid="course-form"]');
check('the edit form shows the current cover', (await page.locator('[data-testid="course-cover-current"]').count()) === 1 && (await page.inputValue('[data-testid="course-slug"]')) === SLUG);

// ------------------------------------------------------------ 4. the website
const guest = await (await browser.newContext()).newPage();
await guest.goto(`${BASE}/en/courses`, { waitUntil: 'networkidle' });
const publicBody = (await guest.innerText('body')).replace(/\s+/g, ' ');
const cover = await guest.locator(`img[src*="course-covers/"]`).count();
check('the public courses page lists it, with its cover', publicBody.includes(TITLE) && cover >= 1, `cover images: ${cover}`);
await guest.goto(`${BASE}/en/courses/${SLUG}`, { waitUntil: 'networkidle' });
check('and its own page opens at the address the form showed', (await guest.innerText('body')).includes(TITLE), guest.url().replace(BASE, ''));

// ------------------------------------------------------------ 5. delete, the SPEC §29 way
// A published course has an offering, so Delete keeps it (soft) rather than
// removing it — this used to answer 500 on the offerings' foreign key. The
// category it uses cannot be deleted while it does. SmokeMarkerSeeder clears
// both on its next run.
await page.goto(`${BASE}/en/admin/public-site/courses`, { waitUntil: 'networkidle' });
await page.locator('[data-testid="course-row"]', { hasText: TITLE }).locator('[data-testid="course-delete"]').click();
await page.waitForSelector('[data-testid="flash-success"]', { timeout: 15000 }).catch(() => {});
const afterDelete = await text();
check('Delete keeps a published course and says what it holds', !afterDelete.includes(TITLE) && /removed from the catalogue.*1 offering/.test(afterDelete), afterDelete.slice(0, 200));
await page.goto(`${BASE}/en/admin/public-site/courses/deleted`, { waitUntil: 'networkidle' });
check('and it is in Deleted courses, restorable', (await page.innerText('body')).includes(TITLE));
await page.goto(`${BASE}/en/admin/public-site/courses/categories`, { waitUntil: 'networkidle' });
const catRow = page.locator('[data-testid="course-category-row"]', { hasText: CATEGORY });
check('a category in use offers no Delete', (await catRow.count()) === 1 && (await catRow.locator('[data-testid="course-category-delete"]').count()) === 0);

await finish();
