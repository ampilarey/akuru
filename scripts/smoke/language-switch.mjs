/**
 * Can the office switch Arabic off and on again from System Settings, and
 * does a language that is off leave the menus and send its addresses to
 * English? (STATUS §5su.)
 *
 * The office opens /en/admin/settings, sees Dhivehi and Arabic both on,
 * unticks Arabic and saves. Then /ar/library opens /en/library, the public
 * Translate menu offers Dhivehi and not Arabic, the app's language menu has
 * no Arabic link, and /dv/library stays in Dhivehi. Arabic is ticked again
 * and /ar/library is Arabic once more.
 *
 * Both switches are put back on at the end, whatever happened. Local server
 * only.
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
    console.log('language-switch changes a site-wide setting: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key) => tinker(`echo __('${key}', [], 'en');`);
const allOn = () => tinker(`app(\\App\\Domains\\Settings\\Actions\\SetEnabledLocalesAction::class)->execute(['dv' => true, 'ar' => true]); echo 'ok';`);
const path = (page) => new URL(page.url()).pathname;

allOn();
const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

try {
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(OFFICE);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);

    await page.goto(`${BASE}/en/admin/settings`, { waitUntil: 'networkidle' });
    check('System Settings shows Languages with Dhivehi and Arabic both on',
        (await page.locator('[data-testid="languages"]').count()) === 1
            && await page.locator('[data-testid="language-dv"]').isChecked() && await page.locator('[data-testid="language-ar"]').isChecked());

    await page.locator('[data-testid="language-ar"]').uncheck();
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/admin/settings/languages')), page.click('[data-testid="languages-save"]')]);
    await page.waitForSelector('[data-testid="flash-success"]');
    check('unticking Arabic and saving says so', (await page.locator('[data-testid="flash-success"]').textContent()).trim() === say('admin.system_settings_languages_saved'));
    check('the app\'s language menu no longer links to Arabic', (await page.locator('a[href*="/ar/admin/settings"]').count()) === 0
        && (await page.locator('a[href*="/dv/admin/settings"]').count()) > 0);

    const visitor = await (await browser.newContext()).newPage();
    await visitor.goto(`${BASE}/ar/library?q=walk`, { waitUntil: 'networkidle' });
    check('/ar/library opens the same page in English, the search kept', path(visitor) === '/en/library' && new URL(visitor.url()).searchParams.get('q') === 'walk', visitor.url());
    check('the public Translate menu offers Dhivehi, not Arabic', (await visitor.locator('.gt-pin[data-code="ar"]').count()) === 0 && (await visitor.locator('.gt-pin[data-code="dv"]').count()) === 1);
    await visitor.goto(`${BASE}/dv/library`, { waitUntil: 'networkidle' });
    check('/dv/library stays in Dhivehi', path(visitor) === '/dv/library' && (await visitor.locator('html').getAttribute('lang')) === 'dv');

    await page.goto(`${BASE}/en/admin/settings`, { waitUntil: 'networkidle' });
    await page.locator('[data-testid="language-ar"]').check();
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/admin/settings/languages')), page.click('[data-testid="languages-save"]')]);
    await page.waitForSelector('[data-testid="flash-success"]');
    await visitor.goto(`${BASE}/ar/library`, { waitUntil: 'networkidle' });
    check('ticked again, /ar/library is Arabic once more', path(visitor) === '/ar/library' && (await visitor.locator('html').getAttribute('dir')) === 'rtl');
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
    allOn();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
