/**
 * Do the office's registers and attendance screens read in Dhivehi and
 * Arabic? (BACKLOG C21, slice OA1, STATUS §5qd.)
 *
 * The seeded teacher opens today's registers, one of their registers and
 * daily attendance; the dean opens the unfilled registers, the attendance
 * reports, who is not in today, absence notes, the attendance policy and the
 * absence reasons — under /dv and /ar. The walk lists what is still in Latin
 * letters in each page's main: every text node, placeholder, aria-label,
 * title and phone caption (`data-label`). What a page shows of its data (a
 * pupil's name, a subject, a reason the office typed, the code it was given)
 * is the author's, so a string found among the page's props passes; codes
 * the server sends to be named (a register's or a mark's state, how a mark
 * was recorded, a note's state and its reason) do not, and printed raw they
 * fail. So do the phrase books the page is sent. Each page must be right to
 * left, every field in it named, and it must open where it was asked for.
 *
 * Then, in Dhivehi:
 *   - the teacher's day says why it is empty, or lists its registers, in
 *     Dhivehi;
 *   - the dean presses Generate on the unfilled list and is told what it did
 *     in Dhivehi;
 *   - the dean adds a reason the school already has ("Illness") and is
 *     refused beside its box, in Dhivehi;
 *   - a family writing an absence note chooses among reasons named in
 *     Dhivehi (the five a school starts with gained Dhivehi and Arabic names),
 *     and lessons offered with their times.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/office-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_TEACHER, SMOKE_DEAN, SMOKE_PARENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const DEAN = process.env.SMOKE_DEAN ?? 'headmaster@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Props that carry codes the page must name, and the phrase books it is sent:
// their values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'csvUrl', 'href',
    'status', 'statuses', 'attendanceStatuses', 'source', 'type', 'note_status', 'mode', 'attendanceMode', 'notify',
]);
// The names of formats, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bPDF\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

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

const teacher = await signIn(TEACHER);
const dean = await signIn(DEAN);
const parent = await signIn(PARENT);

// One of the teacher's own registers, and a class to mark the day for.
const registerId = tinker("$u = DB::table('users')->where('email', '" + TEACHER + "')->value('id'); $t = DB::table('teachers')->where('user_id', $u)->value('id'); echo (int) DB::table('lesson_logs')->where('teacher_id', $t)->orderByDesc('date')->value('id');");
const classId = tinker("$u = DB::table('users')->where('email', '" + TEACHER + "')->value('id'); $t = DB::table('teachers')->where('user_id', $u)->value('id'); echo (int) DB::table('lesson_logs')->where('teacher_id', $t)->orderByDesc('date')->value('classroom_id');");
check('the teacher has a register to open', Number(registerId) > 0, `register ${registerId}`);

const screens = [
    ['/academics/registers/today', teacher],
    ...(Number(registerId) > 0 ? [[`/academics/registers/${registerId}`, teacher]] : []),
    ['/academics/attendance/daily', teacher, Number(classId) > 0 ? `?class_id=${classId}` : ''],
    ['/academics/registers', dean],
    ['/academics/attendance', dean],
    ['/academics/attendance/absences', dean],
    ['/academics/absence-notes', dean],
    ['/academics/attendance-policy', dean],
    ['/academics/absence-types', dean],
];

for (const locale of ['dv', 'ar']) {
    for (const [path, viewer, query = ''] of screens) {
        const who = new Map([[teacher, 'the teacher'], [dean, 'the dean']]).get(viewer);
        const label = `${locale}${path}${query} (${who})`;
        const response = await viewer.goto(`${BASE}/${locale}${path}${query}`, { waitUntil: 'networkidle' });
        // A page that sends its viewer elsewhere was not the page checked.
        if (!response || response.status() >= 400 || new URL(viewer.url()).pathname !== `/${locale}${path}`) {
            check(`${label}: the page opens`, false, `HTTP ${response?.status()} ${viewer.url().replace(BASE, '')}`);
            continue;
        }
        const found = await readMain(viewer);
        const left = english(found.texts, authorsWords(await props(viewer)));
        check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
        check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
    }
}

const inDhivehi = (said) => Boolean(said) && /\p{Script=Thaana}/u.test(said) && !/[A-Za-z]{3,}/.test(said);

// ------------------------------------- the teacher's day, in Dhivehi

await teacher.goto(`${BASE}/dv/academics/registers/today`, { waitUntil: 'networkidle' });
const day = await props(teacher);
check('the teacher’s day says why it is empty, or lists its registers, in Dhivehi',
    (day.registers || []).length > 0 ? /\p{Script=Thaana}/u.test(day.t?.today_title || '') : inDhivehi(day.empty?.message),
    (day.registers || []).length > 0 ? `${day.registers.length} register(s)` : `said: ${day.empty?.message ?? 'nothing'}`);

// ------------------------------------- generating, said in Dhivehi

await dean.goto(`${BASE}/dv/academics/registers`, { waitUntil: 'networkidle' });
const unfilled = (await props(dean)).t ?? {};
await dean.getByRole('button', { name: unfilled.unfilled_generate, exact: true }).click();
await dean.waitForLoadState('networkidle');
const generated = (await dean.getByTestId('flash-success').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check('the dean generates expected registers and is told what it did, in Dhivehi', inDhivehi(generated), `said: ${generated ?? 'nothing'}`);

// ------------------------------------- a reason the school already has, refused in Dhivehi

await dean.goto(`${BASE}/dv/academics/absence-types`, { waitUntil: 'networkidle' });
const reasons = (await props(dean)).t ?? {};
const addForm = dean.locator('form').last();
await addForm.getByLabel(reasons.types_name, { exact: true }).fill('Illness');
await addForm.getByRole('button', { name: reasons.add, exact: true }).click();
await dean.waitForLoadState('networkidle');
const refused = (await addForm.locator('p.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check('a reason the school already has is refused beside its box, in Dhivehi', refused === reasons.error_type_code_exists, `said: ${refused ?? 'nothing'}`);

// ------------------------------------- the family's reasons, in Dhivehi

await parent.goto(`${BASE}/dv/portal/absence-notes`, { waitUntil: 'networkidle' });
// Every option on the form: the reason list's label also holds the chosen
// reason, so it is not found by its label's words alone.
const options = await parent.locator('main form select option').allTextContents().catch(() => []);
check('a family chooses among reasons named in Dhivehi', options.includes('ބަލިވުން') && !options.includes('Illness'), options.join(' | ') || 'no reason list');
// A lesson is offered with its times. They are cast to dates, and the form
// read "Period 1 (2026-–2026-)" — the first five characters of one.
const lessons = options.filter((option) => /\(.*\)$/.test(option));
check('and its lessons are offered with their times', lessons.length > 0 && lessons.every((option) => /\(\d{2}:\d{2}–\d{2}:\d{2}\)$/.test(option)), lessons.slice(0, 3).join(' | ') || 'no lessons');

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
