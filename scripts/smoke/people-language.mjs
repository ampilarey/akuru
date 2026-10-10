/**
 * Do the office's people screens read in Dhivehi and Arabic?
 * (BACKLOG C21, slices PE1 and PE2, STATUS §5qp and §5qq.)
 *
 * The office opens the students list and the walks' pupil's profile on each
 * of its eight tabs under /dv and /ar; the staff profiles and a profile; the
 * custom fields for each kind of record and the admission form preview; and,
 * signed in as the system admin (the only role the seed lets read them), the
 * sensitive records with and without a pupil chosen. The walk lists what is still in Latin
 * letters in each page's main: every text node, placeholder, aria-label,
 * title and phone caption (`data-label`). What a page shows of its data (a
 * pupil's or a guardian's name, a class's, a note, a description, a category
 * the school typed) is the author's, so a string found among the page's
 * props passes; codes the server sends to be named (a pupil's status, a
 * guardian's relationship, a link's consent and verification, a consent's
 * type and source, a behaviour record's type, the reason the system wrote in
 * a status history) do not, and printed raw they fail. So do the phrase
 * books the page is sent. Each page must be right to left, every field in it
 * named, and it must open where it was asked for.
 *
 * Then, in Dhivehi:
 *   - the office adds a student with no first name, and is refused under the
 *     form, the field named in Dhivehi; no student is made;
 *   - the list read for a class: the address carries the class, and every
 *     row names it with its section (the list printed the class alone);
 *   - on the pupil's Guardians tab, no guardian already linked is offered to
 *     attach — every guardian on file was, and attaching one again was a
 *     500 page;
 *   - a guardian's record saved with a verification the server does not
 *     know (a stale page) is refused under its row in Dhivehi — a refused
 *     Save was said nowhere — and the link is unchanged;
 *   - an emergency contact whose relationship runs past 255 letters is
 *     refused under the form in Dhivehi — only a name's and a phone's
 *     refusals were said — and no contact is made;
 *   - the photo consent the pupil already has is granted again: the page
 *     says nothing changed (it said "recorded"), and no row is added;
 *   - the status history of the pupil `middle-name.mjs` added from the
 *     directory names the reason the directory wrote;
 *   - a staff profile with no first name is refused under the form, the
 *     field named in Dhivehi; no account and no profile are made;
 *   - a staff status the server does not know (a stale page) is refused
 *     under the employment form, named in Dhivehi; the status is unchanged;
 *   - a qualification with no title is refused under its form, named in
 *     Dhivehi; one with a title is added and removed, each said in Dhivehi;
 *   - a custom field with no English label is refused beside the field,
 *     named in Dhivehi, and none is made; with one it is made, said in
 *     Dhivehi, and the list names it by its Dhivehi label, its type and its
 *     settings in Dhivehi; the admission form preview shows it by its
 *     Dhivehi label and, under /ar, its Arabic one; archived, it leaves the
 *     list, said in Dhivehi;
 *   - a sensitive note with no summary is refused under the line, in
 *     Dhivehi, and none is made; one is recorded, said in Dhivehi, its
 *     category named; archived meanwhile from another page, its Archive is
 *     refused under it in Dhivehi — a refused Archive was said nowhere — and
 *     it stays archived as it was; the page then reads in Dhivehi and Arabic
 *     with the pupil found by name.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/people-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_SUPER_ADMIN, SMOKE_STUDENT,
 * SMOKE_TEACHER, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const SUPER = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
const TEACHER = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Props that carry codes the page must name, and the phrase books it is sent:
// their values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'href', 'tab', 'hifzProgressUrl',
    // A pupil's status, a gender, a guardian's relationship, a link's consent
    // and verification (with the server's English labels for them), a
    // consent's type and source, a behaviour record's type, and the reason
    // the system wrote in a status history.
    'status', 'statuses', 'from_status', 'to_status', 'gender', 'relationship', 'relationships',
    'consent_status', 'verification_status', 'consent_label', 'verification_label', 'consentStatuses', 'verificationStatuses',
    'consent_type', 'consentTypes', 'source', 'type', 'reason', 'reason_key', 'field_type',
    // PE2: the roles a new login may get, a kind of employment, whose record
    // a custom field is and the types it may take.
    'roles', 'employment_type', 'employmentTypes', 'entityType', 'entityTypes', 'entity_type', 'fieldTypes',
]);
// A sensitive note's category is a code (with the server's English label for
// it) — but a behaviour record's category is the school's own words where
// the school typed one, so it is a code on the sensitive records only.
const SENSITIVE_CODE_KEYS = new Set([...CODE_KEYS, 'categories', 'category', 'category_label']);
// The names of formats, the same in every language; an e-mail address; a link.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const count = (table) => Number(tinker(`echo DB::table('${table}')->count();`));

function authorsWords(value, key = '', out = [], codes = CODE_KEYS) {
    if (codes.has(key)) {
        return out;
    }
    if (typeof value === 'string') {
        if (value.trim().length >= 2) {
            out.push(value.trim());
        }
    } else if (Array.isArray(value)) {
        value.forEach((item) => authorsWords(item, key, out, codes));
    } else if (value && typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => authorsWords(v, k, out, codes));
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

const inDhivehi = (said) => Boolean(said) && /\p{Script=Thaana}/u.test(said) && !/[A-Za-z]{3,}/.test(said);
const said = async (locator) => (await locator.first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;

const pupilId = Number(tinker(`echo (int) DB::table('students')->where('user_id', DB::table('users')->where('email', '${STUDENT}')->value('id'))->value('id');`));
const office = await signIn(ADMIN);
if (!pupilId) {
    check('the walks\' pupil is there', false, `no student for ${STUDENT} — seed first`);
}

// ------------------------------------- every screen, in Dhivehi and Arabic

async function readScreen(page, locale, path, codes = CODE_KEYS) {
    const label = `${locale}${path}`;
    const response = await page.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
    const where = new URL(page.url());
    if (!response || response.status() >= 400 || `${where.pathname}${where.search}` !== `/${locale}${path}`) {
        check(`${label}: the page opens`, false, `HTTP ${response?.status()} ${page.url().replace(BASE, '')}`);
        return;
    }
    const found = await readMain(page);
    const left = english(found.texts, authorsWords(await props(page), '', [], codes));
    check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
    check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
    check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
}

const staffId = Number(tinker(`$id = DB::table('staff_profiles')->where('user_id', DB::table('users')->where('email', '${TEACHER}')->value('id'))->value('id') ?? DB::table('staff_profiles')->orderBy('id')->value('id'); echo (int) $id;`));
if (!staffId) {
    check('a staff profile is there', false, `no staff profile for ${TEACHER} or anybody — seed first`);
}
const tabs = ['overview', 'guardians', 'emergency', 'documents', 'medical', 'history', 'consents', 'behavior'];
const screens = [
    '/people/students', ...(pupilId ? tabs.map((tab) => `/people/students/${pupilId}?tab=${tab}`) : []),
    // PE2.
    '/people/staff', ...(staffId ? [`/people/staff/${staffId}`] : []),
    '/people/custom-fields', '/people/custom-fields?entity_type=staff', '/people/custom-fields?entity_type=admission_applications',
    '/people/custom-fields/admission-preview',
];
const sensitiveScreens = ['/people/sensitive', ...(pupilId ? [`/people/sensitive?student_id=${pupilId}`] : [])];
const superAdmin = await signIn(SUPER);
for (const locale of ['dv', 'ar']) {
    for (const path of screens) {
        await readScreen(office, locale, path);
    }
    for (const path of sensitiveScreens) {
        await readScreen(superAdmin, locale, path, SENSITIVE_CODE_KEYS);
    }
}

// ------------------------------------- a student with no first name

await office.goto(`${BASE}/dv/people/students`, { waitUntil: 'networkidle' });
const book = (await props(office)).t ?? {};
const noFirstName = tinker("echo __('validation.required', ['attribute' => __('validation.attributes.first_name', [], 'dv')], 'dv');");
const addForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.students_add, exact: true }) }).first();
const studentsBefore = count('students');
await addForm.getByLabel(book.last_name, { exact: true }).fill('SMOKE-Lang-Pupil');
await addForm.getByLabel(book.date_of_birth, { exact: true }).fill('2014-05-05');
await addForm.locator('label', { hasText: book.gender }).locator('select').selectOption('female');
await addForm.getByRole('button', { name: book.students_add, exact: true }).click();
await office.waitForLoadState('networkidle');
const refusedPupil = await said(addForm.locator('ul.text-red-600'));
check('a student with no first name is refused under the form, the field named in Dhivehi; none is made', refusedPupil === noFirstName && inDhivehi(refusedPupil) && count('students') === studentsBefore, `said: ${refusedPupil ?? 'nothing'}`);

// ------------------------------------- the list, for a class

const classId = Number(tinker(`echo (int) DB::table('students')->where('id', ${pupilId || 0})->value('class_id');`));
if (classId) {
    const classLabel = tinker(`$c = DB::table('classes')->find(${classId}); echo trim($c->name.' '.$c->section);`);
    await office.goto(`${BASE}/dv/people/students`, { waitUntil: 'networkidle' });
    await office.getByLabel(book.col_class, { exact: true }).selectOption(String(classId));
    await office.getByRole('button', { name: book.students_filter, exact: true }).click();
    // An Inertia visit is no navigation: wait for the address to carry the class.
    await office.waitForURL((url) => url.searchParams.get('class_id') === String(classId), { timeout: 10000 }).catch(() => {});
    const cells = await office.locator('tbody tr td:nth-child(5)').allTextContents();
    check('the list reads for a class, every row naming it with its section', new URL(office.url()).searchParams.get('class_id') === String(classId) && cells.length > 0 && cells.every((cell) => cell.trim() === classLabel), `${office.url().replace(BASE, '')} · ${cells.slice(0, 3).join(' | ')} (want ${classLabel})`);
} else {
    check('the list reads for a class (skipped: the pupil is in no class)', true, '');
}

// ------------------------------------- the guardians: none offered twice, a refused Save said

if (pupilId) {
    await office.goto(`${BASE}/dv/people/students/${pupilId}?tab=guardians`, { waitUntil: 'networkidle' });
    const page = await props(office);
    const linked = (page.guardians ?? []).map((guardian) => guardian.name);
    const offered = await office.locator('select[aria-label="' + book.guardian + '"] option').allTextContents().catch(() => []);
    check('no guardian already linked is offered to attach', linked.length > 0 && offered.every((name) => !linked.includes(name.trim())), `linked: ${linked.join(', ')} · offered: ${offered.length}`);

    const row = office.locator('tr', { has: office.locator(`select[aria-label="${book.verification_status_aria}"]`) }).first();
    const guardianId = Number(page.guardians?.[0]?.guardian_id ?? page.guardians?.[0]?.id ?? 0);
    const before = tinker(`echo DB::table('guardian_student')->where('student_id', ${pupilId})->where('guardian_id', ${guardianId})->value('verification_status');`);
    await row.locator(`select[aria-label="${book.verification_status_aria}"]`).evaluate((select) => {
        const stale = document.createElement('option');
        stale.value = 'SMOKE-stale';
        stale.textContent = 'SMOKE-stale';
        select.appendChild(stale);
    });
    await row.locator(`select[aria-label="${book.verification_status_aria}"]`).selectOption('SMOKE-stale');
    await row.getByRole('button', { name: book.save, exact: true }).click();
    const refusedRow = await said(row.locator('ul.text-red-600'));
    const after = tinker(`echo DB::table('guardian_student')->where('student_id', ${pupilId})->where('guardian_id', ${guardianId})->value('verification_status');`);
    check('a guardian\'s record a stale page sends is refused under its row, in Dhivehi; the link is unchanged', inDhivehi(refusedRow) && before === after && before !== '', `said: ${refusedRow ?? 'nothing'}; ${before} → ${after}`);

    // ------------------------------------- an emergency contact refused for its relationship

    await office.goto(`${BASE}/dv/people/students/${pupilId}?tab=emergency`, { waitUntil: 'networkidle' });
    const contactForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.contact_add, exact: true }) }).first();
    const contactsBefore = count('emergency_contacts');
    await contactForm.getByLabel(book.col_name, { exact: true }).fill('SMOKE-Lang-Contact');
    await contactForm.getByLabel(book.phone, { exact: true }).fill('7000000');
    await contactForm.getByLabel(book.relationship, { exact: true }).fill('x'.repeat(300));
    await contactForm.getByRole('button', { name: book.contact_add, exact: true }).click();
    const refusedContact = await said(contactForm.locator('ul.text-red-600'));
    check('a contact whose relationship runs past 255 letters is refused under the form, in Dhivehi; none is made', inDhivehi(refusedContact) && count('emergency_contacts') === contactsBefore, `said: ${refusedContact ?? 'nothing'}`);

    // ------------------------------------- a consent the pupil already has

    await office.goto(`${BASE}/dv/people/students/${pupilId}?tab=consents`, { waitUntil: 'networkidle' });
    const photoGranted = tinker(`echo (int) DB::table('consents')->where('person_type', 'student')->where('person_id', ${pupilId})->where('consent_type', 'photo_media_use')->orderByDesc('id')->value('granted');`);
    const consentsBefore = count('consents');
    const consentForm = office.locator('form', { has: office.locator('select[name="consent_type"]') });
    await consentForm.locator('select[name="consent_type"]').selectOption('photo_media_use');
    await consentForm.locator('select[name="granted"]').selectOption('1');
    await consentForm.getByRole('button', { name: book.consent_record, exact: true }).click();
    const toldUnchanged = await office.getByText(tinker("echo __('people.flash_consent_unchanged', [], 'dv');"), { exact: true }).first().waitFor({ timeout: 10000 }).then(() => true).catch(() => false);
    check('granting a consent the pupil already has says nothing changed, in Dhivehi; no row is added', photoGranted === '1' && toldUnchanged && count('consents') === consentsBefore, `granted before: ${photoGranted}; told: ${toldUnchanged}; rows ${consentsBefore} → ${count('consents')}`);
}

// ------------------------------------- the reason the directory wrote, named

const directoryPupil = Number(tinker("echo (int) DB::table('student_status_history')->where('reason', 'Created via student directory')->value('student_id');"));
if (directoryPupil) {
    await office.goto(`${BASE}/dv/people/students/${directoryPupil}?tab=history`, { waitUntil: 'networkidle' });
    const reasons = await office.locator('tbody tr td:nth-child(3)').allTextContents();
    check('the status history names the reason the directory wrote, in Dhivehi', reasons.length > 0 && reasons.some((reason) => reason.trim() === book.history_reason_created) && reasons.every((reason) => !/[A-Za-z]{3,}/.test(reason)), reasons.join(' | '));
} else {
    check('the status history names the reason the directory wrote (skipped: no pupil was added from the directory — run middle-name.mjs)', true, '');
}

// ------------------------------------- PE2: a staff profile with no first name

const dvSays = (key) => tinker(`echo __('${key}', [], 'dv');`);
const dvRefusal = (rule, attribute) => tinker(`echo __('validation.${rule}', ['attribute' => __('validation.attributes.${attribute}', [], 'dv')], 'dv');`);
const toldInDhivehi = (page, key) => page.getByText(dvSays(key), { exact: true }).first().waitFor({ timeout: 10000 }).then(() => true).catch(() => false);

await office.goto(`${BASE}/dv/people/staff`, { waitUntil: 'networkidle' });
const staffForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.staff_create, exact: true }) }).first();
const [usersBefore, profilesBefore] = [count('users'), count('staff_profiles')];
await staffForm.getByLabel(book.staff_email, { exact: true }).fill(`smoke-lang-staff-${Date.now()}@example.test`);
await staffForm.getByLabel(book.staff_password, { exact: true }).fill('SMOKE-password-1');
await staffForm.getByLabel(book.last_name, { exact: true }).fill('SMOKE-Lang-Staff');
await staffForm.getByRole('button', { name: book.staff_create, exact: true }).click();
const refusedStaff = await said(staffForm.locator('ul.text-red-600'));
const noStaffFirstName = dvRefusal('required', 'first_name');
check('a staff profile with no first name is refused under the form, the field named in Dhivehi; no account and no profile are made', refusedStaff === noStaffFirstName && count('users') === usersBefore && count('staff_profiles') === profilesBefore, `said: ${refusedStaff ?? 'nothing'} (want ${noStaffFirstName})`);

if (staffId) {
    // ------------------------------------- a staff status the server does not know

    await office.goto(`${BASE}/dv/people/staff/${staffId}`, { waitUntil: 'networkidle' });
    const employmentForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.staff_save_employment, exact: true }) }).first();
    const statusSelect = employmentForm.locator('select:has(option[value="on_leave"])');
    const statusBefore = tinker(`echo DB::table('staff_profiles')->where('id', ${staffId})->value('status');`);
    await statusSelect.evaluate((select) => {
        const stale = document.createElement('option');
        stale.value = 'SMOKE-stale';
        stale.textContent = 'SMOKE-stale';
        select.appendChild(stale);
    });
    await statusSelect.selectOption('SMOKE-stale');
    await employmentForm.getByRole('button', { name: book.staff_save_employment, exact: true }).click();
    const refusedStatus = await said(employmentForm.locator('ul.text-red-600'));
    const statusAfter = tinker(`echo DB::table('staff_profiles')->where('id', ${staffId})->value('status');`);
    const unknownStatus = dvRefusal('in', 'status');
    check('a staff status a stale page sends is refused under the employment form, named in Dhivehi; the status is unchanged', refusedStatus === unknownStatus && statusBefore === statusAfter && statusBefore !== '', `said: ${refusedStatus ?? 'nothing'} (want ${unknownStatus}); ${statusBefore} → ${statusAfter}`);

    // ------------------------------------- a qualification: refused, added, removed

    tinker(`DB::table('staff_qualifications')->where('title', 'SMOKE-Lang-Qualification')->delete(); echo 'ok';`);
    await office.goto(`${BASE}/dv/people/staff/${staffId}`, { waitUntil: 'networkidle' });
    const qualificationForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.qualification_add, exact: true }) }).first();
    const qualificationsBefore = count('staff_qualifications');
    await qualificationForm.getByLabel(book.qualification_institution, { exact: true }).fill('SMOKE-Lang-Institution');
    await qualificationForm.getByRole('button', { name: book.qualification_add, exact: true }).click();
    const refusedQualification = await said(qualificationForm.locator('ul.text-red-600'));
    const noTitle = dvRefusal('required', 'title');
    check('a qualification with no title is refused under its form, named in Dhivehi; none is added', refusedQualification === noTitle && count('staff_qualifications') === qualificationsBefore, `said: ${refusedQualification ?? 'nothing'} (want ${noTitle})`);

    await qualificationForm.getByLabel(book.qualification_title, { exact: true }).fill('SMOKE-Lang-Qualification');
    await qualificationForm.getByLabel(book.qualification_year, { exact: true }).fill('2019');
    await qualificationForm.getByRole('button', { name: book.qualification_add, exact: true }).click();
    const added = await toldInDhivehi(office, 'people.flash_qualification_added');
    const qualificationRow = office.locator('tbody tr', { hasText: 'SMOKE-Lang-Qualification' });
    const shown = await qualificationRow.first().waitFor({ timeout: 10000 }).then(() => true).catch(() => false);
    check('a qualification with a title is added, said in Dhivehi, and listed', added && shown && count('staff_qualifications') === qualificationsBefore + 1, `told: ${added}; listed: ${shown}`);

    await qualificationRow.first().getByRole('button', { name: book.remove, exact: true }).click();
    const removed = await toldInDhivehi(office, 'people.flash_qualification_removed');
    const gone = await qualificationRow.first().waitFor({ state: 'detached', timeout: 10000 }).then(() => true).catch(() => false);
    check('the qualification is removed, said in Dhivehi, and leaves the list', removed && gone && count('staff_qualifications') === qualificationsBefore, `told: ${removed}; gone: ${gone}`);
}

// ------------------------------------- PE2: a custom field, refused, made, previewed, archived

// A field an earlier run left behind would sit on the public admission form;
// archive it first, as the office would.
tinker("DB::table('custom_field_definitions')->where('label_en', 'SMOKE-Lang-Field')->whereNull('deleted_at')->update(['deleted_at' => now()]); echo 'ok';");
const fieldKey = `smoke_lang_${Date.now()}`;
const labelDv = 'ސްމޯކް ސުވާލު';
const labelAr = 'سؤال تجريبي';
await office.goto(`${BASE}/dv/people/custom-fields?entity_type=admission_applications`, { waitUntil: 'networkidle' });
const fieldForm = office.locator('form').filter({ has: office.getByRole('button', { name: book.fields_create, exact: true }) }).first();
const definitionsBefore = count('custom_field_definitions');
await fieldForm.getByLabel(book.fields_key, { exact: true }).fill(fieldKey);
await fieldForm.getByLabel(book.fields_label_dv, { exact: true }).fill(labelDv);
await fieldForm.getByLabel(book.fields_label_ar, { exact: true }).fill(labelAr);
await fieldForm.getByLabel(book.col_type, { exact: true }).selectOption('select');
await fieldForm.getByLabel(book.fields_options, { exact: true }).fill('ހުދު\nރަތް');
await fieldForm.locator('label', { hasText: book.fields_in_admission }).locator('input[type=checkbox]').check();
await fieldForm.getByRole('button', { name: book.fields_create, exact: true }).click();
const refusedLabel = await said(fieldForm.locator('label', { has: office.getByLabel(book.fields_label_en, { exact: true }) }).locator('span.text-red-600'));
const noEnglishLabel = dvRefusal('required', 'label_en');
check('a custom field with no English label is refused beside the field, named in Dhivehi; none is made', refusedLabel === noEnglishLabel && count('custom_field_definitions') === definitionsBefore, `said: ${refusedLabel ?? 'nothing'} (want ${noEnglishLabel})`);

await fieldForm.getByLabel(book.fields_label_en, { exact: true }).fill('SMOKE-Lang-Field');
await fieldForm.getByRole('button', { name: book.fields_create, exact: true }).click();
const fieldMade = await toldInDhivehi(office, 'people.flash_field_created');
const fieldRow = office.locator('tbody tr', { hasText: fieldKey });
const fieldListed = await fieldRow.first().waitFor({ timeout: 10000 }).then(() => true).catch(() => false);
const fieldCells = fieldListed ? (await fieldRow.first().locator('td').allTextContents()).map((cell) => cell.trim()) : [];
const wantSettings = [book.fields_flag_admission, book.fields_flag_active].join(book.list_separator || ', ');
check('with its English label the field is made, said in Dhivehi; the list names it by its Dhivehi label, its type and its settings in Dhivehi', fieldMade && fieldCells[1] === labelDv && fieldCells[2] === book.field_type_select && fieldCells[3] === wantSettings, `told: ${fieldMade}; row: ${fieldCells.slice(0, 4).join(' | ')} (want ${labelDv} | ${book.field_type_select} | ${wantSettings})`);

for (const [locale, label] of [['dv', labelDv], ['ar', labelAr]]) {
    await readScreen(office, locale, '/people/custom-fields/admission-preview');
    const preview = await office.locator('main').textContent().catch(() => '');
    check(`${locale}: the admission form preview shows the field by its ${locale === 'dv' ? 'Dhivehi' : 'Arabic'} label`, preview.includes(label) && !preview.includes('SMOKE-Lang-Field'), label);
}

await office.goto(`${BASE}/dv/people/custom-fields?entity_type=admission_applications`, { waitUntil: 'networkidle' });
await fieldRow.first().getByRole('button', { name: book.fields_archive, exact: true }).click();
const fieldArchived = await toldInDhivehi(office, 'people.flash_field_archived');
const fieldGone = await fieldRow.first().waitFor({ state: 'detached', timeout: 10000 }).then(() => true).catch(() => false);
const trashed = tinker(`echo DB::table('custom_field_definitions')->where('key', '${fieldKey}')->whereNotNull('deleted_at')->count();`);
check('archived, the field leaves the list, said in Dhivehi, and stays on record', fieldArchived && fieldGone && trashed === '1', `told: ${fieldArchived}; gone: ${fieldGone}; archived rows: ${trashed}`);

// ------------------------------------- PE2: a sensitive note, refused, recorded, archived meanwhile

if (pupilId) {
    // The walk's own notes from an earlier run; nobody else writes this summary.
    tinker(`DB::table('student_sensitive_notes')->where('summary', 'SMOKE-Lang-Note')->delete(); echo 'ok';`);
    await superAdmin.goto(`${BASE}/dv/people/sensitive?student_id=${pupilId}`, { waitUntil: 'networkidle' });
    const superBook = (await props(superAdmin)).t ?? {};
    const noteForm = superAdmin.locator('form').filter({ has: superAdmin.getByRole('button', { name: superBook.sensitive_record, exact: true }) }).first();
    const notesBefore = count('student_sensitive_notes');
    await noteForm.locator('select').first().selectOption('dietary');
    await noteForm.getByRole('button', { name: superBook.sensitive_record, exact: true }).click();
    const refusedNote = await said(noteForm.locator('span.text-red-600'));
    const noSummary = dvRefusal('required', 'summary');
    check('a sensitive note with no summary is refused under the line, named in Dhivehi; none is made', refusedNote === noSummary && count('student_sensitive_notes') === notesBefore, `said: ${refusedNote ?? 'nothing'} (want ${noSummary})`);

    await noteForm.getByLabel(superBook.sensitive_summary, { exact: true }).fill('SMOKE-Lang-Note');
    await noteForm.getByRole('button', { name: superBook.sensitive_record, exact: true }).click();
    const recorded = await toldInDhivehi(superAdmin, 'people.flash_note_recorded');
    const noteItem = superAdmin.locator('li', { hasText: 'SMOKE-Lang-Note' });
    const chip = recorded ? await said(noteItem.locator('span').first()) : null;
    check('a note is recorded, said in Dhivehi, its category named in Dhivehi', recorded && chip === superBook.sensitive_category_dietary && count('student_sensitive_notes') === notesBefore + 1, `told: ${recorded}; category: ${chip ?? 'nothing'}`);

    // Somebody else archives it while this page is open.
    const noteId = Number(tinker("echo (int) DB::table('student_sensitive_notes')->where('summary', 'SMOKE-Lang-Note')->value('id');"));
    tinker(`DB::table('student_sensitive_notes')->where('id', ${noteId})->update(['archived_at' => now()->subMinute()->startOfMinute(), 'archived_by' => DB::table('users')->where('email', '${SUPER}')->value('id')]); echo 'ok';`);
    const archivedBefore = tinker(`echo DB::table('student_sensitive_notes')->where('id', ${noteId})->value('archived_at');`);
    await noteItem.first().getByRole('button', { name: superBook.sensitive_archive, exact: true }).click();
    const refusedArchive = await said(noteItem.first().locator('ul.text-red-600'));
    const archivedAfter = tinker(`echo DB::table('student_sensitive_notes')->where('id', ${noteId})->value('archived_at');`);
    check('archived meanwhile from another page, its Archive is refused under the note, in Dhivehi; it stays archived as it was', refusedArchive === dvSays('people.error_note_already_archived') && archivedBefore === archivedAfter && archivedBefore !== '', `said: ${refusedArchive ?? 'nothing'}; ${archivedBefore} → ${archivedAfter}`);

    // The page with the pupil found by name and an archived note on it.
    const pupilName = tinker(`echo DB::table('students')->where('id', ${pupilId})->value('first_name');`);
    for (const locale of ['dv', 'ar']) {
        await readScreen(superAdmin, locale, `/people/sensitive?q=${encodeURIComponent(pupilName)}&student_id=${pupilId}`, SENSITIVE_CODE_KEYS);
    }
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
