/**
 * Do the Digital Library's public pages, the wallet and gift cards read in
 * Dhivehi and Arabic? (BACKLOG C20, slice LT6, STATUS §5pk.)
 *
 * A visitor opens the shelf, the research shelf, the smoke seed's paid and
 * sign-in books, the offers, an author and the gift cards under /dv and /ar.
 * As in `front-door-language.mjs`, nothing of the site's English may be on
 * them, every field named. Signed in, the student reads SMOKE-Primer in
 * Dhivehi, opens My Library and the wallet — a wallet row's kind and source
 * named, not printed as codes — and is refused three times, each in
 * Dhivehi and beside what was refused: a gift card code that does not
 * exist, a discount code that does not exist, and a gift card with nowhere
 * to send it. None of the three writes a row.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/library-public-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const READER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// The site's English, from the books; and what these pages printed as codes before LT6.
const books = JSON.parse(execFileSync('php', ['-r', `
    $out = [];
    foreach (['public', 'site', 'nav', 'shop', 'account'] as $book) {
        foreach (require "resources/lang/en/{$book}.php" as $value) {
            if (is_string($value)) { $out[] = $value; }
        }
    }
    echo json_encode($out);
`]).toString());
const TYPED_IN = ['book', 'article', 'research', 'credit', 'debit', 'gift card', 'purchase', 'paid', 'pending', 'refunded', 'Dhivehi', 'Arabic'];
const INSIDE = [/\bpublic\.[A-Za-z_]/, /\bBuy for\b/, /\d off\b/, /\bmin read\b/, /\bPage \d/];
// Names in their own right; the language picker names English in English.
const FINE = new Set(['Facebook', 'Twitter', 'Instagram', 'YouTube', 'LinkedIn', 'Telegram', 'SMS', 'English', '🇬🇧 English', 'SMOKE', 'Viber', 'MVR', 'WhatsApp', 'Akuru', 'K. Malé', 'Malé', 'OK', 'ID', 'PDF', 'CSV']);
const phrases = [...new Set([...books, ...TYPED_IN])]
    .map((p) => p.trim())
    .filter((p) => /[A-Za-z]{2,}/.test(p) && !p.includes(':') && !p.includes('|') && !FINE.has(p));
const long = phrases.filter((p) => p.split(/\s+/).length >= 3);
const book = (locale, name) => JSON.parse(execFileSync('php', ['-r', `echo json_encode(require "resources/lang/${locale}/${name}.php");`]).toString());
const dv = book('dv', 'public');
const dvCommon = book('dv', 'common');

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
            // An item's body, a category's or an offer's name are the office's words (`data-office-words`).
            acceptNode: (n) => (n.parentElement?.closest('script, style, noscript, #google_translate_element, .skiptranslate, .prose, [data-office-words]') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
        });
        while (walker.nextNode()) {
            const text = walker.currentNode.textContent.trim();
            if (text) texts.push(text);
        }
        for (const el of document.body.querySelectorAll('[placeholder], [aria-label], [title]')) {
            if (el.closest('[data-office-words]')) continue;
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

async function walk(page, url, label) {
    const response = await page.goto(url, { waitUntil: 'networkidle' });
    const found = await englishOn(page);
    check(`${label}: answers`, response?.status() === 200, `HTTP ${response?.status()}`);
    check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
    check(`${label}: nothing in English`, found.hits.length === 0, found.hits.slice(0, 10).join(' | '));
    check(`${label}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
}

const guest = await visitor();
for (const locale of ['dv', 'ar']) {
    for (const path of ['/library', '/library?content_type=research', '/library/smoke-primer-paid', '/library/smoke-primer', '/library/promotions', '/gift-cards']) {
        await walk(guest, `${BASE}/${locale}${path}`, `${locale}${path}`);
    }
    // An author: whoever the shelf lists first, as a reader would reach them.
    await guest.goto(`${BASE}/${locale}/library`, { waitUntil: 'networkidle' });
    const author = await guest.locator('[data-testid="library-author"]').first().getAttribute('href').catch(() => null);
    if (author) {
        await walk(guest, author, `${locale} an author's page`);
    } else {
        console.log(`note  ${locale}: the shelf lists no writer, so no author's page was walked`);
    }
}

// The student, in Dhivehi.
const reader = await visitor(READER);
await walk(reader, `${BASE}/dv/library/smoke-primer/read?page=1`, 'dv the reader');
await walk(reader, `${BASE}/dv/my-library`, 'dv My Library');
await walk(reader, `${BASE}/dv/my-wallet`, 'dv the wallet');
const rows = await reader.locator('[data-testid="wallet-source"]').allInnerTexts();
check('dv the wallet: a row\'s source is named, not a code', rows.length > 0 && !rows.some((t) => /^(purchase|gift card|admin|refund)$/.test(t.trim())), rows.slice(0, 5).join(' | '));

// Three refusals, none of which writes a row.
await reader.fill('input[name="code"]', 'AKG-NOPE-NOPE-NOPE');
await Promise.all([reader.waitForNavigation({ waitUntil: 'networkidle' }), reader.click('form[action$="/my-wallet/redeem"] button[type=submit]')]);
let said = await reader.locator('main').innerText();
check('dv the wallet: a code that does not exist is refused in Dhivehi', said.includes(dvCommon['gift_card_error_not_found']), said.slice(0, 200));

await reader.goto(`${BASE}/dv/library/smoke-primer-paid`, { waitUntil: 'networkidle' });
const buy = reader.locator('form[action$="/checkout"]');
if (await buy.count()) {
    await reader.fill('input[name="discount_code"]', 'SMOKE-LT6-NOPE');
    await Promise.all([reader.waitForNavigation({ waitUntil: 'networkidle' }), buy.locator('button[type=submit]:not([name])').click()]);
    said = await reader.locator('main').innerText();
    check('dv SMOKE-Primer-Paid: a discount code that does not exist is refused in Dhivehi', said.includes(dvCommon['error_discount_not_found']), said.slice(0, 200));
} else {
    check('dv SMOKE-Primer-Paid: the student is offered it to buy', false, 'no checkout form — already theirs?');
}

await reader.goto(`${BASE}/dv/gift-cards`, { waitUntil: 'networkidle' });
await reader.fill('input[name="recipient_name"]', 'SMOKE-LT6 Recipient');
await reader.fill('input[name="recipient_email"]', '');
await reader.fill('input[name="recipient_mobile"]', '');
await Promise.all([reader.waitForNavigation({ waitUntil: 'networkidle' }), reader.click('[data-testid="gift-card-form"] button[type=submit]')]);
said = await reader.locator('main').innerText();
check('dv gift cards: a card with nowhere to send it is refused in Dhivehi', said.includes(dvCommon['gift_card_error_send_to']), said.slice(0, 200));

await walk(reader, `${BASE}/dv/gift-cards/return`, 'dv the gift card return');
await walk(reader, `${BASE}/dv/library/smoke-primer-paid/payment-return`, 'dv a payment return');
said = await reader.locator('main').innerText();
check('dv a payment return: says it is confirming, in Dhivehi', said.includes(dv['Confirming your payment…']), said.slice(0, 200));

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(58)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
