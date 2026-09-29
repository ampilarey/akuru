/**
 * Can the office write the news, and does it reach the public site?
 * (RESEARCH_ARTICLES_PLAN R4, STATUS §5ks.)
 *
 *   1. the office opens Website CMS → News and writes a news item with a
 *      cover, a summary and a body, and publishes it;
 *   2. it shows on /news, on its own page (with its cover), and on the home
 *      page's latest-news row;
 *   3. a draft written next to it shows on none of them;
 *   4. the office's list says which is which.
 *
 *   node scripts/smoke/news.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const STAMP = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`.toUpperCase();
const TITLE = `SMOKE-News ${STAMP}`;
const DRAFT = `SMOKE-News-Draft ${STAMP}`;
const BODY = `SMOKE-News-Body ${STAMP}: the open day is on Saturday.`;

// A small PNG cover the browser can upload.
const COVER = join(mkdtempSync(join(tmpdir(), 'smoke-news-')), 'cover.png');
writeFileSync(COVER, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFklEQVR42mP8z8DwnwEIGBkZGRgYGAAhgQL/kk7qZQAAAABJRU5ErkJggg==', 'base64'));

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--disable-component-update', '--no-first-run', '--no-default-browser-check'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

async function page() {
    const context = await browser.newContext();
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const p = await context.newPage();
    p.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    return p;
}

async function write(office, title, publish) {
    await office.goto(`${BASE}/en/admin/public-site/news/create`, { waitUntil: 'networkidle' });
    await office.fill('#news-title', title);
    await office.fill('#news-summary', `Summary of ${title}`);
    await office.locator('[data-testid="news-body"] .ProseMirror').fill(publish ? BODY : `${BODY} (draft)`);
    await office.locator('[data-testid="news-cover"]').setInputFiles(COVER);
    if (publish) await office.check('[data-testid="news-is_published"]');
    await Promise.all([office.waitForURL(/\/admin\/public-site\/news\/\d+$/), office.click('[data-testid="news-save"]')]);
}

// ------------------------------------------------ 1. the office writes the news
const office = await page();
await office.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await office.fill('input[name="identifier"]', STAFF);
await office.fill('input[name="password"]', PASSWORD);
await Promise.all([office.waitForLoadState('networkidle'), office.click('button[type="submit"]')]);

await write(office, TITLE, true);
const previewed = (await office.locator('[data-testid="news-preview-body"]').innerText()).includes(BODY);
const coverShown = (await office.locator('[data-testid="news-preview-cover"]').count()) === 1;
check('the office writes a news item with a cover and publishes it', previewed && coverShown, `preview: ${previewed}, cover: ${coverShown}`);
await write(office, DRAFT, false);
check('and saves a second one as a draft', (await office.locator('[data-testid="news-preview-state"]').innerText()).includes('Draft'));

await office.goto(`${BASE}/en/admin/public-site/news`, { waitUntil: 'networkidle' });
const states = await office.locator('[data-testid="news-row"]').evaluateAll((rows, [t, d]) => rows
    .filter((row) => row.innerText.includes(t) || row.innerText.includes(d))
    .map((row) => `${row.innerText.includes(d) ? 'draft' : 'news'}:${row.querySelector('[data-testid="news-state"]').innerText.trim()}`), [TITLE, DRAFT]);
check('the office\'s list says which is published and which is a draft', states.includes('news:Published') && states.includes('draft:Draft'), states.join(' · '));

// ------------------------------------------------ 2. a visitor reads it
const visitor = await page();
await visitor.goto(`${BASE}/en/news`, { waitUntil: 'networkidle' });
const list = await visitor.innerText('body');
check('it is on /news, and the draft is not', list.includes(TITLE) && !list.includes(DRAFT));
await Promise.all([visitor.waitForURL(/\/news\/smoke-news-/), visitor.locator('a', { hasText: TITLE }).first().click()]);
await visitor.waitForLoadState('networkidle');
const article = await visitor.innerText('body');
const cover = visitor.locator('article img').first();
const drawn = (await cover.count()) === 1 && await cover.evaluate((el) => el.complete && el.naturalWidth > 0);
check('its own page shows the body and the cover', article.includes(BODY) && drawn, `body: ${article.includes(BODY)}, cover drawn: ${drawn}`);
await visitor.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
check('and the home page lists it among the latest news', (await visitor.innerText('body')).includes(TITLE));

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
const failed = results.filter(([, ok]) => !ok).length + problems.length;
console.log(`\n${results.length - results.filter(([, ok]) => !ok).length}/${results.length} steps passed.`);
await browser.close();
process.exit(failed === 0 ? 0 : 1);
