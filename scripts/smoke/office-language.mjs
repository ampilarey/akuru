/**
 * Do the office's Academics screens read in Dhivehi and Arabic? (BACKLOG
 * C21: slice OA1, the registers and attendance, STATUS §5qd; slice OA2, the
 * school's structure and time, STATUS §5qf; slice OA3, teaching, STATUS
 * §5qh.)
 *
 * The seeded teacher opens today's registers, one of their registers and
 * daily attendance; the dean opens the unfilled registers, the attendance
 * reports, who is not in today, absence notes, the attendance policy and the
 * absence reasons; and then the academic years, the periods, the classes and
 * a class's roster, the school calendar, the timetable, the promotion
 * wizard, the rooms and their bookings; and the teaching screens — the
 * teacher's materials, plans, behaviour records and meetings, and the office's
 * meetings, noticeboard and pupils' work — under /dv and /ar. The walk lists what is still in Latin
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
 *     and lessons offered with their times;
 *   - the dean closes the school year while a term is open and is refused,
 *     in red, in Dhivehi (it was flashed green, in English, as if it had
 *     worked); nothing closes;
 *   - the dean adds a calendar day on a date that has one and is refused
 *     beside the date, in Dhivehi;
 *   - the dean asks for meeting slots 200 minutes long and is refused beside
 *     the minutes, in Dhivehi; nothing is made.
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
    // OA2: a promotion's outcome, an assessment's kind, the timetable's view
    // and a slot's day.
    'outcome', 'assessment_type', 'view', 'day_of_week',
    // OA3: a notice's priority and audience.
    'priority', 'priorities', 'audiences', 'target_audience',
]);
// Props that are a list of codes when they are a list of strings — a
// calendar day's or a room's types, the timetable's days — and the school's
// own records otherwise: the absence reasons are `types`, the calendar's
// entries `days`.
const CODE_LISTS = new Set(['types', 'days']);
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
        // A page's own stylesheet (the timetable's print rules) is not
        // something a reader meets.
        if (walker.currentNode.parentElement?.closest('style, script')) continue;
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
// A class of the school year on screen, for its roster page.
const rosterClassId = tinker("echo (int) DB::table('classes')->where('academic_year_id', DB::table('academic_years')->where('status', 'active')->value('id'))->orderBy('id')->value('id');");
check('the school year has a class to open', Number(rosterClassId) > 0, `class ${rosterClassId}`);

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
    // OA2.
    ['/academics/years', dean],
    ['/academics/periods', dean],
    ['/academics/classes', dean],
    ...(Number(rosterClassId) > 0 ? [[`/academics/classes/${rosterClassId}`, dean]] : []),
    ['/academics/calendar', dean],
    ['/academics/timetable', dean],
    ['/academics/promotion', dean],
    ['/academics/rooms', dean],
    ['/academics/bookings', dean],
    // OA3.
    ['/academics/materials', teacher],
    ['/academics/plans', teacher],
    ['/academics/behavior', teacher],
    ['/teach/meetings', teacher],
    ['/academics/meetings', dean],
    ['/announcements', dean],
    ['/academics/work', dean],
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

// ------------------------------------- a year that cannot close, refused in red, in Dhivehi

await dean.goto(`${BASE}/dv/academics/years`, { waitUntil: 'networkidle' });
const yearsPage = await props(dean);
const yearsBook = yearsPage.t ?? {};
// Pressed only on a year with a term still open, which the server refuses:
// on any other it would close the school year.
const openYear = (yearsPage.years || []).find((year) => year.status === 'active' && (year.terms || []).some((term) => term.status !== 'closed'));
if (openYear) {
    const card = dean.locator('section').filter({ has: dean.locator('h2', { hasText: openYear.name }) }).first();
    await card.getByRole('button', { name: yearsBook.years_close, exact: true }).click();
    await dean.waitForLoadState('networkidle');
    const said = (await dean.getByTestId('flash-error').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    const green = await dean.getByTestId('flash-success').count();
    const still = ((await props(dean)).years || []).find((year) => year.id === openYear.id)?.status;
    check('closing the school year while a term is open is refused, in red, in Dhivehi', said === yearsBook.error_year_terms_open && green === 0 && still === 'active', `said: ${said ?? 'nothing'}; green notices: ${green}; the year is ${still}`);
} else {
    check('closing the school year while a term is open is refused, in red, in Dhivehi', false, 'no active year with an open term to try — re-seed first: php artisan db:seed --class=SmokeMarkerSeeder');
}

// ------------------------------------- a calendar day on a taken date, refused in Dhivehi

await dean.goto(`${BASE}/dv/academics/calendar`, { waitUntil: 'networkidle' });
const calendarPage = await props(dean);
const calendarBook = calendarPage.t ?? {};
const taken = (calendarPage.days || [])[0]?.date;
if (taken) {
    const dayForm = dean.locator('form').first();
    await dayForm.getByLabel(calendarBook.date, { exact: true }).fill(taken);
    await dayForm.getByLabel(calendarBook.title_en, { exact: true }).fill('SMOKE-Taken');
    await dayForm.getByRole('button', { name: calendarBook.calendar_add, exact: true }).click();
    await dean.waitForLoadState('networkidle');
    const said = (await dayForm.locator('span.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('a calendar day on a date that has one is refused beside the date, in Dhivehi', said === calendarBook.error_calendar_date_taken, `${taken} — said: ${said ?? 'nothing'}`);
} else {
    check('a calendar day on a date that has one is refused beside the date, in Dhivehi', false, 'the calendar has no day to collide with — re-seed first: php artisan db:seed --class=SmokeMarkerSeeder');
}

// ------------------------------------- a slot too long, refused in Dhivehi

await dean.goto(`${BASE}/dv/academics/meetings`, { waitUntil: 'networkidle' });
const meetingsBook = (await props(dean)).t ?? {};
const slotForm = dean.locator('form').first();
const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
await slotForm.getByLabel(meetingsBook.date, { exact: true }).fill(tomorrow);
await slotForm.getByLabel(meetingsBook.meetings_minutes, { exact: true }).fill('200');
const slotsBefore = ((await props(dean)).slots || []).length;
await slotForm.getByRole('button', { name: meetingsBook.meetings_generate, exact: true }).click();
await dean.waitForLoadState('networkidle');
const tooLong = (await slotForm.locator('span.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
// The page's first props are not redrawn by a visit: count again from a
// fresh load.
await dean.goto(`${BASE}/dv/academics/meetings`, { waitUntil: 'networkidle' });
const slotsAfter = ((await props(dean)).slots || []).length;
check('meeting slots 200 minutes long are refused beside the minutes, in Dhivehi, and none is made', tooLong === meetingsBook.error_slot_length && slotsAfter === slotsBefore, `said: ${tooLong ?? 'nothing'}; slots ${slotsBefore} → ${slotsAfter}`);

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
