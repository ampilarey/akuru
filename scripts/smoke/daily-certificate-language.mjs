/**
 * Do the daily reminders and the certificate check read in Dhivehi and
 * Arabic? (BACKLOG C20, slice LT5c, STATUS §5pj.)
 *
 * A visitor opens the reminders' archive and the smoke seed's reminder of
 * 2026-01-15 under /dv and /ar; as in `front-door-language.mjs`, nothing of
 * the site's English may be on them, every field named. Signed in, the
 * student opens the subscription page in Dhivehi, subscribes to reminders by
 * email and pauses it, and is told both in Dhivehi. Then three strangers scan
 * the smoke seed's certificate on its unlocalized address, their browsers set
 * to Dhivehi, Arabic and nothing: each reads it in their language.
 *
 *   node scripts/smoke/daily-certificate-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_APPLICANT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LEARNER = process.env.SMOKE_APPLICANT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const CERTIFICATE = 'SMKVERFYCERT00000000000000000001';

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
    'Daily content', 'Reminder archive', 'Month', 'Theme', 'Filter', 'Subscribe', 'Back to archive', 'Channel', 'Types',
    'Send time', 'Save subscription', 'Your channels', 'Pause', 'Resume', 'Reminder', 'Ayah', 'Hadith', 'Saying',
    'ayah', 'hadith', 'saying', 'reminder', 'active', 'paused', 'Open permalink',
];
const INSIDE = [/\bDaily (ayah|hadith|saying|reminder)\b/, /\b(January|February|March|April|June|July|August|September|October|November|December)\b/, /Opt-in only/];
const FINE = new Set(['Facebook', 'Twitter', 'SMS', 'English', 'SMOKE', 'Viber', 'MVR', 'WhatsApp', 'Akuru', '🇬🇧 English', 'English', 'K. Malé', 'Malé', 'OK', 'ID']);
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
    for (const path of ['/daily/reminder', '/daily/reminder/2026-01-15']) {
        const response = await guest.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        const found = await englishOn(guest);
        const label = `${locale}${path}`;
        check(`${label}: answers`, response?.status() === 200, `HTTP ${response?.status()}`);
        check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label}: nothing in English`, found.hits.length === 0, found.hits.slice(0, 10).join(' | '));
        check(`${label}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
    }
}

// Subscribe on the Dhivehi page, then pause it.
const dvBook = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require "resources/lang/dv/public.php");']).toString());
const arBook = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require "resources/lang/ar/public.php");']).toString());
const learner = await visitor(LEARNER);
await learner.goto(`${BASE}/dv/daily/subscribe`, { waitUntil: 'networkidle' });
const page = await englishOn(learner);
check('dv/daily/subscribe: nothing in English', page.hits.length === 0, page.hits.slice(0, 10).join(' | '));
check('dv/daily/subscribe: every field has a name', page.unnamed.length === 0, page.unnamed.slice(0, 4).join(' | '));
await learner.selectOption('select[name="channel"]', 'email');
for (const box of await learner.locator('input[name="content_types[]"]').all()) {
    await box.setChecked((await box.getAttribute('value')) === 'reminder');
}
await Promise.all([learner.waitForNavigation({ waitUntil: 'networkidle' }), learner.click('form[action$="/daily/subscribe"] button[type=submit]')]);
const saved = await learner.locator('main').innerText();
check('dv/daily/subscribe: the subscription is saved, said in Dhivehi', saved.includes(dvBook['Subscription saved. You will only receive messages you opted into.']), saved.slice(0, 200));
check('dv/daily/subscribe: the channel lists its kinds in Dhivehi', saved.includes(dvBook['daily_type_reminder']) && !/\breminder\b/.test(saved), saved.slice(0, 300));
const pause = learner.locator('[data-channel="email"] form[action*="/pause"] button');
if (await pause.count()) {
    await Promise.all([learner.waitForNavigation({ waitUntil: 'networkidle' }), pause.first().click()]);
}
const paused = await learner.locator('main').innerText();
check('dv/daily/subscribe: pausing it is said in Dhivehi', paused.includes(dvBook['Subscription paused.']), paused.slice(0, 200));
// Leave the student subscribed to nothing new: resume what was paused only if the walk paused it.

// A stranger scans the certificate, in three browsers.
for (const [label, locale, said] of [
    ['a Dhivehi browser', 'dv-MV', dvBook['This certificate is authentic.']],
    ['an Arabic browser', 'ar', arBook['This certificate is authentic.']],
    ['a browser in a language we do not speak', 'fr-FR', 'This certificate is authentic.'],
]) {
    const context = await browser.newContext({ locale, extraHTTPHeaders: { 'Accept-Language': locale } });
    const stranger = await context.newPage();
    const response = await stranger.goto(`${BASE}/verify/certificates/${CERTIFICATE}`, { waitUntil: 'domcontentloaded' });
    const text = await stranger.locator('body').innerText();
    const dir = await stranger.evaluate(() => document.documentElement.getAttribute('dir'));
    check(`the certificate check, scanned from ${label}: answers`, response?.status() === 200, `HTTP ${response?.status()}`);
    check(`the certificate check, scanned from ${label}: in its language`, text.includes(said) && text.includes('AK-SMOKE-VERIFY'), text.slice(0, 200));
    check(`the certificate check, scanned from ${label}: its direction`, dir === (locale.startsWith('fr') ? 'ltr' : 'rtl'), `dir=${dir}`);
    await context.close();
}

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(58)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
