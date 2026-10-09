/**
 * Does the family portal read in Dhivehi and Arabic? (BACKLOG C21, slice
 * PT1a, STATUS §5pv.)
 *
 * The seeded parent opens their home, their children, attendance, homework,
 * the noticeboard and the school calendar under /dv and /ar, and the pupil
 * opens their own home and homework. The walk lists what is still in Latin
 * letters in each page's main — every text node, placeholder, aria-label,
 * title and phone caption (`data-label`). What a page shows of its data (a
 * child's name, a subject, a notice the office wrote) is the author's, so a
 * string found among the page's props passes; codes the server sends to be
 * named (an attendance mark, an invoice's status, a relationship, a notice's
 * type) do not, and printed raw they fail. So do the phrase books the page
 * is sent. CSV and MVR are the same in every language. Each page must also
 * be right to left, and every field in it named.
 *
 * Then, in Dhivehi:
 *   - the parent follows the home's Homework tile, and the page it opens is
 *     still in Dhivehi;
 *   - the parent writes SMOKE-Lang-Message to their child's teacher and is
 *     told it was sent, in Dhivehi; an empty reply is refused beside its box,
 *     in Dhivehi; a reply is sent and said so; and the thread, the inbox, a
 *     new message and the notifications read in Dhivehi and Arabic (slice
 *     PT1b);
 *   - the report cards, exam results, awards, behaviour, fees, a child's
 *     work, arrivals and departures and lost property read in Dhivehi and
 *     Arabic, codes named (slice PT2);
 *   - absence notes, sign-up forms, collecting a child, meetings, events,
 *     library books and a child's Digital Library read in Dhivehi and
 *     Arabic, and a PIN too obvious is refused beside its box, in Dhivehi
 *     (slice PT3);
 *   - the pupil ticks SMOKE-Lang-Homework done (`SmokeMarkerSeeder` plants it
 *     on a register two days back) and is told so in Dhivehi; the tick holds
 *     over a reload, and unticking it puts it back.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/portal-language.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PARENT, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PUPIL = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const HOMEWORK = 'SMOKE-Lang-Homework';
const MESSAGE = 'SMOKE-Lang-Message';

// Props that carry codes the page must name, and the phrase books it is sent:
// their values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'nav', 'auth', 'locale', 'locales', 'locale_urls', 'errors', 'flash', 'status', 'relationship',
    'type', 'priority', 'notification_state', 'notification_label', 'verification_status', 'csvUrl', 'href',
    'category', 'categories', 'platform', 'reach',
    // Slice PT2: a payment plan's state, an arrival or departure and how it
    // was recorded, and the server's English names for those.
    'plan_status', 'direction', 'direction_label', 'source', 'source_label',
]);
// The names of formats and of a code a phone reads, the same in every language.
const ALWAYS_FINE = [/https?:\/\/\S*/g, /\bCSV\b/g, /\bMVR\b/g, /\bPDF\b/g, /\bHTML\b/g, /\bQR\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});

// A behaviour record's category is what the teacher wrote, where a
// notification's is a code (slice PT2): a page may say which of the code
// keys are its author's words.
const AUTHORS_KEYS_ON = { '/portal/behavior': ['category'] };

function authorsWords(value, key = '', out = [], codeKeys = CODE_KEYS) {
    if (codeKeys.has(key)) {
        return out;
    }
    if (typeof value === 'string') {
        if (value.trim().length >= 2) {
            out.push(value.trim());
        }
    } else if (Array.isArray(value)) {
        value.forEach((item) => authorsWords(item, key, out, codeKeys));
    } else if (value && typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => authorsWords(v, k, out, codeKeys));
    }
    return out;
}

// The author's words come out first: a notice that says "113.86 MVR" is
// the author's whole sentence, and taking MVR out of it first left a
// sentence nobody wrote (slice PT1b).
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

const parent = await signIn(PARENT);
const pupil = await signIn(PUPIL);

// ------------------------------------- a message, a refusal and a reply, in Dhivehi
// (slice PT1b). Written first, so the thread is among the screens below.

