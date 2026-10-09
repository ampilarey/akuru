/**
 * Do the course-building screens read in Dhivehi and Arabic? (BACKLOG C19,
 * slices CT1–CT7b, STATUS §5ok on.)
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
 * The pupil hands in a typed activity and a marked assessment from the
 * Dhivehi pages and is told so in Dhivehi (slice CT7b); the walk then opens
 * an activity of every pattern the seeder plants, both assessments — one left
 * in progress against its clock, one marked with its answers shown — and the
 * lesson, with its glossary term opened.
 *
 * The dean adds a session to SMOKE-Offering from the Dhivehi page and is told
 * so in Dhivehi, then opens the offerings, the offering's sessions and the
 * halaqa session's attendance (slice CT8); the pupil opens their performance
 * page. Before the session is filled in, it is saved empty and refused in
 * Dhivehi; a term saved empty from the Arabic glossary is refused in Arabic
 * (slice CT6b-1). The pupil types a code that does not exist against a priced
 * course on the Dhivehi catalog and is told why in Dhivehi (slice CT6b-2a).
 * The dean publishes an empty module from the Dhivehi outline and is told why
 * beside the module, in Dhivehi, then deletes it; and is refused a certificate
 * the pupil has not earned on the Arabic page, the reason in Arabic (slice
 * CT6b-2b). `SmokeMarkerSeeder` removes the module and the template. A
 * second SMOKE-Offering, from the Dhivehi offerings page, is refused in
 * Dhivehi (slice CT6b-2c). The system admin opens the pronunciation AI admin
 * and the review queue, and a model version registered empty from the
 * Dhivehi admin is refused in Dhivehi (STATUS §5pr).
 *
 *   node scripts/smoke/course-screens-language.mjs
 *
 * The halaqa sessions list (STATUS §5pu): the dean finds `SMOKE-Hifz-Session`
 * there and opens its sheet; the seeded teacher, who teaches it, finds it in
 * their own list and their menu.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_MARKER, SMOKE_SUPER_ADMIN, SMOKE_STUDENT,
 * SMOKE_TEACHER, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const DEAN = process.env.SMOKE_MARKER ?? 'headmaster@akuru.edu.mv';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
const PUPIL = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
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
    // Slice CT8: an offering's, a session's and an attendance mark's codes, and
    // whose record the performance page shows.
    'modes', 'delivery_mode', 'session_type', 'pin_mode', 'old_pin_mode', 'new_pin_mode', 'attendance_mode', 'relationship',
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
//
// `label` is a code's English name almost everywhere it is sent — a submission
// kind's, a decision's. An option, an item or a target is the exception: a
// bare `{id, label}` the author wrote, and its label is theirs (slice CT7b).
function authorsWords(value, locale, own = [], key = '', out = [], owner = {}) {
    const authorsLabel = key === 'label' && 'id' in owner && Object.keys(owner).every((k) => k === 'id' || k === 'label');
    if ((CODE_KEYS.has(key) && !own.includes(key) && !authorsLabel) || (key.endsWith('_en') && owner[key.replace(/_en$/, `_${locale}`)])
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

// The flash a save leaves, once it reads `expected` — or what it read when the
// wait ran out. A second save on the same page leaves its own text, so the wait
// is on the words, not on the element.
async function flashReads(viewer, expected) {
    await viewer.waitForFunction(
        (want) => document.querySelector('[data-testid="flash-success"]')?.textContent?.trim() === want,
        expected,
        { timeout: 10000 },
    ).catch(() => {});
    return (await viewer.getByTestId('flash-success').textContent({ timeout: 1000 }).catch(() => null))?.trim() ?? null;
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

// A third page, added after the upload from the Dhivehi mushaf page, and a
// page image from the Dhivehi page view (STATUS §5pt). Neither had a door. A
// count below the two it has is refused first: pages are never taken away.
// The mushaf is never approved, locked or given an ayah.
await page.goto(`${BASE}/dv/quran/mushafs/${mushafId}`, { waitUntil: 'networkidle' });
const mushafT = (await props(page)).t ?? {};
const pagesForm = page.getByTestId('mushaf-pages');
await pagesForm.getByLabel(mushafT.mushaf_pages_count, { exact: true }).fill('1');
await pagesForm.getByRole('button', { name: mushafT.mushaf_pages_add, exact: true }).click();
const fewer = (await pagesForm.locator('ul[role="alert"]').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'fewer pages than the mushaf has are refused on the Dhivehi page, in Dhivehi',
    Boolean(mushafT.error_mushaf_pages_fewer) && fewer === mushafT.error_mushaf_pages_fewer.replace(':count', '2'),
    `said: ${fewer ?? 'nothing'}`,
);
await pagesForm.getByLabel(mushafT.mushaf_pages_count, { exact: true }).fill('3');
await pagesForm.getByRole('button', { name: mushafT.mushaf_pages_add, exact: true }).click();
const pagesAdded = (mushafT.flash_mushaf_pages_added ?? '').replace(':count', '3');
const pagesTold = await flashReads(page, pagesAdded);
check('the dean adds a page in Dhivehi and is told so in Dhivehi', Boolean(mushafT.flash_mushaf_pages_added) && pagesTold === pagesAdded, `said: ${pagesTold ?? 'nothing'}`);
await page.goto(page.url(), { waitUntil: 'networkidle' });
check('and the mushaf has three pages', (await props(page)).mushaf?.pages_count === 3, `pages_count=${(await props(page)).mushaf?.pages_count}`);

// A one-pixel PNG is an image as far as the rule and getimagesize go.
const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
await page.goto(`${BASE}/dv/quran/mushafs/${mushafId}/pages/1`, { waitUntil: 'networkidle' });
const pageT = (await props(page)).t ?? {};
const imageForm = page.getByTestId('qpage-image');
await imageForm.getByLabel(pageT.qpage_image_label, { exact: true }).setInputFiles({ name: 'page-1.png', mimeType: 'image/png', buffer: Buffer.from(ONE_PIXEL_PNG, 'base64') });
await imageForm.getByRole('button', { name: pageT.qpage_image_upload, exact: true }).click();
const imageTold = await flashReads(page, pageT.flash_qpage_image_saved);
check('the dean uploads a page image in Dhivehi and is told so in Dhivehi', Boolean(pageT.flash_qpage_image_saved) && imageTold === pageT.flash_qpage_image_saved, `said: ${imageTold ?? 'nothing'}`);
await page.goto(page.url(), { waitUntil: 'networkidle' });
const pageImage = page.locator('main img[src*="/storage/quran/pages/"]');
await pageImage.first().waitFor({ state: 'attached', timeout: 10000 }).catch(() => {});
const loaded = (await pageImage.count()) === 1
    && await pageImage.first().evaluate((img) => img.complete && img.naturalWidth > 0).catch(() => false);
check('and the page shows it', loaded, `${await pageImage.count()} image(s)`);

// The clubs (slice CT6a): `SmokeMarkerSeeder` plants SMOKE-Club with the
// seeded pupil on its roster.
await page.goto(`${BASE}/en/academics/clubs`, { waitUntil: 'networkidle' });
const club = ((await props(page)).clubs || []).find((row) => row.title === 'SMOKE-Club');
check('the dean finds SMOKE-Club among the clubs', Boolean(club));

// The halaqa sheet's door (STATUS §5pu). `SmokeMarkerSeeder` plants
// SMOKE-Hifz-Session on a hifz course, taught by the seeded teacher. The dean
// runs the courses, so sees every halaqa session; the teacher sees their own.
await page.goto(`${BASE}/en/teach/quran-sessions`, { waitUntil: 'networkidle' });
const halaqaRow = ((await props(page)).sessions || []).find((row) => row.title === 'SMOKE-Hifz-Session');
check('the dean finds the planted halaqa session in the halaqa sessions list', Boolean(halaqaRow));
await page.getByTestId(`quran-session-${halaqaRow?.id}`).getByRole('link').click();
await page.waitForURL(/\/teach\/quran-sessions\/\d+$/, { timeout: 10000 }).catch(() => {});
// The script tag keeps the list's props after a client-side visit; a fresh
// load reads the sheet's.
await page.goto(page.url(), { waitUntil: 'networkidle' });
check('and its link opens the session\'s sheet', page.url().endsWith(`/teach/quran-sessions/${halaqaRow?.id}`) && (await props(page)).session?.id === halaqaRow?.id, page.url().replace(BASE, ''));
const teacher = await signIn(TEACHER);
await teacher.goto(`${BASE}/en/teach/quran-sessions`, { waitUntil: 'networkidle' });
const teacherProps = await props(teacher);
check(
    'the teacher finds the session they teach in their own list',
    teacherProps.scope === 'mine' && (teacherProps.sessions || []).some((row) => row.title === 'SMOKE-Hifz-Session'),
    `scope=${teacherProps.scope}, ${(teacherProps.sessions || []).length} session(s)`,
);
const teacherDoors = (teacherProps.nav?.groups || []).flatMap((group) => group.items || []).map((item) => item.href || '');
check('and the teacher\'s menu offers the list', teacherDoors.some((href) => href.endsWith('/teach/quran-sessions')), `${teacherDoors.length} doors`);

// The pupil's activities, assessments and lesson on SMOKE-Course (slice CT7b),
// found the way the pupil finds them: on the course page.
await pupil.goto(`${BASE}/en/learn/courses/${course?.id}`, { waitUntil: 'networkidle' });
const learnerCourse = await props(pupil);
const activityId = (title) => (learnerCourse.activities || []).find((row) => row.title === title)?.id;
const assessmentId = (title) => (learnerCourse.assessments || []).find((row) => row.title === title)?.id;
const lessonId = (learnerCourse.modules || []).flatMap((row) => row.lessons || []).find((row) => row.title === 'SMOKE-Lesson')?.id;
const pupilActivities = ['SMOKE-Activity', 'SMOKE-Review-Activity', 'SMOKE-Lang-Order', 'SMOKE-Lang-Type', 'SMOKE-Lang-Upload'].map(activityId);
check('the pupil finds the seeded activities, both assessments and the lesson', pupilActivities.every(Boolean) && assessmentId('SMOKE-Lang-Timed') && assessmentId('SMOKE-Lang-Marked') && lessonId,
    JSON.stringify({ activities: pupilActivities, timed: assessmentId('SMOKE-Lang-Timed'), marked: assessmentId('SMOKE-Lang-Marked'), lesson: lessonId }));

// The typed activity, handed in from the Dhivehi page. A run before this one
// left it marked, so a second go is taken first.
await pupil.goto(`${BASE}/dv/learn/activities/${activityId('SMOKE-Lang-Type')}`, { waitUntil: 'networkidle' });
const learn = (await props(pupil)).i18n?.learn ?? {};
const again = pupil.getByRole('button', { name: learn.try_again });
if (await again.count()) {
    await again.click();
}
await pupil.getByLabel(learn.your_answer).fill('SMOKE-Answer');
await pupil.getByRole('button', { name: learn.submit, exact: true }).click();
const activityTold = await flashReads(pupil, learn.flash_activity_submitted);
check('the pupil hands in an activity in Dhivehi and is told so in Dhivehi', Boolean(learn.flash_activity_submitted) && activityTold === learn.flash_activity_submitted, `said: ${activityTold ?? 'nothing'}`);

// The marked assessment, the same way: a second go if one was taken already,
// and handed in as it stands — its questions are not required.
await pupil.goto(`${BASE}/dv/learn/assessments/${assessmentId('SMOKE-Lang-Marked')}`, { waitUntil: 'networkidle' });
const sitAgain = pupil.getByRole('button', { name: learn.try_again });
if (await sitAgain.count()) {
    await sitAgain.click();
    await flashReads(pupil, learn.flash_started_again);
}
await pupil.getByRole('button', { name: learn.submit, exact: true }).click();
const assessmentTold = await flashReads(pupil, learn.flash_assessment_submitted);
check('the pupil hands in an assessment in Dhivehi and is told so in Dhivehi', Boolean(learn.flash_assessment_submitted) && assessmentTold === learn.flash_assessment_submitted, `said: ${assessmentTold ?? 'nothing'}`);

// A code that does not exist, typed on the Dhivehi catalog against the priced
// SMOKE-Wallet-Course (slice CT6b-2a). The code is checked before anything is
// made, so nothing is bought or held. The catalog read no errors before: the
// button did nothing, and the reason was English.
await pupil.goto(`${BASE}/dv/learn/catalog`, { waitUntil: 'networkidle' });
const walletRow = pupil.locator('tr', { hasText: 'SMOKE-Wallet-Course' }).first();
await walletRow.getByLabel(learn.discount_code, { exact: true }).fill('SMOKE-NO-SUCH-CODE');
await walletRow.locator('button.btn-primary').first().click();
const codeRefusal = (await pupil.locator('main ul[role="alert"]').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'a discount code refused on the Dhivehi catalog says why in Dhivehi',
    Boolean(codeRefusal) && /\p{Script=Thaana}/u.test(codeRefusal) && !/[A-Za-z]{3,}/.test(codeRefusal),
    `said: ${codeRefusal ?? 'nothing'}`,
);

// A session added to SMOKE-Offering from the Dhivehi page (slice CT8).
// Nobody is enrolled on SMOKE-Offering, so its attendance sheet has no one to
// mark; `SmokeMarkerSeeder` clears the offering's sessions on every run.
const offeringId = sessionsHref?.match(/offerings\/(\d+)/)?.[1];
const attendancePath = `/catalog/offerings/${offeringId}/sessions/${halaqaSession?.id}/attendance`;
await page.goto(`${BASE}/dv/catalog/offerings/${offeringId}/sessions`, { waitUntil: 'networkidle' });
const teach = (await props(page)).t ?? {};
// Saved empty first: the refusal is Laravel's own sentence, in Dhivehi, with
// the fields named as the form labels them (slice CT6b-1).
await page.getByRole('button', { name: teach.sessions_save, exact: true }).click();
const refusal = (await page.locator('form ul[role="alert"]').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'a session refused on the Dhivehi page says why in Dhivehi',
    Boolean(refusal) && refusal.includes('ނަން ބޭނުންވޭ.') && refusal.includes('ފަށާ ވަގުތު ބޭނުންވޭ.') && !/[A-Za-z]{2,}/.test(refusal),
    `said: ${refusal ?? 'nothing'}`,
);
await page.getByLabel(teach.sessions_session_title, { exact: true }).fill('SMOKE-Lang-Session');
await page.getByLabel(teach.sessions_starts, { exact: true }).fill('2030-01-01T09:00');
await page.getByRole('button', { name: teach.sessions_save, exact: true }).click();
const sessionTold = await flashReads(page, teach.flash_session_saved);
check('the dean adds a session in Dhivehi and is told so in Dhivehi', Boolean(teach.flash_session_saved) && sessionTold === teach.flash_session_saved, `said: ${sessionTold ?? 'nothing'}`);

// A term saved empty from the Arabic glossary is refused in Arabic (CT6b-1).
await page.goto(`${BASE}/ar/catalog/glossary`, { waitUntil: 'networkidle' });
const glossaryT = (await props(page)).t ?? {};
await page.getByRole('button', { name: glossaryT.glossary_save, exact: true }).click();
const termRefusal = (await page.locator('form p.text-red-600').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check('a term refused on the Arabic page says why in Arabic', termRefusal === 'حقل المصطلح مطلوب.', `said: ${termRefusal ?? 'nothing'}`);

// An empty module published from the Dhivehi outline (slice CT6b-2b). It was
// the one outline refusal ever shown — once, above the list, in English; a
// refused reorder, unlock rule or block order said nothing. It is said now
// beside the module whose button was pressed, in Dhivehi. Publishing a module
// is `courses.publish`, which the dean does not hold — the outline no longer
// offers the dean the button, which used to answer a bare "Forbidden" — so the
// system admin presses it. The module is deleted again; `SmokeMarkerSeeder`
// removes what a broken run left.
const EMPTY_MODULE = 'SMOKE-Lang-Module';
await office.goto(`${BASE}/dv/catalog/courses/${course?.id}/outline`, { waitUntil: 'networkidle' });
const outlineT = (await props(office)).t ?? {};
await office.getByLabel(outlineT.outline_module_title, { exact: true }).fill(EMPTY_MODULE);
await office.getByRole('button', { name: outlineT.outline_save_module, exact: true }).click();
await flashReads(office, outlineT.flash_module_saved);
const emptyModule = office.locator('section', { hasText: EMPTY_MODULE }).first();
await emptyModule.getByRole('button', { name: (outlineT.outline_publish_aria ?? '').replace(':title', EMPTY_MODULE), exact: true }).click();
const moduleRefusal = (await emptyModule.locator('ul[role="alert"]').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'an empty module published from the Dhivehi outline is refused beside it, in Dhivehi',
    Boolean(outlineT.error_module_empty_publish) && moduleRefusal === outlineT.error_module_empty_publish,
    `said: ${moduleRefusal ?? 'nothing'}`,
);
office.once('dialog', (dialog) => dialog.accept());
await emptyModule.getByRole('button', { name: outlineT.outline_delete_module, exact: true }).click();
await flashReads(office, outlineT.flash_module_deleted);
check('and the empty module is deleted again', (await office.locator('section', { hasText: EMPTY_MODULE }).count()) === 0);
await page.goto(`${BASE}/dv/catalog/courses/${course?.id}/outline`, { waitUntil: 'networkidle' });
const deanModule = ((await props(page)).modules || [])[0];
const publishOffered = deanModule
    ? await page.getByRole('button', { name: (outlineT.outline_publish_aria ?? '').replace(':title', deanModule.title), exact: true }).count()
        + await page.getByRole('button', { name: (outlineT.outline_unpublish_aria ?? '').replace(':title', deanModule.title), exact: true }).count()
    : -1;
check('the dean, who may not publish, is offered no module Publish to be refused', publishOffered === 0, `${deanModule?.title ?? 'no module'}: ${publishOffered}`);

// A certificate the pupil has not earned, issued from the Arabic page (slice
// CT6b-2b). `SMOKE-Lang-Cert` asks for the teacher's approval and belongs to
// no course, so no course page shows it; the box is left unticked. The reason
// was English.
await pupil.goto(`${BASE}/en/portal/performance`, { waitUntil: 'networkidle' });
const pupilName = ((await pupil.locator('main h2').first().textContent().catch(() => '')) ?? '').trim();
await page.goto(`${BASE}/ar/catalog/certificates`, { waitUntil: 'networkidle' });
const certT = (await props(page)).t ?? {};
const templateForm = page.locator('form', { hasText: certT.cert_new_template }).first();
await templateForm.getByLabel(certT.cert_name_en, { exact: true }).fill('SMOKE-Lang-Cert');
await templateForm.getByLabel(certT.cert_require_approval, { exact: true }).check();
await templateForm.getByRole('button', { name: certT.cert_save_template, exact: true }).click();
await flashReads(page, certT.flash_template_saved);
const issueForm = page.locator('form', { hasText: certT.cert_issue_title }).first();
await issueForm.getByLabel(certT.cert_template, { exact: true }).selectOption({ label: 'SMOKE-Lang-Cert' });
const pupilOption = await issueForm.getByLabel(certT.cert_student, { exact: true }).locator('option', { hasText: pupilName }).first().getAttribute('value').catch(() => null);
if (pupilOption) {
    await issueForm.getByLabel(certT.cert_student, { exact: true }).selectOption(pupilOption);
}
await issueForm.getByRole('button', { name: certT.cert_issue, exact: true }).click();
const certRefusal = (await page.getByTestId('cert-issue-refusal').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'a certificate the pupil has not earned is refused on the Arabic page, the reason in Arabic',
    Boolean(pupilOption) && Boolean(certRefusal) && certRefusal.includes('تلزم موافقة المعلم.') && !/[A-Za-z]{2,}/.test(certRefusal),
    `${pupilName || 'no pupil name'} — said: ${certRefusal ?? 'nothing'}`,
);

// A second SMOKE-Offering on SMOKE-Course, from the Dhivehi offerings page
// (slice CT6b-2c). The slug is made from the title, so it collides; the form
// showed its status and certificate-rule refusals only, and this one said
// nothing. Nothing is made.
await page.goto(`${BASE}/dv/catalog/offerings`, { waitUntil: 'networkidle' });
const offeringsT = (await props(page)).t ?? {};
const offeringsBefore = ((await props(page)).rows || []).length;
const offeringForm = page.locator('main form').first();
await offeringForm.getByLabel(offeringsT.offerings_course, { exact: true }).selectOption({ label: COURSE });
await offeringForm.getByLabel(offeringsT.offerings_offering_title, { exact: true }).fill('SMOKE-Offering');
await offeringForm.getByRole('button', { name: offeringsT.offerings_save, exact: true }).click();
const offeringRefusal = (await offeringForm.locator('ul[role="alert"]').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'a second SMOKE-Offering is refused on the Dhivehi page, and says why in Dhivehi',
    Boolean(offeringsT.error_offering_slug) && offeringRefusal === offeringsT.error_offering_slug,
    `said: ${offeringRefusal ?? 'nothing'}`,
);
await page.goto(`${BASE}/dv/catalog/offerings`, { waitUntil: 'networkidle' });
check('and no offering is made', ((await props(page)).rows || []).length === offeringsBefore, `${offeringsBefore} → ${((await props(page)).rows || []).length}`);

// A model version registered empty from the Dhivehi pronunciation admin
// (STATUS §5pr). Laravel's refusal, the two fields named as the form labels
// them; nothing is registered. The screen, the review queue and their messages
// were English in every language.
await office.goto(`${BASE}/dv/admin/pronunciation`, { waitUntil: 'networkidle' });
const pronT = (await props(office)).t ?? {};
const versionsBefore = ((await props(office)).model_versions || []).length;
await office.getByRole('button', { name: pronT.pron_register_version, exact: true }).click();
const versionRefusal = (await office.locator('main form ul[role="alert"]').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check(
    'a model version registered empty is refused on the Dhivehi page, in Dhivehi',
    Boolean(versionRefusal) && versionRefusal.includes('ވާޝަންގެ ނަން ބޭނުންވޭ.') && versionRefusal.includes('މޮޑެލްގެ ފައިލުގެ މަގު ބޭނުންވޭ.') && !/[A-Za-z]{2,}/.test(versionRefusal),
    `said: ${versionRefusal ?? 'nothing'}`,
);
await office.goto(`${BASE}/dv/admin/pronunciation`, { waitUntil: 'networkidle' });
check('and no model version is registered', ((await props(office)).model_versions || []).length === versionsBefore, `${versionsBefore} → ${((await props(office)).model_versions || []).length}`);

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
    // Page 1, with its image and the word-mapping form open; page 3, added
    // after the upload, is the last (STATUS §5pt).
    [`/quran/mushafs/${mushafId}/pages/1`, page, { open: (viewer) => viewer.locator('main button.mb-4').click() }],
    `/quran/mushafs/${mushafId}/pages/3`,
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
    // Slice CT7b: an activity of every planted pattern, the assessment left in
    // progress and the marked one, and the lesson with its term's definition
    // open.
    ...pupilActivities.map((id) => [`/learn/activities/${id}`, pupil]),
    [`/learn/assessments/${assessmentId('SMOKE-Lang-Timed')}`, pupil],
    [`/learn/assessments/${assessmentId('SMOKE-Lang-Marked')}`, pupil],
    [`/learn/lessons/${lessonId}`, pupil, { open: (viewer) => viewer.locator('main button.glossary-term').first().click() }],
    // Slice CT8.
    '/catalog/offerings',
    `/catalog/offerings/${offeringId}/sessions`,
    attendancePath,
    ['/portal/performance', pupil],
    // The office's and the teacher's pronunciation screens (STATUS §5pr). The
    // AI admin is the system admin's; the review queue lets them in too.
    ['/admin/pronunciation', office],
    ['/teach/pronunciation', office],
    // The halaqa sessions list, as the dean and as the teacher (STATUS §5pu).
    '/teach/quran-sessions',
    ['/teach/quran-sessions', teacher],
];

// A step's name, with no record's id in it.
const label = (locale, path) => `${locale}${path.replace(/\/\d+(?=\/|$|\?)/g, '/{id}')}`;

// The learn book's first Dhivehi batch was a few words pasted across rows:
// "to do" for *You are not enrolled yet…*, "profile" for *preview*, "electronic
// education" for *Course* (slice LT1). A label that is nothing but one of them
// is a placeholder, not a translation; inside a sentence they are ordinary
// words, so only a whole text counts.
const PASTED = new Set(['ކުރަން', 'އެންގުޅުން', 'ތައްލިމް', 'އެލެކްޓްރޮނިކް ތައްލީމް', 'ކުރާން ފަސޭހަ', 'ބޭރުވުން', 'ލަނޑު', 'ފިރިހެނުން', 'ޕްރޮފައިލް']);

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
        if (locale === 'dv') {
            const pasted = found.texts.filter((text) => PASTED.has(text.replace(/[.:]$/, '')));
            check(`${label(locale, path)}: no pasted placeholder for a phrase`, pasted.length === 0, [...new Set(pasted)].join(' | '));
        }
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
