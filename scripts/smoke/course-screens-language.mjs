/**
 * Do the course-building screens read in Dhivehi and Arabic? (BACKLOG C19,
 * slices CT1–CT3, STATUS §5ok on.)
 *
 * The dean opens every translated course screen under /dv and /ar, and the
 * walk lists what is still in Latin letters: every text node in the page's
 * main, and every placeholder, aria-label and title in it. What a screen shows
 * of the data it was sent — a course's title, a question's text, a letter's
 * name, an author's typed label — is the author's, not the screen's, so a
 * string found among the page's props passes. So do CSV, PDF, JSON, HTML,
 * YouTube, Vimeo, addresses and a certificate's {{placeholders}}, and
 * whatever sits in a code or JSON box. Codes the
 * server sends to be named (a pattern, a status, a type) do not count as the
 * author's: printed raw, they fail. Anything left is English the screen wrote
 * itself, and fails the step.
 *
 * Each page must also be right to left, and every field in it must have a
 * name a screen reader can say.
 *
 *   node scripts/smoke/course-screens-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_MARKER, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const DEAN = process.env.SMOKE_MARKER ?? 'headmaster@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const COURSE = 'SMOKE-Course';

// Props that carry codes the screen must name, and English renderings of
// things that have a name in the page's language. Their values are not the
// author's words, so they do not excuse a string.
const CODE_KEYS = new Set([
    't', 'locale', 'locales', 'locale_urls', 'pattern', 'patterns', 'skills', 'types', 'status', 'workflow_status',
    'question_type', 'difficulty', 'assessment_type', 'unlock_mode', 'value', 'kind', 'mode', 'normalizationModes',
    'normalizationFlags', 'textInputTypes', 'decision', 'type', 'language', 'direction', 'align', 'tone', 'font',
    'completion_rule', 'submission_kind', 'unlockModes', 'decisions', 'label', 'decision_label', 'english_name',
]);
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\{\{[a-z_]+\}\}/g, /\bCSV\b/g, /\bPDF\b/g, /\bJSON\b/g, /\bHTML\b/g, /\bYouTube\b/g, /\bVimeo\b/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});

// An English name (`name_en`) is the author's only when the page's language
// has none: a level somebody typed in English alone is shown in English, and
// that is right; a subject with a Dhivehi name shown in English is not.
function authorsWords(value, locale, key = '', out = [], owner = {}) {
    if (CODE_KEYS.has(key) || (key.endsWith('_en') && owner[key.replace(/_en$/, `_${locale}`)])) {
        return out;
    }
    if (typeof value === 'string') {
        if (value.trim().length >= 2) {
            out.push(value.trim());
        }
    } else if (Array.isArray(value)) {
        value.forEach((item) => authorsWords(item, locale, key, out));
    } else if (value && typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => authorsWords(v, locale, k, out, value));
    }
    return out;
}

function english(strings, allowed) {
    const longestFirst = [...new Set(allowed)].sort((a, b) => b.length - a.length);
    return strings.filter((raw) => {
        // The fixed tokens first: a short prop string ("me", "Tube") taken out
        // of the middle of "YouTube" would leave half a word behind.
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

const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', DEAN);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

await page.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const course = ((await props(page)).rows || []).find((row) => row.title === COURSE);
check('the dean finds SMOKE-Course in the catalog', Boolean(course));

const screens = [
    '/catalog/courses',
    `/catalog/courses/${course?.id}/outline`,
    `/catalog/courses/${course?.id}/rubrics`,
    `/catalog/courses/${course?.id}/activities`,
    `/catalog/courses/${course?.id}/assessments`,
    '/catalog/questions',
    '/catalog/glossary',
    '/catalog/certificates',
    '/catalog/reviews',
];

for (const locale of ['dv', 'ar']) {
    for (const path of screens) {
        await page.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        const found = await page.evaluate(() => {
            const main = document.querySelector('main');
            const texts = [];
            const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) {
                const node = walker.currentNode;
                if (node.parentElement?.closest('script, style, textarea, code, pre')) {
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
                    if (value && !el.closest('code, pre') && !(name === 'placeholder' && el.classList.contains('font-mono'))) {
                        texts.push(value.trim());
                    }
                }
            }
            const unnamed = [...main.querySelectorAll('select, textarea, input:not([type=hidden]):not([type=submit]):not([type=button])')]
                .filter((el) => !(el.labels?.length || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title')))
                .map((el) => el.outerHTML.slice(0, 90));
            return { dir: document.documentElement.getAttribute('dir'), texts, unnamed };
        });
        const left = english(found.texts, authorsWords(await props(page), locale));
        check(`${locale}${path.replace(/\/\d+\//, '/{id}/')}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${locale}${path.replace(/\/\d+\//, '/{id}/')}: nothing in English`, left.length === 0, [...new Set(left)].slice(0, 12).join(' | '));
        check(`${locale}${path.replace(/\/\d+\//, '/{id}/')}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
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
