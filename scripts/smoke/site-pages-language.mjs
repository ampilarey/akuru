/**
 * Do the public site's events, news, about, careers, achievements, gallery,
 * search and CMS pages read in Dhivehi and Arabic? (BACKLOG C20, slice LT5b,
 * STATUS §5pi.)
 *
 * A visitor opens each under /dv and /ar, and the smoke seed's
 * SMOKE-Lang-Event. As in `front-door-language.mjs`, the walk lists every
 * text node, placeholder, aria-label and title that is one of the site's
 * English phrases (the books' English, and the strings these pages used to
 * type in) or holds one of three words or more; what the office wrote (an
 * event's title, a CMS page's body) does not count. Each page must be right
 * to left and every field named.
 *
 * Then, on /dv, the visitor registers for the event and is told so in
 * Dhivehi; registering again with the same email is refused in Dhivehi
 * beside the form.
 *
 *   node scripts/smoke/site-pages-language.mjs
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
    'About Akuru Institute', 'Our Mission', 'Our Vision', 'Our Values', 'Our Commitment', 'Meet our team', 'Our Instructors',
    'What our students say', 'Student Testimonials', 'Join Our Community', 'Apply Now', 'View Courses', 'Students enrolled',
    'Courses offered', 'Expert teachers', 'Years of service', 'Achievements', 'No published school awards yet.', 'Photo on file',
    'Add to Calendar', 'Registration', 'Register Now', 'Registration submitted successfully!',
    'You are already registered for this event.', 'Event Details', 'Careers', 'No open positions right now.',
];
// English inside a line: a month's name, a closing date, the old calendar button.
const INSIDE = [/\b(January|February|March|April|June|July|August|September|October|November|December)\b/, /\bCloses \d/, /Download \.ics/, /\bEst\. \d{4}/];
const FINE = new Set(['Facebook', 'Twitter', 'Viber', 'MVR', 'WhatsApp', 'Akuru', '🇬🇧 English', 'English', 'K. Malé', 'Malé', 'OK', 'ID']);
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
    for (const path of ['/about', '/events', '/events/smoke-lang-event', '/news', '/gallery', '/careers', '/achievements', '/search?q=quran', '/page/privacy-policy']) {
        const response = await guest.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        const found = await englishOn(guest);
        const label = `${locale}${path}`;
        check(`${label}: answers`, response?.status() === 200, `HTTP ${response?.status()}`);
        check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label}: nothing in English`, found.hits.length === 0, found.hits.slice(0, 10).join(' | '));
        check(`${label}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
    }
}

// Register for the event on the Dhivehi page, twice with the same email.
const dvBook = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require "resources/lang/dv/public.php");']).toString());
// A fresh address each run; the smoke seed clears `smoke-lang-%` registrations.
const GUEST_EMAIL = `smoke-lang-${Date.now()}@akuru.invalid`;
const register = async () => {
    await guest.goto(`${BASE}/dv/events/smoke-lang-event`, { waitUntil: 'networkidle' });
    await guest.fill('form[action*="/register"] input[name="name"]', 'SMOKE-Lang-Guest');
    await guest.fill('form[action*="/register"] input[name="email"]', GUEST_EMAIL);
    await Promise.all([guest.waitForNavigation({ waitUntil: 'networkidle' }), guest.click('form[action*="/register"] button[type=submit]')]);
    return guest.locator('main').innerText();
};
const first = await register();
check('dv/events: the registration is confirmed in Dhivehi', first.includes(dvBook['Registration submitted successfully!']), first.slice(0, 200));
const second = await register();
check('dv/events: a second registration is refused in Dhivehi', second.includes(dvBook['You are already registered for this event.']), second.slice(0, 200));
check('dv/events: the refusal is not in English', !second.includes('You are already registered for this event.'));

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(58)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
