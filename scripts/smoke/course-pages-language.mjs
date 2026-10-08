/**
 * Do the public course catalogue and a course page read in Dhivehi and
 * Arabic? (BACKLOG C20, slice LT5a, STATUS §5ph.)
 *
 * A visitor opens /courses and the first course it lists, under /dv and /ar.
 * As in `front-door-language.mjs`, the walk lists every text node,
 * placeholder, aria-label and title that is one of the site's English phrases
 * (the books' English, and the strings these pages used to type in), or holds
 * one of three words or more; a course's own title and description are the
 * office's and do not count. Each page must be right to left, every field
 * named, and no course's status, level or duration printed as a code.
 *
 * Then, on /dv/courses, the visitor filters by level and the page comes back
 * in Dhivehi with the level still chosen.
 *
 *   node scripts/smoke/course-pages-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';

// The site's English, from the books; and what these pages typed in before LT4.
const books = JSON.parse(execFileSync('php', ['-r', `
    $out = [];
    foreach (['public', 'site', 'nav', 'shop'] as $book) {
        foreach (require "resources/lang/en/{$book}.php" as $value) {
            if (is_string($value)) { $out[] = $value; }
        }
    }
    echo json_encode($out);
`]).toString());
const TYPED_IN = [
    'Fully booked', 'Details', 'What you\'ll be able to do', 'Course Description', 'Your Instructors', 'Qualifications',
    'What students say', 'Class Schedule', 'Have more questions?', 'Contact us', 'Course Information', 'Seats',
    'Enroll Now', 'Or submit an inquiry', 'Have questions? Chat with us.', 'Chat on Viber', 'Send a Message', 'Related Courses',
    'View all', 'Free', 'Explore', 'all our courses', 'Register', 'Notify Me', 'Ask on WhatsApp', 'Get full syllabus',
    'Join waiting list', 'Limited seats', 'Ongoing', 'Enrollment closed', 'Enrollment opening soon', 'Mixed',
    'open', 'upcoming', 'closed', 'kids', 'youth', 'adult', 'all', 'Open', 'Upcoming', 'Closed', 'Kids', 'Youth', 'Adult', 'All',
];
// Codes and English that only ever appear as a word inside a line: `8 weeks`, `Starts 05 Dec 2026`, `3 seats left`.
const INSIDE = [/\b\d+ (weeks?|months?)\b/, /\bStarts \d/, /\b\d+ seats? left\b/, /\bdays? left\b/, /\bCloses \d/, /\bEarly bird until\b/];
const FINE = new Set(['Viber', 'MVR', 'WhatsApp', 'Akuru', '🇬🇧 English', 'English', 'K. Malé', 'Malé', 'OK', 'ID']);
const phrases = [...new Set([...books, ...TYPED_IN])]
    .map((p) => p.trim())
    .filter((p) => /[A-Za-z]{2,}/.test(p) && !p.includes(':') && !p.includes('|') && !FINE.has(p));
const long = phrases.filter((p) => p.split(/\s+/).length >= 3);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

async function visitor(email = null) {
    const page = await (await browser.newContext()).newPage();
    page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
    if (email) {
        await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
        await page.fill('input[name="identifier"]', email);
        await page.fill('input[name="password"]', PASSWORD);
        await page.click('button[type=submit]');
        await page.waitForLoadState('networkidle');
    }
    return page;
}

async function englishOn(page) {
    const found = await page.evaluate(() => {
        const texts = [];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
            // A course's body and a category's name are the office's words (`data-office-words`).
            acceptNode: (n) => (n.parentElement?.closest('script, style, noscript, #google_translate_element, .skiptranslate, .prose, [data-office-words]') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
        });
        while (walker.nextNode()) {
            const text = walker.currentNode.textContent.trim();
            if (text) texts.push(text);
        }
        for (const el of document.body.querySelectorAll('[placeholder], [aria-label], [title]')) {
            for (const name of ['placeholder', 'aria-label', 'title']) {
                const value = el.getAttribute(name)?.trim();
                if (value) texts.push(value);
            }
        }
        const unnamed = [...document.querySelectorAll('main select, main textarea, main input:not([type=hidden]):not([type=submit]):not([type=button]):not([tabindex="-1"])')]
            .filter((el) => !(el.labels?.length || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title')))
            .map((el) => el.outerHTML.slice(0, 90));
        return { dir: document.documentElement.getAttribute('dir'), texts, unnamed };
    });
    const hits = [];
    for (const text of found.texts) {
        const plain = text.replace(/^[^\p{L}]+|[^\p{L}.!?…]+$/gu, '').trim();
        if (phrases.includes(plain) || phrases.includes(text)) {
            hits.push(text);
            continue;
        }
        const inside = long.find((p) => text.length > p.length && new RegExp(`(^|[^A-Za-z])${p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}([^A-Za-z]|$)`).test(text));
        if (inside) hits.push(`${inside} (in “${text.slice(0, 60)}”)`);
        const pattern = INSIDE.find((p) => p.test(text));
        if (pattern) hits.push(`${pattern} (in “${text.slice(0, 60)}”)`);
    }
    return { ...found, hits: [...new Set(hits)] };
}

const guest = await visitor();
for (const locale of ['dv', 'ar']) {
    const response = await guest.goto(`${BASE}/${locale}/courses`, { waitUntil: 'networkidle' });
    const list = await englishOn(guest);
    check(`${locale}/courses: answers`, response?.status() === 200, `HTTP ${response?.status()}`);
    check(`${locale}/courses: right to left`, list.dir === 'rtl', `dir=${list.dir}`);
    check(`${locale}/courses: nothing in English`, list.hits.length === 0, list.hits.slice(0, 10).join(' | '));
    check(`${locale}/courses: every field has a name`, list.unnamed.length === 0, list.unnamed.slice(0, 4).join(' | '));

    const href = await guest.locator('main a[href*="/courses/"]').first().getAttribute('href');
    const page = await guest.goto(new URL(href, BASE).toString(), { waitUntil: 'networkidle' });
    const course = await englishOn(guest);
    const label = `${locale}/courses/${href.split('/courses/')[1]?.slice(0, 28)}`;
    check(`${label}: answers`, page?.status() === 200, `HTTP ${page?.status()}`);
    check(`${label}: right to left`, course.dir === 'rtl', `dir=${course.dir}`);
    check(`${label}: nothing in English`, course.hits.length === 0, course.hits.slice(0, 10).join(' | '));
    check(`${label}: every field has a name`, course.unnamed.length === 0, course.unnamed.slice(0, 4).join(' | '));
}

// Filter the Dhivehi catalogue by level.
await guest.goto(`${BASE}/dv/courses`, { waitUntil: 'networkidle' });
await guest.selectOption('select[name="level"]', 'all');
await Promise.all([guest.waitForNavigation({ waitUntil: 'networkidle' }), guest.locator('form:has(select[name="level"]) button[type=submit]').last().click()]);
const filtered = await englishOn(guest);
check('dv/courses?level=all: the filter is applied', /\/dv\/courses\?.*level=all/.test(guest.url()), guest.url());
check('dv/courses?level=all: the level stays chosen', (await guest.locator('select[name="level"]').inputValue()) === 'all');
check('dv/courses?level=all: still in Dhivehi', filtered.hits.length === 0 && filtered.dir === 'rtl', filtered.hits.slice(0, 6).join(' | '));

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(58)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