await parent.goto(`${BASE}/dv/portal/messages/new`, { waitUntil: 'networkidle' });
const compose = await props(parent);
const words = compose.t ?? {};
let threadPath = null;
if ((compose.recipients || []).length === 0) {
    check('the parent has a teacher to write to', false, 'no recipients — is the child on a class roster with a timetable?');
} else {
    await parent.getByLabel(words.messages_subject, { exact: true }).fill(MESSAGE);
    await parent.getByLabel(words.messages_body, { exact: true }).fill('SMOKE-Lang-Message: a question about the trip.');
    await parent.getByRole('button', { name: words.messages_send, exact: true }).click();
    await parent.waitForURL(/\/portal\/messages\/\d+$/, { timeout: 10000 }).catch(() => {});
    const sent = (await parent.getByTestId('flash-success').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('the parent writes to the teacher and is told it was sent, in Dhivehi', sent === words.flash_message_sent, `said: ${sent ?? 'nothing'}`);
    threadPath = new URL(parent.url()).pathname.replace(/^\/(en|dv|ar)/, '');

    await parent.getByRole('button', { name: words.messages_send_reply, exact: true }).click();
    const refused = (await parent.locator('textarea + span').first().textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('an empty reply is refused beside its box, in Dhivehi', Boolean(refused) && /\p{Script=Thaana}/u.test(refused) && !/[A-Za-z]{3,}/.test(refused), `said: ${refused ?? 'nothing'}`);

    await parent.locator('textarea').first().fill('SMOKE-Lang-Message: thank you.');
    await parent.getByRole('button', { name: words.messages_send_reply, exact: true }).click();
    await parent.waitForLoadState('networkidle');
    const replied = (await parent.getByTestId('flash-success').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('and a reply is sent and said so, in Dhivehi', replied === words.flash_reply_sent, `said: ${replied ?? 'nothing'}`);
}

// A child's Digital Library is reached from the children page (slice PT3).
await parent.goto(`${BASE}/en/portal/children`, { waitUntil: 'networkidle' });
const childLibrary = (await parent.getByTestId('child-library').first().getAttribute('href').catch(() => null))?.replace(/^\/(en|dv|ar)/, '') ?? null;

const screens = [
    ['/portal/home', parent],
    ['/portal/children', parent],
    ['/portal/attendance', parent],
    ['/portal/homework', parent],
    ['/portal/announcements', parent],
    ['/portal/holidays', parent],
    ['/portal/home', pupil],
    ['/portal/homework', pupil],
    // Slice PT1b.
    ['/portal/notifications', parent],
    ['/portal/messages', parent],
    ['/portal/messages/new', parent],
    ...(threadPath ? [[threadPath, parent]] : []),
    // Slice PT2.
    ['/portal/report-cards', parent],
    ['/portal/exams', parent],
    ['/portal/awards', parent],
    ['/portal/behavior', parent],
    ['/portal/invoices', parent],
    ['/portal/work', parent],
    ['/portal/movements', parent],
    ['/portal/found-items', parent],
    // Slice PT3.
    ['/portal/absence-notes', parent],
    ['/portal/forms', parent],
    ['/portal/pickup', parent],
    ['/portal/meetings', parent],
    ['/portal/events', parent],
    ['/portal/loans', parent],
    ...(childLibrary ? [[childLibrary, parent]] : []),
];

for (const locale of ['dv', 'ar']) {
    for (const [path, viewer] of screens) {
        const who = viewer === parent ? 'the parent' : 'the pupil';
        const label = `${locale}${path} (${who})`;
        const response = await viewer.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        if (!response || response.status() >= 400) {
            check(`${label}: the page opens`, false, `HTTP ${response?.status()} ${viewer.url().replace(BASE, '')}`);
            continue;
        }
        const found = await readMain(viewer);
        const codeKeys = new Set([...CODE_KEYS].filter((key) => !(AUTHORS_KEYS_ON[path] || []).includes(key)));
        const left = english(found.texts, authorsWords(await props(viewer), '', [], codeKeys));
        check(`${label}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${label}: nothing left in English`, left.length === 0, left.slice(0, 6).join(' | '));
        check(`${label}: every field is named`, found.unnamed.length === 0, found.unnamed.slice(0, 3).join(' | '));
    }
}

// ------------------------------------- the family's records, in Dhivehi (slice PT2)

// The transcript is asked for in the page's language; the button always sent `en`.
await parent.goto(`${BASE}/dv/portal/report-cards`, { waitUntil: 'networkidle' });
const transcriptHref = await parent.getByTestId('transcript').getAttribute('href').catch(() => null);
check('the report cards page asks for the transcript in Dhivehi', /[?&]locale=dv\b/.test(transcriptHref || ''), transcriptHref || 'no transcript link');

// A Pay refused is said under its own row. The fees page is opened, then the
// invoice is settled behind it, as the office or another tab might, and Pay
// is pressed: the server says it is already paid, in Dhivehi, on that row.
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const invoice = "\\Illuminate\\Support\\Facades\\DB::table('invoices')->where('invoice_number', 'SMOKE-INV-1')";
await parent.goto(`${BASE}/dv/portal/invoices`, { waitUntil: 'networkidle' });
const fees = (await props(parent)).t ?? {};
const feeRow = parent.locator('tr', { hasText: 'SMOKE-INV-1' });
const payInFull = feeRow.locator('span.inline-flex button').last();
if ((await payInFull.count()) === 0) {
    check('a Pay refused is said under its row, in Dhivehi', false, 'no Pay button on SMOKE-INV-1');
} else {
    const paidBefore = tinker(`echo ${invoice}->value('paid_amount');`);
    tinker(`${invoice}->update(['paid_amount' => \\Illuminate\\Support\\Facades\\DB::raw('total_amount')]);`);
    try {
        await payInFull.click();
        await parent.waitForLoadState('networkidle');
        const said = (await feeRow.locator('[role="alert"]').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
        check('a Pay refused is said under its row, in Dhivehi', said === fees.error_invoice_paid, `said: ${said ?? 'nothing'}`);
    } finally {
        tinker(`${invoice}->update(['paid_amount' => ${Number(paidBefore) || 0}]);`);
    }
}

// ------------------------------------- an obvious PIN, refused in Dhivehi (slice PT3)

await parent.goto(`${BASE}/dv/portal/pickup`, { waitUntil: 'networkidle' });
const pickupWords = (await props(parent)).t ?? {};
const pinForm = parent.locator('form').first();
await pinForm.locator('input[type="password"]').fill('1111');
await pinForm.locator('button[type="submit"]').click();
await parent.waitForLoadState('networkidle');
const pinRefused = (await pinForm.locator('input[type="password"] + span').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
check('an obvious pick-up PIN is refused beside its box, in Dhivehi', pinRefused === pickupWords.error_pickup_pin_obvious, `said: ${pinRefused ?? 'nothing'}`);

// ------------------------------------- a tile's door keeps the page's language

await parent.goto(`${BASE}/dv/portal/home`, { waitUntil: 'networkidle' });
const homeProps = await props(parent);
const homeworkTile = (homeProps.tiles || []).find((tile) => tile.key === 'homework');
check('the parent’s home names its tiles in Dhivehi', (homeProps.tiles || []).length > 0 && (homeProps.tiles || []).every((tile) => /\p{Script=Thaana}/u.test(`${tile.label}${tile.status}`)), (homeProps.tiles || []).map((tile) => tile.label).join(', '));
if (homeworkTile) {
    await parent.locator(`main a[href="${homeworkTile.href}"]`).first().click();
    await parent.waitForURL(/\/portal\/homework/, { timeout: 10000 }).catch(() => {});
    await parent.reload({ waitUntil: 'networkidle' });
    const landed = await props(parent);
    check('and its Homework tile opens the homework page, still in Dhivehi', parent.url().includes('/dv/portal/homework') && /\p{Script=Thaana}/u.test(landed.t?.homework_title || ''), parent.url().replace(BASE, ''));
} else {
    check('and its Homework tile opens the homework page, still in Dhivehi', false, 'no homework tile');
}

// ------------------------------------------------ the pupil's tick, in Dhivehi

await pupil.goto(`${BASE}/dv/portal/homework`, { waitUntil: 'networkidle' });
const t = (await props(pupil)).t ?? {};
const item = pupil.locator('li', { hasText: HOMEWORK }).first();
check('the pupil finds SMOKE-Lang-Homework on the Dhivehi homework page', (await item.count()) > 0, `${await item.count()} item(s)`);
const box = item.locator('input[type="checkbox"]');
if ((await box.count()) > 0) {
    if ((await box.getAttribute('aria-label')) === t.homework_mark_not_done) {
        await box.click();
        await pupil.waitForLoadState('networkidle');
    }
    check('its box is named in Dhivehi', (await box.getAttribute('aria-label')) === t.homework_mark_done, String(await box.getAttribute('aria-label')));
    await box.click();
    const said = (await pupil.getByTestId('flash-success').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check('ticking it done is said in Dhivehi', said === t.flash_homework_ticked, `said: ${said ?? 'nothing'}`);
    await pupil.reload({ waitUntil: 'networkidle' });
    check('and the tick holds over a reload', (await pupil.locator('li', { hasText: HOMEWORK }).first().locator('input[type="checkbox"]').isChecked()), '');
    await pupil.locator('li', { hasText: HOMEWORK }).first().locator('input[type="checkbox"]').click();
    await pupil.waitForLoadState('networkidle');
    await pupil.reload({ waitUntil: 'networkidle' });
    check('unticking it puts it back', !(await pupil.locator('li', { hasText: HOMEWORK }).first().locator('input[type="checkbox"]').isChecked()), '');
} else {
    check('its box is named in Dhivehi', false, 'no checkbox');
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
