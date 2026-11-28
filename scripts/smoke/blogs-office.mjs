/**
 * Is there a Blogs office, and does each office lead to its own settings? On a phone, in Dhivehi.
 * (Admin audit 2026-11-27, ST5, STATUS §5uy.)
 *
 * At 390 × 844 under /dv the super admin opens the Blogs office: one row
 * of six thumb-sized tabs, the same on its five pages, the open one filled
 * in; the Settings tab opens the blog settings, which lead back to the
 * office. Lending, the wallet and the Bookstore settings each carry the
 * button to their own settings pages. Under /en the same, left to right.
 *
 *   node scripts/smoke/blogs-office.mjs
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

    const pages = [['insights', '/admin/library/blog-insights'], ['requests', '/admin/library/blog-requests'], ['pages', '/admin/library/blog-pages'], ['comments', '/admin/library/comments'], ['tips', '/admin/library/tips']];
    const faults = [];
    for (const [key, href] of pages) {
        const response = await page.goto(`${BASE}/dv${href}`, { waitUntil: 'networkidle' });
        if (response.status() !== 200) faults.push(`${href} answered ${response.status()}`);
        const tabs = page.locator('[data-testid="blog-office-tabs"] a');
        if (await tabs.count() !== 6) faults.push(`${key} has ${await tabs.count()} tabs`);
        const current = await page.locator('[data-testid="blog-office-tabs"] a[aria-current="page"]').evaluateAll((els) => els.map((a) => a.dataset.testid));
        if (current.join() !== `blog-tab-${key}`) faults.push(`${key} marks ${current.join() || 'nothing'} as open`);
        const boxes = await tabs.evaluateAll((els) => els.map((a) => ({ h: a.getBoundingClientRect().height, text: a.innerText.trim() })));
        if (boxes.some((b) => b.h < 40 || !/[\u0780-\u07BF]/.test(b.text))) faults.push(`${key} has a small or untranslated tab`);
        if (await sideways(page) > 1) faults.push(`${key} scrolls sideways`);
    }
    check('/dv the Blogs office is five pages under one row of six thumb-sized tabs, the open one marked, nothing sideways', faults.length === 0, faults.join(' | '));
    if (SHOTS) await page.screenshot({ path: `${SHOTS}/st5-blogs-office-dv.png`, fullPage: true });

    await page.goto(`${BASE}/dv/admin/library/blog-insights`, { waitUntil: 'networkidle' });
    await Promise.all([page.waitForURL(/\/admin\/library\/blog-requests$/), page.locator('[data-testid="blog-tab-requests"]').click()]);
    await Promise.all([page.waitForURL(/\/admin\/settings\/office\/blogs$/), page.locator('[data-testid="blog-tab-settings"]').click()]);
    await page.waitForLoadState('networkidle');
    check('/dv the Settings tab opens the blog settings', await page.locator('[data-testid="knobs-blogs"]').count() === 1);
    await Promise.all([page.waitForURL(/\/admin\/library\/blog-insights$/), page.locator('[data-testid="knobs-office"] a').click()]);
    check('/dv which lead back to the Blogs office', await page.locator('[data-testid="blog-office-tabs"]').count() === 1);

    const offices = [];
    for (const [href, keys] of [['/admin/lending', ['lending_settings']], ['/admin/commerce', ['wallet_settings']], ['/admin/bookshop/settings', ['bookstore_rules', 'bookstore_money', 'print_settings']]]) {
        await page.goto(`${BASE}/dv${href}`, { waitUntil: 'networkidle' });
        const found = await page.locator('[data-testid="office-settings-links"] a').evaluateAll((els) => els.map((a) => a.dataset.testid.replace('office-settings-', '')));
        if (found.join() !== keys.join()) offices.push(`${href} offers ${found.join() || 'nothing'}`);
        const small = await page.locator('[data-testid="office-settings-links"] a').evaluateAll((els) => els.filter((a) => a.getBoundingClientRect().height < 44).length);
        if (small > 0) offices.push(`${href} has a small button`);
        if (await sideways(page) > 1) offices.push(`${href} scrolls sideways`);
    }
    check('/dv Lending, the wallet and the Bookstore settings each carry the button to their own settings pages', offices.length === 0, offices.join(' | '));
    await page.goto(`${BASE}/dv/admin/lending`, { waitUntil: 'networkidle' });
    await Promise.all([page.waitForURL(/\/admin\/settings\/office\/lending$/), page.locator('[data-testid="office-settings-lending_settings"]').click()]);
    await Promise.all([page.waitForURL(/\/admin\/lending$/), page.locator('[data-testid="knobs-office"] a').click()]);
    check('/dv the Lending button opens its settings, which lead back to the office', true);

    await page.goto(`${BASE}/en/admin/library/blog-insights`, { waitUntil: 'networkidle' });
    const en = await page.locator('[data-testid="blog-office-tabs"] a').allInnerTexts();
    check('/en the row reads left to right, in English, nothing sideways', await page.evaluate(() => document.documentElement.dir) === 'ltr' && en.join(' | ') === 'At a glance | Requests | Page changes | Comments | Tips | Settings' && await sideways(page) <= 1, en.join(' | '));
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
