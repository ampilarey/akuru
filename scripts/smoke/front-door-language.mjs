/**
 * Does the public site's front door read in Dhivehi and Arabic? (BACKLOG C20,
 * slice LT4, STATUS §5pg.)
 *
 * A visitor opens the home page, the quick apply page, admissions, the
 * thank-you page, contact and a page that does not exist, under /dv and /ar.
 * The walk lists every text node, placeholder, aria-label and title on the
 * page — header and footer included — that is one of the site's English
 * phrases (the `public`, `site`, `nav` and `shop` books' English, and the
 * strings these pages used to type in), or holds one of three words or more.
 * What the page shows of its data (a course's title, a news headline) is the
 * office's, so only the site's own words count. Each page must be right to
 * left and every field on it named.
 *
 * Then, signed in, the header's account menu is in the page's language; and
 * a visitor applies on /dv/apply and is thanked in Dhivehi, at an address
 * that does not end in `?dv`.
 *
 *   node scripts/smoke/front-door-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_APPLICANT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LEARNER = process.env.SMOKE_APPLICANT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

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
    'Islamic Education in the Maldives', 'Chat on Viber', 'Life at Akuru', 'Our Gallery', 'Student voices',
    'What Our Students Say', 'Latest News', 'All news', 'Upcoming Events', 'All events', 'No upcoming events scheduled.',
    'Daily content', 'Open permalink', 'Translate', 'Log out', 'Login', 'Signed in as', 'Search other language…',
    'Apply to Akuru Institute', 'Your Details', 'Mobile Number', 'Course Interested In', 'Message / Questions',
    'Submit Application', 'What happens next?', 'Questions?', 'Our admissions team is happy to help.', 'Contact Us',
    '8:00 AM - 4:00 PM', '8:00 AM - 12:00 PM', 'Select island', 'Search island or atoll…', 'Previous slide', 'Next slide',
];
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
            acceptNode: (n) => (n.parentElement?.closest('script, style, noscript, #google_translate_element, .skiptranslate') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
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
    }
    return { ...found, hits: [...new Set(hits)] };
}

const guest = await visitor();
for (const locale of ['dv', 'ar']) {
    for (const path of ['', '/apply', '/admissions', '/admissions/thanks', '/contact', '/no-such-page-lt4']) {
        const response = await guest.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        const status = response?.status();
        const found = await englishOn(guest);
        const label = `${locale}${path || '/'}`;
        check(`${label}: answers`, path === '/no-such-page-lt4' ? status === 404 : status === 200, `HTTP ${status}`);
        check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label}: nothing in English`, found.hits.length === 0, found.hits.slice(0, 10).join(' | '));
        check(`${label}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
    }
}

// The header's account menu, signed in.
const learner = await visitor(LEARNER);
for (const locale of ['dv', 'ar']) {
    await learner.goto(`${BASE}/${locale}`, { waitUntil: 'networkidle' });
    const found = await englishOn(learner);
    const menu = found.hits.filter((h) => /Log out|Signed in as|My Library|My Wallet/.test(h));
    check(`${locale}/ signed in: the account menu in the page's language`, menu.length === 0, menu.join(' | '));
}

// Apply on the Dhivehi page, and be thanked in Dhivehi.
await guest.goto(`${BASE}/dv/apply`, { waitUntil: 'networkidle' });
await guest.fill('form[action$="/apply"] input[name="full_name"]', 'SMOKE-Lang-Applicant');
await guest.fill('form[action$="/apply"] input[name="phone"]', '7770004');
await Promise.all([guest.waitForNavigation({ waitUntil: 'networkidle' }), guest.click('form[action$="/apply"] button[type=submit]')]);
const thanked = guest.url();
const thanks = await englishOn(guest);
check('dv/apply: the applicant is thanked', /\/dv\/admissions\/thanks/.test(thanked), thanked);
check('dv/apply: the thank-you address has no ?dv on it', !thanked.includes('?'), thanked);
check('dv/apply: thanked in Dhivehi', thanks.hits.length === 0 && thanks.dir === 'rtl', thanks.hits.slice(0, 6).join(' | '));

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(58)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
