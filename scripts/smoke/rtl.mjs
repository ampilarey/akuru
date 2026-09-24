/**
 * Do English sentences read right on a Dhivehi or Arabic page, without the
 * page's alignment changing?
 *
 * ~87% of the interface is still English, and in a right-to-left paragraph the
 * bidi algorithm put an English sentence's full stop at its visual front:
 * ".No exams still in marks entry" (STATUS §5bz). The fix (owner decision 9,
 * §5gd) gives each text element the direction of its own first letter and
 * keeps its alignment matched to its container. This walk measures both, in
 * the rendered page rather than in the stylesheet:
 *
 *   1. on /dv and /ar, every English sentence ending in punctuation has that
 *      punctuation drawn *after* its first letter;
 *   2. on /dv and /ar, no text element has turned left-aligned unless it asks
 *      to (`text-end`, `text-right`) — English lines stay on the right;
 *   3. on /en, nothing moved: the same elements are left-aligned as before.
 *
 * Read-only.
 *
 *   node scripts/smoke/rtl.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const PAGES = ['/portal/overview', '/academics/years', '/exams/schedule'];

const browser = await chromium.launch({
    args: ['--no-first-run', '--no-default-browser-check', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
context.setDefaultNavigationTimeout(60000);
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();
page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', STAFF);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
check('the office signs in', !page.url().includes('/login'), page.url());

// Everything is measured in the page: where the first letter and the final
// punctuation of each English sentence are drawn, and each text element's
// computed alignment.
const measure = () => page.evaluate(() => {
    const TEXT = 'main :is(p, li, dt, dd, blockquote, figcaption, label, caption, td, h1, h2, h3, h4, h5, h6, [role="status"], [role="alert"])';
    const sentences = [];
    const flipped = [];
    for (const el of document.querySelectorAll(TEXT)) {
        const text = el.textContent.trim();
        if (text === '' || el.offsetParent === null) continue;

        // Where the text is *drawn*, not what the stylesheet says: a
        // `unicode-bidi: plaintext` line without `match-parent` still reports
        // `text-align: start` while rendering on the left — the first version
        // of this check read the computed value and passed the broken fix.
        const style = getComputedStyle(el);
        const asksLeft = el.matches('.text-end, .text-right, .text-left');
        const centred = style.textAlign === 'center' || style.textAlign === '-webkit-center';
        const range = document.createRange();
        range.selectNodeContents(el);
        const ink = range.getBoundingClientRect();
        const box = el.getBoundingClientRect();
        const padL = parseFloat(style.paddingLeft) + parseFloat(style.borderLeftWidth);
        const padR = parseFloat(style.paddingRight) + parseFloat(style.borderRightWidth);
        const gapLeft = ink.left - (box.left + padL);
        const gapRight = (box.right - padR) - ink.right;
        const hugsLeft = gapLeft < 2 && gapRight > 16;
        if (hugsLeft && !asksLeft && !centred) flipped.push(`${el.tagName.toLowerCase()} "${text.slice(0, 40)}"`);

        // An English sentence: starts with a Latin letter, ends in . ! ? or :,
        // and is one text node so the characters can be measured directly.
        if (!/^[A-Za-z]/.test(text) || !/[.!?:]$/.test(text) || el.childNodes.length !== 1 || el.firstChild.nodeType !== 3) continue;
        const node = el.firstChild;
        const raw = node.textContent;
        const first = raw.search(/[A-Za-z]/);
        const last = raw.search(/[.!?:]\s*$/);
        const rect = (i) => { const r = document.createRange(); r.setStart(node, i); r.setEnd(node, i + 1); return r.getBoundingClientRect(); };
        const a = rect(first);
        const b = rect(last);
        // Same line only: a wrapped sentence can end on the line below.
        if (Math.abs(a.top - b.top) > 4) continue;
        sentences.push({ text: text.slice(0, 50), ok: b.left > a.left });
    }

    return { dir: document.documentElement.getAttribute('dir'), sentences, flipped };
});

for (const locale of ['dv', 'ar']) {
    let sentences = [];
    let flipped = [];
    let dir = '';
    for (const path of PAGES) {
        await page.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);
        const m = await measure();
        dir = m.dir;
        sentences = [...sentences, ...m.sentences.map((s) => ({ ...s, path }))];
        flipped = [...flipped, ...m.flipped.map((f) => `${path} ${f}`)];
    }
    const wrong = sentences.filter((s) => !s.ok);

    check(`/${locale} is still right-to-left`, dir === 'rtl', `dir=${dir}`);
    check(
        `/${locale}: every English sentence ends with its punctuation`,
        sentences.length > 0 && wrong.length === 0,
        wrong.length ? wrong.slice(0, 3).map((s) => `${s.path} "${s.text}"`).join(' · ') : `${sentences.length} sentences measured`,
    );
    check(`/${locale}: no English line turned left-aligned`, flipped.length === 0, flipped.length ? flipped.slice(0, 3).join(' · ') : `${PAGES.length} pages`);
}

// The control: English pages are left-to-right and nothing about them moved.
await page.goto(`${BASE}/en/portal/overview`, { waitUntil: 'networkidle' });
const en = await page.evaluate(() => ({
    dir: document.documentElement.getAttribute('dir'),
    bidi: [...document.querySelectorAll('main td, main p')].map((el) => getComputedStyle(el).unicodeBidi).filter((v) => v === 'plaintext').length,
}));
check('/en is untouched by the rule', en.dir !== 'rtl' && en.bidi === 0, `dir=${en.dir}, plaintext elements=${en.bidi}`);

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
