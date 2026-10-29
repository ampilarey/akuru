/**
 * Does System Settings say how the backups stand? On a phone, in Dhivehi.
 * (SPEC "Backups", BK2, STATUS §5ur.)
 *
 * At 390 × 844 under /dv the super admin opens /admin/settings with no
 * backup on record: the card is red and says none was made. With a good
 * one three hours old it reads its time and size, and not red. With the
 * last good one 30 hours old it is red again. A failed run since is shown
 * with why. Nothing scrolls sideways. The rows are removed after. Local
 * server only.
 *
 *   node scripts/smoke/system-backups.mjs
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
if (!LOCAL) {
    console.log('system-backups writes backup rows with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const saved = tinker(`echo json_encode(DB::table('backup_runs')->get());`);
const clear = () => tinker(`DB::table('backup_runs')->delete(); echo 'ok';`);
const row = (status, hoursAgo, extra = '') => tinker(`DB::table('backup_runs')->insert(['name' => 'akuru-walk.akb', 'status' => '${status}', 'started_at' => now()->subHours(${hoursAgo}), 'finished_at' => now()->subHours(${hoursAgo}), 'size_bytes' => 12582912, 'offsite' => false, 'removed' => 0, 'created_at' => now(), 'updated_at' => now() ${extra}]); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
try {
    clear();
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="identifier"]', STAFF);
    await page.fill('input[name="password"]', PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });
    const card = page.locator('[data-testid="backup-status"]');
    const open = async (locale = 'dv') => page.goto(`${BASE}/${locale}/admin/settings`, { waitUntil: 'networkidle' });

    await open();
    check('/dv with no backup on record, the card is red and says none was made', (await card.getAttribute('data-stale')) === 'yes' && (await card.innerText()).includes(tinker(`echo trans('admin.backup_never', [], 'dv');`).slice(0, 20)));
    check('/dv it says no store off the server is set', await page.locator('[data-testid="backup-no-offsite"]').count() === 1);

    row('succeeded', 3);
    await open();
    check('/dv a good one three hours old: its time and size, not red', (await card.getAttribute('data-stale')) === 'no' && (await page.locator('[data-testid="backup-last-good"]').innerText()).includes('12'));
    check('/dv the page does not scroll sideways at 390 px', await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1);
    if (SHOTS) { await card.scrollIntoViewIfNeeded(); await page.screenshot({ path: `${SHOTS}/bk2-backups-dv.png` }); }

    clear();
    row('succeeded', 30);
    row('failed', 2, ", 'error' => 'mysqldump failed: access denied'");
    await open();
    check('/dv the last good one 30 hours old: red again', (await card.getAttribute('data-stale')) === 'yes');
    check('/dv the failed run since is shown, with why', (await page.locator('[data-testid="backup-last-failed"]').innerText()).includes('access denied'));
    await open('en');
    check('/en the card reads left to right, red, nothing sideways', await page.evaluate(() => document.documentElement.dir) === 'ltr' && (await card.getAttribute('data-stale')) === 'yes' && await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1);
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
    clear();
    tinker(`foreach (json_decode('${saved.replace(/'/g, "\\'")}', true) as $r) { DB::table('backup_runs')->insert($r); } echo 'ok';`);
}
for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
