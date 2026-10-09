/**
 * Do the report card and the transcript read in Dhivehi and Arabic? (STATUS
 * §5pz)
 *
 * A Dhivehi report card printed placeholders where its headings belonged —
 * *Student (DV)*, *Grades (DV)*, fifteen of them — and an Arabic card, and an
 * Arabic transcript, came out in English. The office chooses the card's
 * language when it generates the term's cards; a family asks for the
 * transcript from the report cards page, in the page's language (slice PT2).
 *
 *   1. the dean generates `SMOKE-Doc-Term`'s cards for the walk's class in
 *      Dhivehi, the queue renders them, and the card opens with its
 *      headings in Dhivehi and no placeholder left; then again in Arabic;
 *   2. the parent asks for the transcript from `/dv` and `/ar` report cards,
 *      and it opens right to left with its headings in that language.
 *
 * `SmokeMarkerSeeder` plants `SMOKE-Doc-Term` and removes its cards each run.
 * The cards are never published, so no family sees them.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/documents-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_DEAN, SMOKE_PARENT, SMOKE_PASSWORD,
 * SMOKE_CLASS (default `Grade 5 A`), SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const DEAN = process.env.SMOKE_DEAN ?? 'headmaster@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const CLASS_LABEL = process.env.SMOKE_CLASS ?? 'Grade 5 A';
const TERM = 'SMOKE-Doc-Term';

const books = Object.fromEntries(['dv', 'ar'].map((locale) => [
    locale,
    JSON.parse(execFileSync('php', ['-r', `echo json_encode(require "resources/lang/${locale}/documents.php");`]).toString()),
]));

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

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

// What a document says: its language, direction, headings, and any placeholder.
const readDocument = (page) => page.evaluate(() => ({
    lang: document.documentElement.getAttribute('lang'),
    dir: document.documentElement.getAttribute('dir'),
    title: document.title,
    headings: [...document.querySelectorAll('h1, h2, th, strong')].map((el) => el.textContent.trim()),
    text: document.body.innerText,
}));

// ------------------------------------------------- 1. the dean's report cards

const dean = await signIn(DEAN);
await dean.goto(`${BASE}/en/exams/report-cards`, { waitUntil: 'networkidle' });
const generate = dean.locator('form', { hasText: 'Generate' }).first();
const classOption = await generate.locator('select').nth(0).locator('option', { hasText: CLASS_LABEL }).first().getAttribute('value').catch(() => null);
const termOption = await generate.locator('select').nth(1).locator('option', { hasText: TERM }).first().getAttribute('value').catch(() => null);
check(`the generate form offers ${CLASS_LABEL} and ${TERM}`, Boolean(classOption && termOption), `class ${classOption}, term ${termOption}`);

for (const locale of ['dv', 'ar']) {
    const book = books[locale].report_card;
    if (!classOption || !termOption) {
        break;
    }
    await dean.goto(`${BASE}/en/exams/report-cards?class_id=${classOption}&term_id=${termOption}`, { waitUntil: 'networkidle' });
    const form = dean.locator('form', { hasText: 'Generate' }).first();
    await form.locator('select').nth(0).selectOption(classOption);
    await form.locator('select').nth(1).selectOption(termOption);
    await form.locator('select').nth(2).selectOption(locale);
    await form.locator('button:has-text("Generate")').click();
    await dean.waitForLoadState('networkidle');
    // The cards render on the queue; drain it here rather than wait on a worker.
    execFileSync('php', ['artisan', 'queue:work', '--stop-when-empty', '--tries=1'], { encoding: 'utf8' });

    await dean.goto(`${BASE}/en/exams/report-cards?class_id=${classOption}&term_id=${termOption}`, { waitUntil: 'networkidle' });
    const open = dean.locator('tr', { hasText: TERM }).locator('a[href*="/download"]:not([href*="format=pdf"])').first();
    if ((await open.count()) === 0) {
        check(`${locale}: a report card is ready to open`, false, 'no card for the term');
        continue;
    }
    await dean.goto(new URL(await open.getAttribute('href'), BASE).href, { waitUntil: 'domcontentloaded' });
    const card = await readDocument(dean);
    check(`${locale}: the report card is in ${locale}, right to left`, card.lang === locale && card.dir === 'rtl', `lang=${card.lang} dir=${card.dir}`);
    // The card's own header is the office's words (the template's), so the
    // headings checked are the ones the card itself writes.
    check(`${locale}: its headings are the ${locale} ones`, card.headings.includes(`${book.student}:`) && card.headings.includes(`${book.class}:`) && card.headings.includes(`${book.term}:`),
        card.headings.slice(0, 6).join(' | '));
    check(`${locale}: and no placeholder heading is left`, !card.text.includes('(DV)'), card.text.match(/[^\n]*\(DV\)[^\n]*/)?.[0] ?? '');
}

// --------------------------------------------- 2. the parent's transcripts

const parent = await signIn(PARENT);
for (const locale of ['dv', 'ar']) {
    const book = books[locale].transcript;
    await parent.goto(`${BASE}/${locale}/portal/report-cards`, { waitUntil: 'networkidle' });
    const href = await parent.getByTestId('transcript').getAttribute('href').catch(() => null);
    if (!href) {
        check(`${locale}: the report cards page offers a transcript`, false, 'no transcript link');
        continue;
    }
    await parent.goto(new URL(href, BASE).href, { waitUntil: 'domcontentloaded' });
    const transcript = await readDocument(parent);
    check(`${locale}: the transcript is in ${locale}, right to left`, transcript.lang === locale && transcript.dir === 'rtl', `lang=${transcript.lang} dir=${transcript.dir}`);
    check(`${locale}: its headings are the ${locale} ones`, transcript.headings.includes(book.title) && transcript.headings.includes(book.point) && !transcript.text.includes('Academic transcript'),
        transcript.headings.slice(0, 8).join(' | '));
}

await browser.close();
for (const problem of problems) {
    results.push([problem, false, '']);
}
const failed = results.filter(([, ok]) => !ok);
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${step}${detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
