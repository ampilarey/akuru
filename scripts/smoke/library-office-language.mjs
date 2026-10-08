/**
 * Does the Library office read in Dhivehi and Arabic? (BACKLOG C20, slice
 * LT3, STATUS §5pf.)
 *
 * The office opens /admin/library, its reading alerts, insights, settings,
 * reviewers and promotions under /dv and /ar. The walk lists what is still in
 * Latin letters in each page's main — every text node, placeholder,
 * aria-label and title. What the pages show of their data (a title, a
 * writer's name, an email, a category somebody named) is the author's, so a
 * string found among the page's props passes; codes the server sends to be
 * named (a type, an access, a status, a history entry) do not, and printed
 * raw they fail. So do the phrase books the page is sent. File types,
 * addresses and CSV are the same in every language, and so is the name of
 * the setting that turns enforcement on. Each page must also be right to
 * left, and every field in it named.
 *
 * Then, on the Dhivehi page:
 *   - the office adds SMOKE-Lang-Category (once) and adds it again. The
 *     second is refused beside the category form, in Dhivehi;
 *   - the writer submits SMOKE-Lang-Queue, research with its three
 *     declarations (once; it waits in the queue after), and the office
 *     assigns it a reviewer by an email nobody has. The refusal is said on
 *     the submission's row, in Dhivehi. The page's one list at the top used
 *     to say it in English, and the item stays submitted.
 *
 *   node scripts/smoke/library-office-language.mjs
 *
 * The writer is the smoke seed's approved writer (`library.mjs` makes them
 * one).
 *
 * Environment: SMOKE_BASE_URL, SMOKE_APPLICANT, SMOKE_STAFF, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const WRITER = process.env.SMOKE_APPLICANT ?? 'student@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const CATEGORY = 'SMOKE-Lang-Category';
const QUEUED = 'SMOKE-Lang-Queue';
const NOBODY = 'smoke-nobody@akuru.invalid';

// Props that carry codes the page must name, and the phrase books it is sent:
// their values are not the author's words, so they excuse nothing.
const CODE_KEYS = new Set([
    'i18n', 't', 'id_l', 'nav', 'locale', 'locales', 'locale_urls', 'content_types', 'access_types', 'content_type',
    'access_type', 'status', 'state', 'delivery', 'difficulty', 'language', 'recommendation', 'decision', 'signal',
    'signal_label', 'outcome', 'errors', 'flash', 'periods', 'period',
]);
const ALWAYS_FINE = [
    /https?:\/\/\S*/g, /\bPDF\b/g, /\bCSV\b/g, /\bHTML\b/g, /\bJPEG\b/g, /\bPNG\b/g, /\bWebP\b/g, /\bMB\b/g, /\bMVR\b/g,
    /\bLIBRARY_ABUSE_ENFORCE\b/g, /[\w.+-]+@[\w-]+(\.[\w-]+)+/g,
];

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const props = (page) => page.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent || '{}').props || {});

