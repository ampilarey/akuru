/**
 * Are a product's business settings changed on a screen? On a phone, in Dhivehi.
 * (Admin audit 2026-11-27, ST4a, STATUS §5uw.)
 *
 * At 390 × 844 under /dv the super admin opens Settings, follows the
 * Lending card to its own page: two groups, each with its own Save, every
 * field with what it does and the server's own value. A wrong value is
 * refused beside its field; a right one is saved, is there after a reload,
 * and is put back. The wallet's page holds the wallet's one setting. The
 * Bookstore's rules, its money and Blogs each have a page (ST4b), and an
 * unsafe address is refused. Under /en the same, left to right.
 *
 *   node scripts/smoke/office-settings.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const sideways = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
try {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="identifier"]', STAFF);
    await page.fill('input[name="password"]', PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').first().click();
    await page.waitForURL((u) => !String(u).includes('/login'), { timeout: 20000 });

    await page.goto(`${BASE}/dv/admin/settings`, { waitUntil: 'networkidle' });
    const card = page.locator('[data-testid="settings-card-lending_settings"]');
    check('/dv the Settings index has a Lending card and a Wallet card', await card.count() === 1 && await page.locator('[data-testid="settings-card-wallet_settings"]').count() === 1);
    await Promise.all([page.waitForURL(/\/admin\/settings\/office\/lending$/), card.click()]);
    await page.waitForLoadState('networkidle');
    const main = page.locator('[data-phone-main][data-testid="knobs-lending"]');
    check('/dv Lending has its own page: two groups, each with its own Save', await main.locator('form').count() === 2 && await page.locator('[data-testid="knobs-save-loans"]').count() === 1 && await page.locator('[data-testid="knobs-save-notices"]').count() === 1);
    const knobs = await page.locator('[data-testid^="knob-"]').evaluateAll((els) => els.map((el) => ({ name: el.dataset.testid, lines: el.querySelectorAll('p').length, text: el.innerText })));
    check('/dv every field says what it does and the server\'s own value, in Dhivehi', knobs.length === 7 && knobs.every((k) => k.lines >= 2 && /[\u0780-\u07BF]/.test(k.text) && !/knobs\./.test(k.text)), JSON.stringify(knobs.map((k) => [k.name, k.lines])));
    check('/dv it reads right to left, starts on the first screen and does not scroll sideways', await page.evaluate(() => document.documentElement.dir) === 'rtl' && (await main.boundingBox()).y < 844 && await sideways(page) <= 1, `${await sideways(page)} px`);
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/st4a-lending-settings-dv.png`, fullPage: true });

    const books = page.locator('[data-testid="knob-max_books"] input');
    const before = await books.inputValue();
    const usual = page.locator('[data-testid="knob-default_days"] input');
    const usualBefore = await usual.inputValue();
    // A usual loan longer than the longest is refused beside its field.
    await usual.evaluate((el) => { el.removeAttribute('max'); });
    await usual.fill('365');
    await page.locator('[data-testid="knob-max_days"] input').fill('30');
    await page.locator('[data-testid="knobs-save-loans"]').click();
    await page.locator('[data-testid="knob-default_days"] [role="alert"]').waitFor({ timeout: 15000 }).catch(() => {});
    check('/dv a usual loan longer than the longest is refused beside its field', await page.locator('[data-testid="knob-default_days"] [role="alert"]').count() === 1);
    await page.reload({ waitUntil: 'networkidle' });
    check('/dv a refused save changes nothing', await usual.inputValue() === usualBefore, `${await usual.inputValue()} vs ${usualBefore}`);

    const next = String(Number(before) === 37 ? 38 : 37);
    await books.fill(next);
    await page.locator('[data-testid="knobs-save-loans"]').click();
    await page.locator('[data-testid="flash-success"]').waitFor({ timeout: 15000 }).catch(() => {});
    check('/dv a right value is saved and says so', await page.locator('[data-testid="flash-success"]').count() === 1);
    await page.reload({ waitUntil: 'networkidle' });
    check('/dv it is there after a reload', await books.inputValue() === next, await books.inputValue());
    await books.fill(before);
    await page.locator('[data-testid="knobs-save-loans"]').click();
    await page.locator('[data-testid="flash-success"]').waitFor({ timeout: 15000 }).catch(() => {});
    await page.reload({ waitUntil: 'networkidle' });
    check('/dv and is put back', await books.inputValue() === before, await books.inputValue());

    const crumb = page.locator('[data-testid="settings-crumb"] a');
    await Promise.all([page.waitForURL(/\/admin\/settings$/), crumb.click()]);
    check('/dv the page leads back to the Settings index', await page.locator('[data-testid="settings-institute"]').count() === 1);

    await page.goto(`${BASE}/dv/admin/settings/office/wallet`, { waitUntil: 'networkidle' });
    check('/dv the wallet\'s page holds the wallet\'s one setting, nothing sideways', await page.locator('[data-testid^="knob-"]').count() === 1 && await page.locator('[data-testid="knob-alert_credit_at"] input').count() === 1 && await sideways(page) <= 1);
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/st4a-wallet-settings-dv.png`, fullPage: true });

    // ST4b: the Bookstore's rules, its money, and Blogs.
    const more = [];
    for (const [product, groups, fields] of [['bookstore', 2, 3], ['bookstore_money', 3, 11], ['blogs', 1, 3]]) {
        const response = await page.goto(`${BASE}/dv/admin/settings/office/${product}`, { waitUntil: 'networkidle' });
        const texts = await page.locator('[data-testid^="knob-"]').evaluateAll((els) => els.map((el) => el.innerText));
        if (response.status() !== 200) more.push(`${product} answered ${response.status()}`);
        if (await page.locator(`[data-phone-main][data-testid="knobs-${product}"] form`).count() !== groups) more.push(`${product} has ${await page.locator('main form').count()} groups`);
        if (texts.length !== fields) more.push(`${product} has ${texts.length} fields`);
        if (texts.some((t) => /knobs\./.test(t) || !/[\u0780-\u07BF]/.test(t))) more.push(`${product} has a field not in Dhivehi`);
        if (await page.locator('[data-testid="settings-crumb"] a').count() !== 1) more.push(`${product} has no way back`);
        if (await sideways(page) > 1) more.push(`${product} scrolls sideways`);
        if (SHOTS) await page.screenshot({ path: `${SHOTS}/st4b-${product}-dv.png`, fullPage: true });
    }
    check('/dv Bookstore rules, Bookstore money and Blogs each have their own page: groups, fields in Dhivehi, a way back, nothing sideways', more.length === 0, more.join(' | '));

    await page.goto(`${BASE}/dv/admin/settings/office/blogs`, { waitUntil: 'networkidle' });
    const address = page.locator('[data-testid="knob-guidelines_url"] input');
    const addressBefore = await address.inputValue();
    await address.fill('javascript:alert(1)');
    await page.locator('[data-testid="knobs-save-students"]').click();
    await page.locator('[data-testid="knob-guidelines_url"] [role="alert"]').waitFor({ timeout: 15000 }).catch(() => {});
    await page.reload({ waitUntil: 'networkidle' });
    check('/dv an address that is not a page or https is refused and nothing changes', await address.inputValue() === addressBefore, await address.inputValue());

    await page.goto(`${BASE}/en/admin/settings/office/lending`, { waitUntil: 'networkidle' });
    check('/en the same page, left to right, in English, nothing sideways', await page.evaluate(() => document.documentElement.dir) === 'ltr' && (await page.locator('[data-testid="knob-max_days"]').innerText()).includes('Longest loan') && await sideways(page) <= 1);
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/st4a-lending-settings-en.png`, fullPage: true });
} catch (e) {
    problems.push(String(e).slice(0, 300));
} finally {
    await browser.close();
}
for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
