/**
 * Can the Library office name a category in Dhivehi and Arabic, and rename
 * one? (C20, STATUS §5po.)
 *
 * The office (the system admin) adds SMOKE-Category-Seerah with its Dhivehi
 * and Arabic names, finds it in the page's category list, and renames its
 * Dhivehi name in place. A rename with no English name is refused on its own
 * row. Then the shelf's category filter names it in each language: the new
 * Dhivehi name on /dv, the Arabic one on /ar, the English one on /en.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/library-categories.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const NAME = 'SMOKE-Category-Seerah';
const SLUG = 'smoke-category-seerah';
const DV = 'ސީރަތް';
const DV_RENAMED = 'ނަބިއްޔާގެ ސީރަތް';
const AR = 'السيرة';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', STAFF);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

const row = () => page.locator(`[data-testid="category-row-${SLUG}"]`);
async function openList() {
    await page.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const list = page.locator('[data-testid="library-categories"]');
    if ((await list.count()) && !(await list.evaluate((el) => el.open))) await list.locator('summary').click();
}

// 1. Added with its three names. Run again without a reseed, the walk's own
// category is still there, renamed: it is given its first names back.
await openList();
if ((await row().count()) === 1 && (await row().locator('[data-testid="category-dv"]').innerText().catch(() => '')) !== DV) {
    await row().locator('[data-testid="category-rename"]').click();
    await row().locator('[data-testid="category-name"]').fill(NAME);
    await row().locator('[data-testid="category-name-dv"]').fill(DV);
    await row().locator('[data-testid="category-name-ar"]').fill(AR);
    await row().locator('[data-testid="category-save"]').click();
    await page.waitForLoadState('networkidle');
    await openList();
}
const form = page.locator('[data-testid="category-form"]');
if ((await row().count()) === 0 && (await form.locator('[data-testid="new-category-name-dv"]').count()) === 1) {
    await form.locator('[data-testid="new-category-name"]').fill(NAME);
    await form.locator('[data-testid="new-category-name-dv"]').fill(DV);
    await form.locator('[data-testid="new-category-name-ar"]').fill(AR);
    await form.locator('button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    await openList();
}
check('the office adds a category with its Dhivehi and Arabic names, and lists it', (await row().count()) === 1
    && (await row().locator('[data-testid="category-dv"]').innerText().catch(() => '')) === DV
    && (await row().locator('[data-testid="category-ar"]').innerText().catch(() => '')) === AR, (await row().innerText().catch(() => 'no row')).replace(/\s+/g, ' '));

// 2. A rename with no English name is refused on its own row.
if (await row().count()) {
    await row().locator('[data-testid="category-rename"]').click();
    await row().locator('[data-testid="category-name"]').fill('');
    await row().locator('[data-testid="category-save"]').click();
    const refusal = (await row().locator('[role="alert"]').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('a rename with no English name is refused on its own row', Boolean(refusal), `said: ${refusal ?? 'nothing'}`);

    // 3. Renamed in place: the Dhivehi name changes, the address stays.
    await openList();
    await row().locator('[data-testid="category-rename"]').click();
    await row().locator('[data-testid="category-name-dv"]').fill(DV_RENAMED);
    await row().locator('[data-testid="category-save"]').click();
    await page.waitForLoadState('networkidle');
    await row().locator('[data-testid="category-dv"]').waitFor({ timeout: 10000 }).catch(() => {});
    check('the Dhivehi name is renamed in place, and the category keeps its address', (await row().count()) === 1
        && (await row().locator('[data-testid="category-dv"]').innerText().catch(() => '')) === DV_RENAMED
        && (await row().locator('[data-testid="category-en"]').innerText().catch(() => '')) === NAME, (await row().innerText().catch(() => 'no row')).replace(/\s+/g, ' '));
} else {
    check('a rename with no English name is refused on its own row', false, 'no category to rename');
    check('the Dhivehi name is renamed in place, and the category keeps its address', false, 'no category to rename');
}

// 4. The shelf's filter says it in each language.
async function filterNames(locale) {
    await page.goto(`${BASE}/${locale}/library`, { waitUntil: 'networkidle' });
    return page.locator('select[name="category"] option').allInnerTexts().catch(() => []);
}
const dvNames = await filterNames('dv');
// An option reads "name (count)".
const named = (names, name) => names.some((option) => option.trim().startsWith(`${name} (`));
check('the Dhivehi shelf names it by its new Dhivehi name', named(dvNames, DV_RENAMED) && !named(dvNames, NAME), dvNames.join(' | ').slice(0, 200));
const arNames = await filterNames('ar');
check('the Arabic shelf names it in Arabic', named(arNames, AR) && !named(arNames, NAME), arNames.join(' | ').slice(0, 200));
const enNames = await filterNames('en');
check('the English shelf names it in English', named(enNames, NAME), enNames.join(' | ').slice(0, 200));

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(76)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
