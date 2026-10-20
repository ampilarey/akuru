/**
 * Can a reader report a Library work that copies theirs, and can the office
 * decide it? On a phone, in Dhivehi. (BACKLOG B11, BP3, STATUS §5ts.)
 *
 * At 390 × 844 under /dv a signed-out reader opens a published work, unfolds
 * *Report a copyright problem* in its footer, sends the form without the
 * good-faith tick (refused, said in the form), then with it (thanked). The
 * office finds the report on its own page, dismisses it with a note, and it
 * shows as decided. Nothing scrolls sideways. The report is removed after.
 * Local server only.
 *
 *   node scripts/smoke/copyright.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const EMAIL = 'smoke-bp3@example.mv';

if (!LOCAL) {
    console.log('copyright removes its report with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const NOTE_ASKED = tinker(`app()->setLocale('dv'); echo __('library_copyright.error_note');`);
// The form is throttled to five a person in ten minutes; a walk run twice would hit it.
const restore = () => tinker(`DB::table('library_copyright_reports')->where('email', '${EMAIL}')->delete(); Cache::flush(); echo 'ok';`);
restore();
const slug = tinker(`echo DB::table('library_items')->where('status', 'published')->orderBy('id')->value('slug');`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const phone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const watch = (page) => {
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    return page;
};

try {
    check('a published work to report', Boolean(slug), slug);
    const reader = watch(await (await browser.newContext(phone)).newPage());
    await reader.goto(`${BASE}/dv/library/${slug}`, { waitUntil: 'networkidle' });
    const box = reader.locator('[data-testid="copyright-report"]');
    check('/dv the work\'s footer offers to report a copyright problem', await box.count() === 1);
    await box.locator('summary').click();
    await box.locator('input[name="name"]').fill('ހައްވާ ރަޝީދު');
    await box.locator('input[name="email"]').fill(EMAIL);
    await box.locator('input[name="work_claimed"]').fill('ނަޙްވު 2019');
    await box.locator('textarea[name="statement"]').fill('ދެވަނަ ބާބު އަޅުގަނޑުގެ ފޮތުން.');
    check('/dv the open form does not scroll sideways at 390 px', await sideways(reader) <= 1, `${await sideways(reader)} px`);
    await Promise.all([reader.waitForLoadState('networkidle'), box.locator('[data-testid="copyright-send"]').click()]);
    const refused = reader.locator('[data-testid="copyright-errors"]');
    check('without the good-faith tick it is refused, said in the form, the form open again', await refused.count() === 1 && await box.evaluate((d) => d.open));
    await reader.locator('input[name="good_faith"]').check();
    await Promise.all([reader.waitForLoadState('networkidle'), reader.locator('[data-testid="copyright-send"]').click()]);
    check('with it, the reader is thanked', await reader.locator('[data-testid="copyright-sent"]').count() === 1);
    if (SHOTS) { await reader.locator('[data-testid="copyright-report"]').scrollIntoViewIfNeeded(); await reader.screenshot({ path: `${SHOTS}/bp3-sent-dv.png` }); }
    const id = tinker(`echo DB::table('library_copyright_reports')->where('email', '${EMAIL}')->value('id');`);
    check('the report is recorded, open', tinker(`echo DB::table('library_copyright_reports')->where('id', ${Number(id) || 0})->value('status');`) === 'open', id);

    const office = watch(await (await browser.newContext(phone)).newPage());
    await office.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await office.locator('input[name="identifier"]').fill(STAFF);
    await office.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([office.waitForLoadState('networkidle'), office.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    await office.goto(`${BASE}/dv/admin/library`, { waitUntil: 'networkidle' });
    check('/dv the Library office links to the copyright reports', await office.locator('[data-testid="library-copyright-link"]').count() === 1);
    await office.goto(`${BASE}/dv/admin/library/copyright`, { waitUntil: 'networkidle' });
    const row = office.locator(`[data-testid="copyright-report-${id}"]`);
    check('/dv the office finds the report', await row.count() === 1);
    check('/dv the office page does not scroll sideways at 390 px', await sideways(office) <= 1, `${await sideways(office)} px`);
    if (SHOTS) { await row.scrollIntoViewIfNeeded(); await office.screenshot({ path: `${SHOTS}/bp3-office-dv.png` }); }
    await row.locator(`[data-testid="copyright-dismiss-${id}"]`).click();
    await office.waitForTimeout(600);
    check('dismissed without a note: refused on its row', tinker(`echo DB::table('library_copyright_reports')->where('id', ${id})->value('status');`) === 'open' && (await row.textContent()).includes(NOTE_ASKED));
    await row.locator(`[data-testid="copyright-note-${id}"]`).fill('ތަފާތު ފޮތެއް.');
    await row.locator(`[data-testid="copyright-dismiss-${id}"]`).click();
    await office.waitForTimeout(800);
    check('with a note, it is dismissed', tinker(`echo DB::table('library_copyright_reports')->where('id', ${id})->value('status');`) === 'dismissed');
    check('the work stays on the shelf', tinker(`echo DB::table('library_items')->where('slug', '${slug}')->value('status');`) === 'published');
} finally {
    await browser.close();
    restore();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
