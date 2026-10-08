/**
 * Do the Library's writer portal and peer review read in Dhivehi and Arabic?
 * (BACKLOG C20, slice LT2, STATUS §5pe.)
 *
 * The writer opens /dv/write and /ar/write with a new draft's editor open;
 * the reviewer opens /dv/review and /ar/review. The walk lists what is still
 * in Latin letters in each page's main — every text node, placeholder,
 * aria-label and title. What the pages show of their data (a draft's title,
 * an editor's comment, a category somebody named) is the author's, so a
 * string found among the page's props passes; codes the server sends to be
 * named (a type, an access, a status, a recommendation) do not, and printed
 * raw they fail. So do the phrase books the page is sent. File types, sizes,
 * addresses and the social networks' names are the same in every language.
 * Each page must also be right to left, and every field in it named.
 *
 * Then the writer, on the Dhivehi page, saves SMOKE-Lang-Research with only
 * the copyright declaration ticked (made once, found after), and presses
 * Submit for review on its row. The submission is refused because research
 * also needs its originality and conflict-of-interest declarations. The
 * refusal must be said on that row, in Dhivehi, naming both; the draft stays
 * a draft. The page's one list at the top used to say it in English.
 *
 *   node scripts/smoke/writer-language.mjs
 *
 * The writer is the smoke seed's approved writer (`library.mjs` makes them
 * one); the reviewer is whoever `peer-review.mjs` made one.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_APPLICANT, SMOKE_REVIEWER,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const WRITER = process.env.SMOKE_APPLICANT ?? 'student@akuru.edu.mv';
const REVIEWER = process.env.SMOKE_REVIEWER ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const DRAFT = 'SMOKE-Lang-Research';

// Props that carry codes the page must name, and the phrase books it is sent:
// their values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'id_l', 'nav', 'locale', 'locales', 'locale_urls', 'content_types', 'content_type', 'access_type',
    'status', 'state', 'delivery', 'difficulty', 'language', 'recommendation', 'errors', 'flash',
]);
const ALWAYS_FINE = [
    /https?:\/\/\S*/g, /\bPDF\b/g, /\bHTML\b/g, /\bJPEG\b/g, /\bPNG\b/g, /\bWebP\b/g, /\bMB\b/g,
    /\bFacebook\b/g, /\bInstagram\b/g, /\bYouTube\b/g, /\bLinkedIn\b/g, /\bTelegram\b/g,
];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});

function authorsWords(value, key = '', out = []) {
    if (CODE_KEYS.has(key)) {
        return out;
    }
    if (typeof value === 'string') {
        // A paper's body arrives as HTML and is shown as its text, piece by
        // piece between the tags.
        for (const piece of value.split(/<[^>]*>/)) {
            if (piece.trim().length >= 2) {
                out.push(piece.trim());
            }
        }
    } else if (Array.isArray(value)) {
        value.forEach((item) => authorsWords(item, key, out));
    } else if (value && typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => authorsWords(v, k, out));
    }
    return out;
}

function english(strings, allowed) {
    const longestFirst = [...new Set(allowed)].sort((a, b) => b.length - a.length);
    return strings.filter((raw) => {
        let rest = raw;
        for (const pattern of ALWAYS_FINE) {
            rest = rest.replace(pattern, ' ');
        }
        for (const word of longestFirst) {
            rest = rest.split(word).join(' ');
        }
        return /[A-Za-z]{2,}/.test(rest);
    });
}

