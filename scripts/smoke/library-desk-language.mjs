/**
 * Does the library desk read in Dhivehi and Arabic? (BACKLOG C21, slice LD1,
 * STATUS §5qt.)
 *
 * The dean opens the desk, a title, its label sheet and the borrower cards
 * under /dv and /ar. The walk lists what is still in Latin letters in each
 * page's main: every text node, placeholder, aria-label, title and phone
 * caption (`data-label`). What a page shows of its data (a book's title, an
 * accession number, a pupil's name and number) is the author's, so a string
 * found among the page's props passes; the phrase book the page is sent does
 * not. Each page must be right to left, every field in it named, and it must
 * open where it was asked for.
 *
 * Then, in Dhivehi, the dean:
 *   - adds a title with no name, and is refused under the form, the field
 *     named in Dhivehi; none is made;
 *   - adds the walk's title, told in Dhivehi, and two copies of it, told how
 *     many; each copy's state is named in Dhivehi;
 *   - lends a label nobody has, and is refused under the form — it was a
 *     bare 422 page; lends a copy to a card nobody has, refused by name;
 *   - lends a copy by scanning a pupil's borrower card — the box takes the
 *     card's student number, where it took the pupil's row id — told in
 *     Dhivehi when it is due back; and takes it back, told in Dhivehi;
 *   - issues the title to that pupil from its page, then again, and the
 *     result names the pupil and why they were skipped, in Dhivehi — the
 *     result never reached the page; and collects it back in, told in
 *     Dhivehi.
 *
 * The walk's title, its copies and their loans are removed first and last.
 *
 *   node scripts/smoke/library-desk-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_DEAN, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const DEAN = process.env.SMOKE_DEAN ?? 'headmaster@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const TITLE = 'SMOKE-Desk-Title';

// Props that carry the phrase books and codes the page must name: their
// values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set(['i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'href', 'status', 'status_label', 'reason']);
// The names of a format and a book number, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bISBN\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();

function authorsWords(value, key = '', out = []) {
    if (CODE_KEYS.has(key)) {
        return out;
    }
    if (typeof value === 'string') {
        if (value.trim().length >= 2) {
            out.push(value.trim());
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
        for (const word of longestFirst) {
            rest = rest.split(word).join(' ');
        }
        for (const pattern of ALWAYS_FINE) {
            rest = rest.replace(pattern, ' ');
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

const readMain = (page) => page.evaluate(() => {
    const main = document.querySelector('main') || document.body;
    const texts = [];
    const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        if (walker.currentNode.parentElement?.closest('style, script, textarea, svg')) continue;
        const value = walker.currentNode.nodeValue.trim();
        if (value) texts.push(value);
    }
    main.querySelectorAll('[placeholder],[aria-label],[title],[data-label]').forEach((el) => {
        // A barcode's label is the number it encodes.
        if (el.closest('svg')) return;
        ['placeholder', 'aria-label', 'title', 'data-label'].forEach((name) => {
            const value = el.getAttribute(name);
            if (value && value.trim()) texts.push(value.trim());
        });
    });
    const unnamed = [...main.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((el) => !el.getAttribute('aria-label')
        && !(el.id && main.querySelector(`label[for="${el.id}"]`)) && !el.closest('label')).map((el) => el.outerHTML.slice(0, 80));
    return { texts, unnamed, dir: document.documentElement.getAttribute('dir') };
});

async function readScreen(page, locale, path, query = '') {
    const label = `${locale}${path}${query}`;
    const response = await page.goto(`${BASE}/${locale}${path}${query}`, { waitUntil: 'networkidle' });
    const where = new URL(page.url());
    if (!response || response.status() >= 400 || where.pathname !== `/${locale}${path}`) {
        check(`${label}: the page opens`, false, `HTTP ${response?.status()} ${page.url().replace(BASE, '')}`);
        return;
    }
    const found = await readMain(page);
    const left = english(found.texts, authorsWords(await props(page)));
    check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
    check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
    check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
}

const inDhivehi = (said) => Boolean(said) && /\p{Script=Thaana}/u.test(said) && !/[A-Za-z]{3,}/.test(said);
const dvSays = (key, args = '[]') => tinker(`echo __('${key}', ${args}, 'dv');`);
const flashed = (page, text) => page.getByText(text, { exact: true }).first().waitFor({ timeout: 10000 }).then(() => true).catch(() => false);
const refusedUnder = async (form) => (await form.locator('ul.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;

// The walk's own title, and everything hanging off it, gone.
const cleanUp = () => tinker(`$ids = DB::table('book_titles')->where('title', '${TITLE}')->pluck('id'); $copies = DB::table('book_copies')->whereIn('book_title_id', $ids)->pluck('id'); DB::table('loans')->whereIn('book_copy_id', $copies)->delete(); DB::table('book_copies')->whereIn('id', $copies)->delete(); DB::table('book_titles')->whereIn('id', $ids)->delete(); echo 'ok';`);
// A pupil with a student number, as a borrower card would carry it.
const pupil = JSON.parse(tinker(`$s = DB::table('students')->whereNotNull('student_id')->where('student_id', '!=', '')->where('status', 'active')->orderBy('id')->first(); echo json_encode(['id' => (int) $s->id, 'number' => $s->student_id, 'name' => App\\Support\\PersonName::ofStudent($s), 'first' => $s->first_name]);`));
check('the school has a pupil with a student number to lend to', Boolean(pupil.number), `${pupil.name} (${pupil.number})`);

cleanUp();
const dean = await signIn(DEAN);

// ------------------------------------- the desk and the cards, read
for (const locale of ['dv', 'ar']) {
    await readScreen(dean, locale, '/circulation');
    await readScreen(dean, locale, '/circulation/cards', `?q=${encodeURIComponent(pupil.first)}`);
}

// ------------------------------------- a title with no name, refused in Dhivehi
await dean.goto(`${BASE}/dv/circulation`, { waitUntil: 'networkidle' });
const book = (await props(dean)).t ?? {};
const titleForm = dean.locator('form').filter({ has: dean.getByRole('button', { name: book.add, exact: true }) }).first();
await titleForm.getByLabel(book.field_author, { exact: true }).fill('SMOKE author');
await titleForm.getByRole('button', { name: book.add, exact: true }).click();
const refusedTitle = await refusedUnder(titleForm);
const madeNone = Number(tinker(`echo DB::table('book_titles')->where('author', 'SMOKE author')->count();`)) === 0;
check('a title with no name is refused under the form, the field named in Dhivehi; none is made',
    inDhivehi(refusedTitle) && refusedTitle.includes(dvSays('circulation.attr_title')) && madeNone, `said: ${refusedTitle ?? 'nothing'}`);

// ------------------------------------- the walk's title, and two copies
await titleForm.getByLabel(book.field_title, { exact: true }).fill(TITLE);
await titleForm.getByRole('button', { name: book.add, exact: true }).click();
const added = await flashed(dean, dvSays('circulation.flash_title_added'));
const titleId = Number(tinker(`echo (int) DB::table('book_titles')->where('title', '${TITLE}')->value('id');`));
check('the walk’s title is added, said in Dhivehi', added && titleId > 0, `told: ${added}; title ${titleId}`);

await dean.goto(`${BASE}/dv/circulation/titles/${titleId}`, { waitUntil: 'networkidle' });
const copiesForm = dean.locator('form').filter({ has: dean.getByText(book.add_copies, { exact: true }) }).first();
await copiesForm.locator('input[type=number]').fill('2');
await copiesForm.getByRole('button', { name: book.add, exact: true }).click();
const copiesAdded = await flashed(dean, dvSays('circulation.flash_copies_added', "['count' => 2]"));
const states = (await dean.locator('main tbody tr td:nth-child(2)').allTextContents()).map((s) => s.trim());
check('two copies are added, told how many in Dhivehi, each copy’s state named in Dhivehi',
    copiesAdded && states.length === 2 && states.every((s) => s === book.copy_status_available), `told: ${copiesAdded}; states: ${states.join(' | ')}`);
const accessions = (await dean.locator('main tbody tr td:nth-child(1)').allTextContents()).map((s) => s.trim());

for (const locale of ['dv', 'ar']) {
    await readScreen(dean, locale, `/circulation/titles/${titleId}`);
    await readScreen(dean, locale, `/circulation/titles/${titleId}/labels`);
}

// ------------------------------------- lending: a label and a card nobody has, refused
await dean.goto(`${BASE}/dv/circulation`, { waitUntil: 'networkidle' });
const lendForm = dean.locator('form').filter({ has: dean.getByRole('button', { name: book.lend, exact: true }) }).first();
await lendForm.getByLabel(book.accession_number, { exact: true }).fill('AK999999');
await lendForm.getByLabel(book.borrower_card, { exact: true }).fill(pupil.number);
await lendForm.getByRole('button', { name: book.lend, exact: true }).click();
const refusedLabel = await refusedUnder(lendForm);
check('a label nobody has is refused under the form, in Dhivehi, on the desk — it was a bare 422 page',
    refusedLabel === dvSays('circulation.error_copy_not_found') && new URL(dean.url()).pathname === '/dv/circulation', `said: ${refusedLabel ?? 'nothing'} at ${dean.url().replace(BASE, '')}`);

// A fresh desk each time, so the refusal read is this answer's, not the last.
await dean.goto(`${BASE}/dv/circulation`, { waitUntil: 'networkidle' });
await lendForm.getByLabel(book.accession_number, { exact: true }).fill(accessions[0] ?? '');
await lendForm.getByLabel(book.borrower_card, { exact: true }).fill('SMOKE-NO-CARD');
await lendForm.getByRole('button', { name: book.lend, exact: true }).click();
const refusedCard = await refusedUnder(lendForm);
check('a card nobody has is refused under the form, in Dhivehi; nothing is lent',
    refusedCard === dvSays('circulation.error_no_such_pupil') && Number(tinker(`echo DB::table('loans')->whereIn('book_copy_id', DB::table('book_copies')->where('book_title_id', ${titleId})->pluck('id'))->count();`)) === 0, `said: ${refusedCard ?? 'nothing'}`);

// ------------------------------------- lending by the card's number, and taking it back
await dean.goto(`${BASE}/dv/circulation`, { waitUntil: 'networkidle' });
await lendForm.getByLabel(book.accession_number, { exact: true }).fill(accessions[0] ?? '');
await lendForm.getByLabel(book.borrower_card, { exact: true }).fill(pupil.number);
await lendForm.getByRole('button', { name: book.lend, exact: true }).click();
const due = tinker(`echo now()->addDays(14)->toDateString();`);
const lent = await flashed(dean, dvSays('circulation.flash_lent', `['date' => '${due}']`));
const borrower = Number(tinker(`echo (int) DB::table('loans')->where('status', 'out')->whereIn('book_copy_id', DB::table('book_copies')->where('book_title_id', ${titleId})->pluck('id'))->value('student_id');`));
check('a copy is lent by the card’s student number to its pupil, told in Dhivehi when it is due back', lent && borrower === pupil.id, `told: ${lent}; lent to ${borrower}, expected ${pupil.id}`);

const backForm = dean.locator('form').filter({ has: dean.getByRole('button', { name: book.take_back, exact: true }) }).first();
await backForm.getByLabel(book.accession_number, { exact: true }).fill(accessions[0] ?? '');
await backForm.getByRole('button', { name: book.take_back, exact: true }).click();
const tookBack = await flashed(dean, dvSays('circulation.flash_returned'));
check('it is taken back, said in Dhivehi', tookBack, `told: ${tookBack}`);

// ------------------------------------- a class issue, its result by name
await dean.goto(`${BASE}/dv/circulation/titles/${titleId}?q=${encodeURIComponent(pupil.number)}`, { waitUntil: 'networkidle' });
const tick = dean.locator('main li label', { hasText: pupil.name }).first().locator('input[type=checkbox]');
await tick.check();
await dean.getByRole('button', { name: book.issue_one, exact: true }).click();
const issued = await flashed(dean, dvSays('circulation.flash_issued_all', "['issued' => 1, 'skipped' => 0]"));
check('the title is issued to the pupil from its page, said in Dhivehi', issued, `told: ${issued}`);

await dean.goto(`${BASE}/dv/circulation/titles/${titleId}?q=${encodeURIComponent(pupil.number)}`, { waitUntil: 'networkidle' });
await dean.locator('main li label', { hasText: pupil.name }).first().locator('input[type=checkbox]').check();
await dean.getByRole('button', { name: book.issue_one, exact: true }).click();
const result = (await dean.locator('[data-testid="bulk-result"]').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? '';
check('issued again, the result names the pupil and why they were skipped, in Dhivehi — it never reached the page',
    result.includes(pupil.name) && result.includes(dvSays('circulation.reason_has_copy')) && result.includes(book.result_not_issued), `result: ${result || 'nothing'}`);

await dean.goto(`${BASE}/dv/circulation/titles/${titleId}?q=${encodeURIComponent(pupil.number)}`, { waitUntil: 'networkidle' });
await dean.locator('main li label', { hasText: pupil.name }).first().locator('input[type=checkbox]').check();
await dean.getByRole('button', { name: book.collect, exact: true }).click();
const collected = await flashed(dean, dvSays('circulation.flash_collected', "['returned' => 1, 'outstanding' => 0]"));
check('it is collected back in, said in Dhivehi', collected, `told: ${collected}`);

cleanUp();
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
