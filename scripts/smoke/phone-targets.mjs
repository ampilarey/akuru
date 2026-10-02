/**
 * Under a thumb (docs/ADMIN_PANEL.md §7 M3, M4, M5; STATUS §5nq). The audit
 * measured the admin panel at 390px: 889 of 1,246 tap targets under 32px,
 * 204 form controls under 16px (iOS zooms the page when one is tapped), 38
 * fields with a placeholder and no name. This walk loads the screens the
 * audit named and asks of each: is every visible text control 16px; does
 * every row action, chip, back link and button reach 44px; does every
 * control have an accessible name.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/phone-targets.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN (a super admin), SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const PAGES = [
    '/en/admin', '/en/admin/users', '/en/admin/enrollments', '/en/admin/enrollments/payments',
    '/en/admin/public-site/pages', '/en/admin/public-site/courses', '/en/admin/public-site/news/categories',
    '/en/admin/library', '/en/admin/library/promotions', '/en/admin/library/reviewers', '/en/admin/library/insights',
    '/en/admin/commerce', '/en/admin/pronunciation', '/en/admin/prayer-times/islands', '/en/admin/prayer-times/groups/create',
    '/en/admin/operations', '/en/admin/operations/features', '/en/admin/translations',
    '/en/admin/bookshop', '/en/admin/bookshop/akuru', '/en/admin/lending',
];

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--no-first-run', '--no-default-browser-check'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
let finished = false;
async function finish() {
    if (finished) return;
    finished = true;
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}
for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => { check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200)); await finish(); });
}

const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
context.setDefaultNavigationTimeout(60000);
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

const measure = () => page.evaluate(() => {
    const vis = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
    const name = (el) => `${el.tagName.toLowerCase()}${el.getAttribute('data-testid') ? '[' + el.getAttribute('data-testid') + ']' : ''}"${(el.getAttribute('aria-label') || el.textContent || el.getAttribute('placeholder') || '').replace(/\s+/g, ' ').trim().slice(0, 28)}"`;
    const controls = [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=file]):not([type=range]), select, textarea')].filter(vis);
    const small = controls.filter((el) => parseFloat(getComputedStyle(el).fontSize) < 16).map(name);
    const named = (el) => Boolean((el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) || el.closest('label') || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby'));
    const unnamed = controls.filter((el) => !named(el)).map(name);
    const targets = [...document.querySelectorAll('td > a, td > button, .btn-primary, .btn-secondary, .chip-link, [data-testid$="-back"], [data-testid="back-link"], [data-testid$="-link"]')].filter(vis);
    const short = targets.filter((el) => el.getBoundingClientRect().height < 43.5).map((el) => `${name(el)}=${Math.round(el.getBoundingClientRect().height)}px`);
    const ticks = [...document.querySelectorAll('input[type=checkbox], input[type=radio]')].filter(vis);
    const tinyTicks = ticks.filter((el) => el.getBoundingClientRect().width < 23.5).map(name);

    return { controls: controls.length, small, unnamed, targets: targets.length, short, ticks: ticks.length, tinyTicks };
});

const totals = { controls: 0, targets: 0, ticks: 0 };
const smallAll = []; const unnamedAll = []; const shortAll = []; const tinyAll = [];
for (const path of PAGES) {
    const response = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) { check(path, false, `HTTP ${response?.status()}`); continue; }
    const m = await measure();
    totals.controls += m.controls; totals.targets += m.targets; totals.ticks += m.ticks;
    const where = path.replace('/en/admin', '') || '/';
    smallAll.push(...m.small.map((s) => `${where} ${s}`));
    unnamedAll.push(...m.unnamed.map((s) => `${where} ${s}`));
    shortAll.push(...m.short.map((s) => `${where} ${s}`));
    tinyAll.push(...m.tinyTicks.map((s) => `${where} ${s}`));
}
check(`${PAGES.length} admin screens load at 390px`, results.length === 0);
check(`every visible text control is 16px (${totals.controls} controls)`, smallAll.length === 0, smallAll.slice(0, 6).join(' | '));
check(`every visible text control has a name (${totals.controls} controls)`, unnamedAll.length === 0, unnamedAll.slice(0, 6).join(' | '));
check(`every row action, chip, back link and button reaches 44px (${totals.targets} targets)`, shortAll.length === 0, shortAll.slice(0, 6).join(' | '));
check(`every checkbox and radio is 24px (${totals.ticks})`, tinyAll.length === 0, tinyAll.slice(0, 6).join(' | '));

await finish();