async function signIn(email) {
    const page = await (await browser.newContext()).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

async function flashReads(viewer, startsWith) {
    await viewer.waitForFunction(
        (want) => (document.querySelector('[data-testid="flash-success"]')?.textContent?.trim() ?? '').startsWith(want),
        startsWith,
        { timeout: 10000 },
    ).catch(() => {});
    return (await viewer.getByTestId('flash-success').textContent({ timeout: 1000 }).catch(() => null))?.trim() ?? null;
}

const writer = await signIn(WRITER);
const reviewer = await signIn(REVIEWER);

await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
const english0 = await props(writer);
check('the walker is an approved writer', Boolean(english0.dashboard?.profile), 'not a writer yet — run scripts/smoke/library.mjs first');
// English reads as it did: the book's English is the page's old text.
check('the English page reads as it did', (await writer.locator('main').textContent()).includes('New draft'), 'no "New draft" on /en/write');

// --------------------------------------- the draft, saved on the Dhivehi page

await writer.goto(`${BASE}/dv/write`, { waitUntil: 'networkidle' });
const dv = (await props(writer)).i18n?.common ?? {};
const draftRow = () => writer.locator('tr', { hasText: DRAFT }).first();
if (!(await draftRow().count())) {
    await writer.getByRole('button', { name: dv.library_new_draft, exact: true }).click();
    const editor = writer.getByTestId('draft-editor');
    await editor.getByLabel(dv.library_f_title, { exact: true }).fill(DRAFT);
    await editor.getByLabel(dv.library_f_type, { exact: true }).selectOption('research');
    await editor.locator('input[name="declarations[copyright]"]').check();
    await editor.getByRole('button', { name: dv.library_save_draft, exact: true }).click();
    const said = await flashReads(writer, dv.library_flash_draft_saved);
    check('the writer saves a draft on the Dhivehi page and is told so in Dhivehi', Boolean(dv.library_flash_draft_saved) && Boolean(said?.startsWith(dv.library_flash_draft_saved)) && !/[A-Za-z]{3,}/.test(said.replace(/PDF/g, '')), `said: ${said ?? 'nothing'}`);
} else {
    check('the writer saves a draft on the Dhivehi page and is told so in Dhivehi', true, 'skipped: SMOKE-Lang-Research is there from a run before');
}

// ------------------------- Submit for review, refused on its row, in Dhivehi

await writer.goto(`${BASE}/dv/write`, { waitUntil: 'networkidle' });
await draftRow().getByRole('button', { name: dv.library_submit_review, exact: true }).click();
const refusal = (await draftRow().locator('ul[role="alert"]').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'a research draft without its declarations is refused on its row, in Dhivehi',
    Boolean(refusal) && /\p{Script=Thaana}/u.test(refusal) && !/[A-Za-z]{2,}/.test(refusal),
    `said: ${refusal ?? 'nothing'}`,
);
check(
    'naming the two declarations it lacks',
    Boolean(refusal) && refusal.includes(dv.library_decl_name_originality) && refusal.includes(dv.library_decl_name_conflict_of_interest),
    `said: ${refusal ?? 'nothing'}`,
);
await writer.goto(`${BASE}/dv/write`, { waitUntil: 'networkidle' });
const kept = ((await props(writer)).dashboard?.items || []).find((row) => row.title === DRAFT);
check('and the draft stays a draft', kept?.status === 'draft', `status=${kept?.status}`);

// ------------------------------------------------- the pages, in both languages

const screens = [
    ['/write', writer, { open: (viewer, t) => viewer.getByRole('button', { name: t.library_new_draft, exact: true }).click() }],
    ['/review', reviewer],
];

for (const locale of ['dv', 'ar']) {
    for (const [path, viewer, options = {}] of screens) {
        const response = await viewer.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        if (!(await viewer.locator('main').count())) {
            check(`${locale}${path}: the screen opens`, false, `HTTP ${response?.status()} ${viewer.url().replace(BASE, '')}`);
            continue;
        }
        const pageProps = await props(viewer);
        if (options.open) {
            await options.open(viewer, pageProps.i18n?.common ?? {});
            await viewer.waitForTimeout(400);
        }
        const found = await viewer.evaluate(() => {
            const main = document.querySelector('main');
            const texts = [];
            const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) {
                const node = walker.currentNode;
                if (node.parentElement?.closest('script, style, textarea, code, pre, [contenteditable="true"]')) {
                    continue;
                }
                const text = node.textContent.replace(/\s+/g, ' ').trim();
                if (text) {
                    texts.push(text);
                }
            }
            for (const el of main.querySelectorAll('[placeholder], [aria-label], [title]')) {
                for (const name of ['placeholder', 'aria-label', 'title']) {
                    const value = el.getAttribute(name);
                    if (value && !el.closest('code, pre')) {
                        texts.push(value.trim());
                    }
                }
            }
            const unnamed = [...main.querySelectorAll('select, textarea, input:not([type=hidden]):not([type=submit]):not([type=button])')]
                .filter((el) => !(el.labels?.length || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title')))
                .map((el) => el.outerHTML.slice(0, 90));
            return { dir: document.documentElement.getAttribute('dir'), texts, unnamed };
        });
        const left = english(found.texts, authorsWords(pageProps));
        check(`${locale}${path}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${locale}${path}: nothing in English`, left.length === 0, [...new Set(left)].slice(0, 12).join(' | '));
        check(`${locale}${path}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
    }
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
