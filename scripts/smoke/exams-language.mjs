/**
 * Do the office's exams and grades screens read in Dhivehi and Arabic?
 * (BACKLOG C21, slice EG1, STATUS §5qj.)
 *
 * The dean schedules an exam from the Dhivehi schedule and is told it was
 * scheduled, in Dhivehi. Then the dean opens the exam schedule, that exam's
 * marks, the gradebook, the assessment weights, the grade scales and the
 * exam types under /dv and /ar. The walk lists what is still in Latin
 * letters in each page's main: every text node, placeholder, aria-label,
 * title and phone caption (`data-label`). What a page shows of its data (an
 * exam's name, a class, a subject or exam type the school named only in
 * English, a band's grade) is the author's, so a string found among the
 * page's props passes; codes the server sends to be named (an exam's state,
 * an exam type's code, a scale's kind) do not, and printed raw they fail.
 * So do the phrase books the page is sent. Each page must be right to left,
 * every field in it named, and it must open where it was asked for.
 *
 * Then, in Dhivehi:
 *   - the dean moves the new exam straight from scheduled to locked and is
 *     refused under its row, both states named in Dhivehi (it named them by
 *     their codes, in English); the exam stays scheduled;
 *   - the dean saves the year's weight scheme a second time and is refused
 *     above the weights, in Dhivehi; no second scheme is made (if the year
 *     had none, the first save is said in Dhivehi too);
 *   - the dean adds an exam type with a code the school already has and is
 *     refused beside the code, in Dhivehi; no type is made.
 *
 * The exam is `SMOKE-Lang-Exam`, in `SMOKE-Term`; `SmokeMarkerSeeder`
 * removes it on every run, as it does `exams.mjs`'s `SMOKE-Exam`.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/exams-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_DEAN, SMOKE_PASSWORD, SMOKE_CLASS (the
 * label of a class in the year, as the schedule form shows it — default
 * `Grade 5 A`), SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const DEAN = process.env.SMOKE_DEAN ?? 'headmaster@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const CLASS_LABEL = process.env.SMOKE_CLASS ?? 'Grade 5 A';
const EXAM = 'SMOKE-Lang-Exam';
const TERM = 'SMOKE-Term';

// Props that carry codes the page must name, and the phrase books it is sent:
// their values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'csvUrl', 'href',
    // An exam's state, an exam type's code, a scale's kind.
    'status', 'statuses', 'code', 'codes', 'type',
]);
// A list of codes when it is a list of strings — a grade scale's kinds — and
// the school's own records otherwise: the exam types are `types` too.
const CODE_LISTS = new Set(['types']);
// The names of formats, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bJSON\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

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
    if (CODE_KEYS.has(key) || (CODE_LISTS.has(key) && Array.isArray(value) && value.every((item) => typeof item === 'string'))) {
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

// The author's words come out first, longest first, so a sentence the
// author wrote is not left half-taken (slice PT1b).
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

// Every piece of text a reader meets in the page's main, and whether it runs
// right to left; and every field with no name.
const readMain = (page) => page.evaluate(() => {
    const main = document.querySelector('main') || document.body;
    const texts = [];
    const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        // A field's value is not one of its labels: an input's is not read,
        // and neither is a textarea's — a grade scale's bands are written in
        // it as JSON.
        if (walker.currentNode.parentElement?.closest('style, script, textarea')) continue;
        const value = walker.currentNode.nodeValue.trim();
        if (value) texts.push(value);
    }
    main.querySelectorAll('[placeholder],[aria-label],[title],[data-label]').forEach((el) => {
        ['placeholder', 'aria-label', 'title', 'data-label'].forEach((name) => {
            const value = el.getAttribute(name);
            if (value && value.trim()) texts.push(value.trim());
        });
    });
    const unnamed = [...main.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((el) => !el.getAttribute('aria-label')
        && !(el.id && main.querySelector(`label[for="${el.id}"]`)) && !el.closest('label')).map((el) => el.outerHTML.slice(0, 80));
    return { texts, unnamed, dir: document.documentElement.getAttribute('dir') };
});

// A label matched whole, whatever brackets its words hold.
const exactly = (text) => new RegExp('^' + String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$');
const inDhivehi = (said) => Boolean(said) && /\p{Script=Thaana}/u.test(said) && !/[A-Za-z]{3,}/.test(said);
const dean = await signIn(DEAN);

// ------------------------------------- an exam scheduled, said in Dhivehi

await dean.goto(`${BASE}/dv/exams/schedule`, { waitUntil: 'networkidle' });
const book = (await props(dean)).t ?? {};
const one = dean.locator('form').filter({ has: dean.locator('h2', { hasText: book.exams_schedule_one }) }).first();
// A label's text runs straight into its options, so a field is found by the
// label that starts with its name.
const field = (label) => one.locator('label').filter({ has: dean.locator('span', { hasText: exactly(label) }) }).locator('select, input').first();
const termValue = await field(book.term).locator('option', { hasText: TERM }).first().getAttribute('value').catch(() => null);
const classValue = await field(book.class).locator('option', { hasText: CLASS_LABEL }).first().getAttribute('value').catch(() => null);
check(`the schedule offers ${TERM} and the class "${CLASS_LABEL}"`, Boolean(termValue) && Boolean(classValue), `term ${termValue}, class ${classValue}`);
// A week ahead, the school's own date (Indian/Maldives).
const inAWeek = new Intl.DateTimeFormat('en-CA', { timeZone: 'Indian/Maldives' }).format(new Date(Date.now() + 7 * 86400000));
let examId = 0;
if (termValue && classValue) {
    await field(book.term).selectOption(termValue);
    await field(book.class).selectOption(classValue);
    await field(book.name).fill(EXAM);
    await field(book.date).fill(inAWeek);
    await one.getByLabel(book.exams_confirm_calendar, { exact: true }).check();
    await one.getByLabel(book.exams_confirm_same_day, { exact: true }).check();
    await one.getByRole('button', { name: book.exams_schedule, exact: true }).click();
    await dean.waitForLoadState('networkidle');
    const scheduled = (await dean.getByTestId('flash-success').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('the dean schedules an exam and is told so, in Dhivehi', scheduled === book.flash_exam_scheduled && inDhivehi(scheduled), `said: ${scheduled ?? 'nothing'}`);
    examId = Number(tinker(`echo (int) DB::table('exams')->where('name', '${EXAM}')->orderByDesc('id')->value('id');`));
}
check('the exam is on the schedule', examId > 0, `exam ${examId}`);

// ------------------------------------- every screen, in Dhivehi and Arabic

const screens = [
    '/exams/schedule',
    ...(examId > 0 ? [`/exams/${examId}/marks`] : []),
    '/exams/gradebook',
    '/exams/weights',
    '/exams/scales',
    '/exams/types',
];
for (const locale of ['dv', 'ar']) {
    for (const path of screens) {
        const label = `${locale}${path}`;
        const response = await dean.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        if (!response || response.status() >= 400 || new URL(dean.url()).pathname !== `/${locale}${path}`) {
            check(`${label}: the page opens`, false, `HTTP ${response?.status()} ${dean.url().replace(BASE, '')}`);
            continue;
        }
        const found = await readMain(dean);
        const left = english(found.texts, authorsWords(await props(dean)));
        check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
        check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
    }
}

// ------------------------------------- a move the state does not allow, refused in Dhivehi

if (examId > 0) {
    await dean.goto(`${BASE}/dv/exams/schedule`, { waitUntil: 'networkidle' });
    const row = dean.locator('tr', { hasText: EXAM }).first();
    await row.locator('select').selectOption('locked');
    await row.getByRole('button', { name: book.exams_move, exact: true }).click();
    await dean.waitForLoadState('networkidle');
    const said = (await row.locator('span.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    const expected = (book.error_cannot_move || '').replace(':from', book.exam_status_scheduled).replace(':to', book.exam_status_locked);
    const still = tinker(`echo DB::table('exams')->where('id', ${examId})->value('status');`);
    check('moving a scheduled exam straight to locked is refused under its row, both states in Dhivehi; it stays scheduled', said === expected && inDhivehi(said) && still === 'scheduled', `said: ${said ?? 'nothing'}; the exam is ${still}`);
} else {
    check('moving a scheduled exam straight to locked is refused under its row, both states in Dhivehi; it stays scheduled', false, 'no exam to move');
}

// ------------------------------------- the year's scheme saved twice, refused in Dhivehi

const yearId = Number(tinker("echo (int) DB::table('academic_years')->where('status', 'active')->value('id');"));
const schemes = () => Number(tinker(`echo DB::table('assessment_weight_schemes')->where('academic_year_id', ${yearId})->whereNull('class_id')->whereNull('subject_id')->count();`));
await dean.goto(`${BASE}/dv/exams/weights`, { waitUntil: 'networkidle' });
const create = dean.locator('form').filter({ has: dean.locator('h2', { hasText: book.weights_create }) }).first();
const saveScheme = async () => {
    await create.locator('label').filter({ has: dean.locator('span', { hasText: exactly(book.year) }) }).locator('select').selectOption(String(yearId));
    await create.getByRole('button', { name: book.weights_save, exact: true }).click();
    await dean.waitForLoadState('networkidle');
};
if (schemes() === 0) {
    await saveScheme();
    const saved = (await dean.getByTestId('flash-success').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('the year’s first weight scheme is saved, and said in Dhivehi', saved === book.flash_scheme_saved, `said: ${saved ?? 'nothing'}`);
    await dean.goto(`${BASE}/dv/exams/weights`, { waitUntil: 'networkidle' });
}
const before = schemes();
await saveScheme();
const twice = (await create.locator('p.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
const after = schemes();
check('saving the year’s weight scheme a second time is refused above the weights, in Dhivehi; none is added', twice === book.error_scheme_exists && after === before, `said: ${twice ?? 'nothing'}; schemes ${before} → ${after}`);

// ------------------------------------- an exam type code taken, refused in Dhivehi

const typeCount = () => Number(tinker("echo DB::table('exam_types')->count();"));
await dean.goto(`${BASE}/dv/exams/types`, { waitUntil: 'networkidle' });
const typesBefore = typeCount();
const typeForm = dean.locator('form').first();
const typeField = (label) => typeForm.locator('label').filter({ has: dean.locator('span', { hasText: exactly(label) }) }).locator('select, input').first();
await typeField(book.name_en).fill('SMOKE-Final');
await typeField(book.types_code).selectOption('final');
await typeForm.getByRole('button', { name: book.types_create, exact: true }).click();
await dean.waitForLoadState('networkidle');
const taken = (await typeForm.locator('span.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
const typesAfter = typeCount();
check('an exam type with a code the school has is refused beside the code, in Dhivehi; none is made', taken === book.error_type_code_exists && typesAfter === typesBefore, `said: ${taken ?? 'nothing'}; types ${typesBefore} → ${typesAfter}`);

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
