/**
 * Does a form answer in the language of the page it was sent from?
 *
 * The screens send their forms to addresses without a language
 * (`/catalog/glossary`), and the localization package leaves POST, PUT and
 * DELETE in the default language — so a save on a Dhivehi page said "Term
 * saved." in English, though the page it came back to was Dhivehi (STATUS
 * §5os; found by the CT5b walk, where an upload said *Mushaf created.*).
 *
 * The dean adds a glossary term on the Dhivehi page, then the Arabic one,
 * then the English one, and deletes each again; every save and every delete
 * must answer in the page's own words, read from the page's phrase book, and
 * come back to the page's language. Nothing is left behind: a term the walk
 * adds it also deletes (a glossary delete is a real delete).
 *
 *   node scripts/smoke/saved-in-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_MARKER, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const DEAN = process.env.SMOKE_MARKER ?? 'headmaster@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});

const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', DEAN);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

// What the flash box says once it says something other than `before`, or
// null. (A load state that was reached once is not waited for again, so a
// save's answer is waited for by its text.)
const told = async (before = null) => {
    await page.waitForFunction((previous) => {
        const box = document.querySelector('[data-testid="flash-success"]');
        return box !== null && box.textContent.trim() !== previous;
    }, before, { timeout: 10000 }).catch(() => {});
    return (await page.getByTestId('flash-success').textContent().catch(() => null))?.trim() ?? null;
};

for (const locale of ['dv', 'ar', 'en']) {
    await page.goto(`${BASE}/${locale}/catalog/glossary`, { waitUntil: 'networkidle' });
    const t = (await props(page)).t ?? {};
    const term = `SMOKE-Term-${locale}-${Date.now()}`;

    await page.getByLabel(t.glossary_term_en ?? 'Term (EN)', { exact: true }).fill(term);
    await page.locator('main form button[type=submit]').first().click();
    const saved = await told();
    check(`${locale}: adding a term says so in the page's language`, Boolean(t.flash_term_saved) && saved === t.flash_term_saved, `said: ${saved ?? 'nothing'}`);
    check(`${locale}: and comes back to the ${locale} page`, new URL(page.url()).pathname.startsWith(`/${locale}/`), page.url().replace(BASE, ''));

    const remove = page.getByRole('button', { name: (t.glossary_delete_aria ?? 'Delete :term').replace(':term', term), exact: true });
    await remove.waitFor({ timeout: 10000 }).catch(() => {});
    if (!(await remove.count())) {
        check(`${locale}: the new term is listed, to be deleted`, false, term);
        continue;
    }
    await remove.click();
    const deleted = await told(saved);
    check(`${locale}: deleting it says so in the page's language`, Boolean(t.flash_term_deleted) && deleted === t.flash_term_deleted, `said: ${deleted ?? 'nothing'}`);
    check(`${locale}: and the term is gone`, (await page.getByText(term, { exact: true }).count()) === 0, term);
}

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(52)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
