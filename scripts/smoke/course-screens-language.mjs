/**
 * Do the course-building screens read in Dhivehi and Arabic? (BACKLOG C19,
 * slices CT1–CT7a, STATUS §5ok on.)
 *
 * The dean opens every translated course screen under /dv and /ar — the
 * system admin the one the website's course list owns, Deleted courses, and
 * the seeded pupil their own Qur'an page — and the walk lists what is still
 * in Latin letters: every text node in the page's
 * main, and every placeholder, aria-label and title in it. What a screen shows
 * of the data it was sent — a course's title, a question's text, a letter's
 * name, an author's typed label — is the author's, not the screen's, so a
 * string found among the page's props passes. So do CSV, PDF, JSON, HTML,
 * YouTube, Vimeo, addresses and a certificate's {{placeholders}}, and
 * whatever sits in a code or JSON box. Codes the
 * server sends to be named (a pattern, a status, a type) do not count as the
 * author's: printed raw, they fail. Anything left is English the screen wrote
 * itself, and fails the step.
 *
 * Each page must also be right to left, and every field in it must have a
 * name a screen reader can say.
 *
 * The dean also uploads a mushaf through the Dhivehi form (slice CT5b) and is
 * told so in Dhivehi; the walk opens it, its pages and the word-mapping form.
 * It never imports an ayah — with no mushaf active, every mushaf's ayahs are
 * read as the Qur'an, so walk-made text must not exist. `SmokeMarkerSeeder`
 * removes what a run uploaded.
 *
 *   node scripts/smoke/course-screens-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_MARKER, SMOKE_SUPER_ADMIN, SMOKE_STUDENT,
 * SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const DEAN = process.env.SMOKE_MARKER ?? 'headmaster@akuru.edu.mv';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
const PUPIL = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const COURSE = 'SMOKE-Course';

// Props that carry codes the screen must name, and English renderings of
// things that have a name in the page's language. Their values are not the
// author's words, so they do not excuse a string.
const CODE_KEYS = new Set([
    't', 'locale', 'locales', 'locale_urls', 'pattern', 'patterns', 'skills', 'types', 'status', 'workflow_status',
    'question_type', 'difficulty', 'assessment_type', 'unlock_mode', 'value', 'kind', 'mode', 'normalizationModes',
    'normalizationFlags', 'textInputTypes', 'decision', 'type', 'language', 'direction', 'align', 'tone', 'font',
    'completion_rule', 'submission_kind', 'unlockModes', 'decisions', 'label', 'decision_label', 'english_name',
    'q', 'statuses', 'assignment_type', 'overall_statuses', 'lane_results', 'revision_results', 'new_result',
    'recent_revision_result', 'old_revision_result', 'overall_status', 'surah',
]);
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\{\{[a-z_]+\}\}/g, /\bCSV\b/g, /\bPDF\b/g, /\bJSON\b/g, /\bHTML\b/g, /\bYouTube\b/g, /\bVimeo\b/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});

// An English name (`name_en`) is the author's only when the page's language
// has none: a level somebody typed in English alone is shown in English, and
// that is right; a subject with a Dhivehi name shown in English is not.
//
// `own` names the code keys a screen shows on purpose: the Qur'an reference
// has a column of English surah names (slice CT5b).
//
// An activity's type label is the author's when they typed one; left blank,
// `SaveActivityAction` stores the pattern's code there, and that is a code.
// Taken for the author's, it excused a raw "selection" and "teacher_marked"
// on the learner's course page and on the activities screen, and both passed
// (slice CT7a).
function authorsWords(value, locale, own = [], key = '', out = [], owner = {}) {
    if ((CODE_KEYS.has(key) && !own.includes(key)) || (key.endsWith('_en') && owner[key.replace(/_en$/, `_${locale}`)])
        || (key === 'activity_type' && value === owner.pattern)) {
        return out;
    }
    if (typeof value === 'string') {
        if (value.trim().length >= 2) {
            out.push(value.trim());
        }
    } else if (Array.isArray(value)) {
        value.forEach((item) => authorsWords(item, locale, own, key, out));
    } else if (value && typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => authorsWords(v, locale, own, k, out, value));
    }
    return out;
}

function english(strings, allowed) {
    const longestFirst = [...new Set(allowed)].sort((a, b) => b.length - a.length);
    return strings.filter((raw) => {
        // The fixed tokens first: a short prop string ("me", "Tube") taken out
        // of the middle of "YouTube" would leave half a word behind.
        let rest = raw;
        for (const pattern of ALWAYS_FINE) {
            rest = rest.replace(pattern, ' ');
        }
        for (const word of longestFirst) {
            rest = rest.split(word).join(' ');
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

const page = await signIn(DEAN);
// Deleted courses is a screen of the website's course list, which only the
// system admin opens (slice CT4).
const office = await signIn(SUPER);
// The learner's own Qur'an page (slice CT5b).
const pupil = await signIn(PUPIL);

await page.goto(`${BASE}/en/catalog/courses`, { waitUntil: 'networkidle' });
const course = ((await props(page)).rows || []).find((row) => row.title === COURSE);
check('the dean finds SMOKE-Course in the catalog', Boolean(course));

// The halaqa sheet reads an engine session; SmokeMarkerSeeder plants one on
// SMOKE-Offering for it (slice CT5a). Found as a teacher's office would: the
// offering's sessions screen.
await page.goto(`${BASE}/en/catalog/offerings`, { waitUntil: 'networkidle' });
const sessionsHref = await page.locator('tr', { hasText: 'SMOKE-Offering' }).first().locator('a[href*="/sessions"]').first().getAttribute('href').catch(() => null);
let halaqaSession = null;
if (sessionsHref) {
    await page.goto(new URL(sessionsHref, BASE).href, { waitUntil: 'networkidle' });
    halaqaSession = ((await props(page)).sessions || []).find((row) => row.title === 'SMOKE-Halaqa-Sheet');
}
check('the dean finds the planted halaqa session', Boolean(halaqaSession), sessionsHref ?? 'no sessions link for SMOKE-Offering');

// The dean uploads a mushaf in Dhivehi with two page placeholders, and is
// told so in Dhivehi (slice CT5b). No ayah is imported — see the top.
const mushafName = `SMOKE-Mushaf-${Date.now()}`;
await page.goto(`${BASE}/dv/quran/mushafs/create`, { waitUntil: 'networkidle' });
const created = (await props(page)).t?.flash_mushaf_created;
await page.fill('#name', mushafName);
await page.fill('#page_count', '2');
await page.locator('main button[type=submit]').click();
await page.waitForURL(/\/quran\/mushafs\/\d+$/, { timeout: 15000 }).catch(() => {});
const told = await page.getByTestId('flash-success').textContent({ timeout: 10000 }).catch(() => null);
check('the dean uploads a mushaf in Dhivehi and is told so in Dhivehi', Boolean(created) && told?.trim() === created, `said: ${told ?? 'nothing'}`);
// The page's script tag still holds the form's props after a client-side
// visit; a fresh load reads the mushaf's.
await page.goto(page.url(), { waitUntil: 'networkidle' });
const uploaded = await props(page);
const mushafId = uploaded.mushaf?.name === mushafName ? uploaded.mushaf.id : null;
check('the uploaded mushaf opens', Boolean(mushafId), page.url().replace(BASE, ''));
check('with the two pages it was given', uploaded.mushaf?.pages_count === 2, `pages_count=${uploaded.mushaf?.pages_count}`);

// The clubs (slice CT6a): `SmokeMarkerSeeder` plants SMOKE-Club with the
// seeded pupil on its roster.
await page.goto(`${BASE}/en/academics/clubs`, { waitUntil: 'networkidle' });
const club = ((await props(page)).clubs || []).find((row) => row.title === 'SMOKE-Club');
check('the dean finds SMOKE-Club among the clubs', Boolean(club));

const screens = [
    '/catalog/courses',
    `/catalog/courses/${course?.id}/outline`,
    `/catalog/courses/${course?.id}/rubrics`,
    `/catalog/courses/${course?.id}/activities`,
    `/catalog/courses/${course?.id}/assessments`,
    '/catalog/questions',
    '/catalog/glossary',
    '/catalog/certificates',
    '/catalog/reviews',
    '/catalog/reports',
    '/catalog/reports/completions',
    '/catalog/subjects',
    '/catalog/levels',
    '/catalog/audiences',
    ['/admin/public-site/courses/deleted', office],
    '/teach/assignments',
    '/teach/milestones',
    '/teach/recitations',
    `/teach/quran-sessions/${halaqaSession?.id}`,
    // Slice CT5b.
    '/catalog/quran/oversight',
    ['/catalog/quran', page, { own: ['english_name'] }],
    ['/catalog/quran?surah=1', page, { own: ['english_name'] }],
    '/quran/mushafs',
    '/quran/mushafs/create',
    `/quran/mushafs/${mushafId}`,
    // Page 1 with the word-mapping form open; page 2 is the last.
    [`/quran/mushafs/${mushafId}/pages/1`, page, { open: (viewer) => viewer.locator('main button.mb-4').click() }],
    `/quran/mushafs/${mushafId}/pages/2`,
    ['/learn/quran', pupil],
    // Slice CT6a.
    '/catalog/arabic',
    '/catalog/arabic/reports',
    '/catalog/i18n-preview',
    '/academics/clubs',
    `/academics/clubs/${club?.id}`,
    `/academics/clubs/${club?.id}/attendance-sheet`,
    // Slice CT7a: the pupil's own pages.
    ['/learn', pupil],
    ['/learn/catalog', pupil],
    [`/learn/courses/${course?.id}`, pupil],
    ['/learn/schedule', pupil],
    ['/learn/arabic-report', pupil],
    ['/learn/pronounce', pupil],
];

// A step's name, with no record's id in it.
const label = (locale, path) => `${locale}${path.replace(/\/\d+(?=\/|$|\?)/g, '/{id}')}`;

for (const locale of ['dv', 'ar']) {
    for (const entry of screens) {
        const [path, viewer, options = {}] = Array.isArray(entry) ? entry : [entry, page];
        const response = await viewer.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        if (!(await viewer.locator('main').count())) {
            check(`${label(locale, path)}: the screen opens`, false, `HTTP ${response?.status()} ${viewer.url().replace(BASE, '')}`);
            continue;
        }
        if (options.open) {
            await options.open(viewer);
        }
        const found = await viewer.evaluate(() => {
            const main = document.querySelector('main');
            const texts = [];
            const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) {
                const node = walker.currentNode;
                if (node.parentElement?.closest('script, style, textarea, code, pre')) {
                    continue;
                }
                const text = node.textContent.replace(/\s+/g, ' ').trim();
                if (text) {
                    texts.push(text);
                }
            }
            for (const el of main.querySelectorAll('[placeholder], [aria-label], [title]')) {
                for (const name of ['placeholder', 'aria-label', 'title']) {
                    const value = el.getAttribute(name);
                    if (value && !el.closest('code, pre') && !(name === 'placeholder' && el.classList.contains('font-mono'))) {
                        texts.push(value.trim());
                    }
                }
            }
            const unnamed = [...main.querySelectorAll('select, textarea, input:not([type=hidden]):not([type=submit]):not([type=button])')]
                .filter((el) => !(el.labels?.length || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title')))
                .map((el) => el.outerHTML.slice(0, 90));
            return { dir: document.documentElement.getAttribute('dir'), texts, unnamed };
        });
        const left = english(found.texts, authorsWords(await props(viewer), locale, options.own ?? []));
        check(`${label(locale, path)}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label(locale, path)}: nothing in English`, left.length === 0, [...new Set(left)].slice(0, 12).join(' | '));
        check(`${label(locale, path)}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
    }
}

await browser.close();

let ok = 0;
for (const [step, pass, detail] of results) {
    ok += pass ? 1 : 0;
    console.log(`${pass ? 'ok  ' : 'FAIL'}  ${step.padEnd(52)} ${pass ? '' : detail}`);
}
console.log(problems.length ? `\n${problems.join('\n')}` : '\nno console or server errors');
console.log(`\n${ok}/${results.length} steps passed.`);
process.exit(ok === results.length && problems.length === 0 ? 0 : 1);
