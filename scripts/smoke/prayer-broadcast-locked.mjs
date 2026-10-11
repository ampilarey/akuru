/**
 * Is a sent prayer broadcast locked, and said so in the page's language?
 * (BACKLOG C21, slice PB1, STATUS §5rl.)
 *
 * Two broadcasts are planted: one already completed, one a draft. The system
 * admin opens the completed one under /dv: the page says it was queued or
 * sent and cannot be changed, offers no Preview and no Confirm, and its Save
 * is off. The draft under /ar still offers Preview, and previewing it works.
 * The walk never confirms, so nothing is sent. The planted broadcasts are
 * removed first and last. Local server only.
 *
 *   node scripts/smoke/prayer-broadcast-locked.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const MARK = 'SMOKE PB1';

if (!LOCAL) {
    console.log('prayer-broadcast-locked plants broadcasts with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const B = '\\App\\Domains\\PrayerTimes\\Models\\PrayerBroadcast';
const cleanUp = () => tinker(`$ids = ${B}::query()->whereJsonContains('message_template->smoke', '${MARK}')->pluck('id'); DB::table('prayer_broadcast_recipients')->whereIn('prayer_broadcast_id', $ids)->delete(); ${B}::query()->whereIn('id', $ids)->delete(); echo 'ok';`);

cleanUp();
const island = tinker(`echo DB::table('prayer_islands')->where('is_active', true)->orderBy('id')->value('id');`);
const plant = (status) => tinker(`$by = \\App\\Domains\\Identity\\Models\\User::where('email', '${STAFF}')->value('id'); echo ${B}::query()->create(['mode' => 'daily', 'island_id' => ${island}, 'date_from' => now()->toDateString(), 'status' => '${status}', 'created_by' => $by, 'language' => 'en', 'recipient_refs' => [], 'message_template' => ['smoke' => '${MARK}']])->id;`);
const sent = plant('completed');
const draft = plant('draft');

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(STAFF);
await page.locator('input[name="password"]').fill(PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
check('the system admin signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

await page.goto(`${BASE}/dv/admin/prayer-times/broadcasts/${sent}/edit`, { waitUntil: 'networkidle' });
const notice = flat(await page.locator('[data-testid="broadcast-locked"]').textContent().catch(() => ''));
check('/dv a sent broadcast says it is locked, in Dhivehi', notice === flat(say('admin.prayer_error_locked', 'dv')) && /\p{Script=Thaana}/u.test(notice), notice.slice(0, 160));
check('/dv it offers no Preview and no Confirm', await page.locator('[data-testid="broadcast-preview"]').count() === 0 && await page.locator('[data-testid="broadcast-confirm"]').count() === 0);
check('/dv its Save is off', await page.locator('[data-testid="broadcast-save"]').isDisabled().catch(() => false));

await page.goto(`${BASE}/ar/admin/prayer-times/broadcasts/${draft}/edit`, { waitUntil: 'networkidle' });
check('/ar a draft is not locked', await page.locator('[data-testid="broadcast-locked"]').count() === 0);
await page.locator('[data-testid="broadcast-preview"]').click();
const snapshot = await page.locator('[data-testid="broadcast-snapshot"]').waitFor({ timeout: 8000 }).then(() => true).catch(() => false);
check('/ar previewing the draft still works', snapshot && tinker(`echo ${B}::query()->find(${draft})->status->value;`) === 'previewed');
check('the sent broadcast is still completed', tinker(`echo ${B}::query()->find(${sent})->status->value;`) === 'completed');

await browser.close();
cleanUp();

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