function authorsWords(value, key = '', out = []) {
    if (CODE_KEYS.has(key)) {
        return out;
    }
    if (typeof value === 'string') {
        // A body arrives as HTML and is shown as its text, piece by piece.
        for (const piece of value.split(/<[^>]*>/)) {
            if (piece.trim().length >= 2) {
                out.push(piece.trim());
            }
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

const office = await signIn(STAFF);
const writer = await signIn(WRITER);

// --------------------------------------- a category added twice, in Dhivehi

await office.goto(`${BASE}/dv/admin/library`, { waitUntil: 'networkidle' });
const officeProps = await props(office);
const t = officeProps.t ?? {};
check('the office page is sent its Dhivehi words', Boolean(t.library_office_title) && /\p{Script=Thaana}/u.test(t.library_office_title), String(t.library_office_title));
// A step that cannot find its controls fails, rather than stopping the walk —
// against a build with no Dhivehi words there is nothing to find by them.
const attempt = async (step, run) => {
    try {
        await run();
    } catch (error) {
        check(step, false, String(error).split('\n')[0].slice(0, 160));
    }
};

await attempt('a second SMOKE-Lang-Category is refused beside the form, in Dhivehi', async () => {
    const categoryForm = office.locator('main form').filter({ has: office.getByPlaceholder(t.library_office_new_category, { exact: true }) }).first();
    if (!(officeProps.categories || []).some((row) => row.name === CATEGORY)) {
        await categoryForm.getByPlaceholder(t.library_office_new_category, { exact: true }).fill(CATEGORY);
        await categoryForm.getByRole('button', { name: t.library_office_add, exact: true }).click();
        await office.waitForTimeout(800);
    }
    await office.goto(`${BASE}/dv/admin/library`, { waitUntil: 'networkidle' });
    await categoryForm.getByPlaceholder(t.library_office_new_category, { exact: true }).fill(CATEGORY);
    await categoryForm.getByRole('button', { name: t.library_office_add, exact: true }).click();
    const categoryRefusal = (await categoryForm.locator('ul[role="alert"]').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check(
        'a second SMOKE-Lang-Category is refused beside the form, in Dhivehi',
        Boolean(categoryRefusal) && categoryRefusal === t.library_office_error_category_slug,
        `said: ${categoryRefusal ?? 'nothing'}`,
    );
});

// ------------------------- the queued research, assigned to nobody, in Dhivehi

await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
const mine = ((await props(writer)).dashboard?.items || []).find((row) => row.title === QUEUED);
if (!mine) {
    await writer.getByRole('button', { name: 'New draft', exact: true }).click();
    const editor = writer.getByTestId('draft-editor');
    await editor.getByLabel('Title', { exact: true }).fill(QUEUED);
    await editor.getByLabel('Type', { exact: true }).selectOption('research');
    for (const name of ['copyright', 'originality', 'conflict_of_interest']) {
        await editor.locator(`input[name="declarations[${name}]"]`).check();
    }
    await editor.getByRole('button', { name: 'Save draft', exact: true }).click();
    await writer.waitForTimeout(800);
    await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
}
const row = writer.locator('tr', { hasText: QUEUED }).first();
const submit = row.getByRole('button', { name: 'Submit for review', exact: true });
if (await submit.count()) {
    await submit.click();
    await writer.waitForTimeout(800);
}
await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
const queued = ((await props(writer)).dashboard?.items || []).find((item) => item.title === QUEUED);
check('SMOKE-Lang-Queue waits in the office\'s queue', queued?.status === 'submitted', `status=${queued?.status}`);

await attempt('a reviewer nobody is refused on the submission\'s row, in Dhivehi', async () => {
    await office.goto(`${BASE}/dv/admin/library`, { waitUntil: 'networkidle' });
    const submissionRow = office.locator('tr', { hasText: QUEUED }).first();
    await submissionRow.getByPlaceholder(t.library_office_reviewer_email, { exact: true }).fill(NOBODY);
    await submissionRow.getByRole('button', { name: t.library_office_assign, exact: true }).click();
    const assignRefusal = (await submissionRow.locator('ul[role="alert"]').textContent({ timeout: 10000 }).catch(() => null))?.trim() ?? null;
    check(
        'a reviewer nobody is refused on the submission\'s row, in Dhivehi',
        Boolean(assignRefusal) && assignRefusal === t.library_office_error_no_user,
        `said: ${assignRefusal ?? 'nothing'}`,
    );
});
await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
const still = ((await props(writer)).dashboard?.items || []).find((item) => item.title === QUEUED);
check('and the item stays submitted', still?.status === 'submitted', `status=${still?.status}`);

// ------------------------------------------------- the pages, in both languages

const screens = ['/admin/library', '/admin/library/reading-alerts', '/admin/library/insights', '/admin/library/settings', '/admin/library/reviewers', '/admin/library/promotions'];

for (const locale of ['dv', 'ar']) {
    for (const path of screens) {
        const response = await office.goto(`${BASE}/${locale}${path}`, { waitUntil: 'networkidle' });
        if (!(await office.locator('main').count())) {
            check(`${locale}${path}: the screen opens`, false, `HTTP ${response?.status()} ${office.url().replace(BASE, '')}`);
            continue;
        }
        const pageProps = await props(office);
        const found = await office.evaluate(() => {
            const main = document.querySelector('main');
            const texts = [];
            const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) {
                const node = walker.currentNode;
                if (node.parentElement?.closest('script, style, textarea, code, pre, [contenteditable="true"]')) {
                    continue;
                }
                const text = node.textContent.replace(/\s+/g, ' ').trim();
                if (text) {
                    texts.push(text);
                }
            }
            for (const el of main.querySelectorAll('[placeholder], [aria-label], [title], [data-label]')) {
                for (const name of ['placeholder', 'aria-label', 'title', 'data-label']) {
                    const value = el.getAttribute(name);
                    if (value && !el.closest('code, pre')) {
                        texts.push(value.trim());
                    }
                }
            }
            const unnamed = [...main.querySelectorAll('select, textarea, input:not([type=hidden]):not([type=submit]):not([type=button])')]
                .filter((el) => !(el.labels?.length || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title')))
                .map((el) => el.outerHTML.slice(0, 90));
            return { dir: document.documentElement.getAttribute('dir'), texts, unnamed };
        });
        const left = english(found.texts, authorsWords(pageProps));
        check(`${locale}${path}: right to left`, found.dir === 'rtl', `dir=${found.dir}`);
        check(`${locale}${path}: nothing in English`, left.length === 0, [...new Set(left)].slice(0, 12).join(' | '));
        check(`${locale}${path}: every field has a name`, found.unnamed.length === 0, found.unnamed.slice(0, 4).join(' | '));
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
