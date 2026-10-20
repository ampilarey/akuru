/**
 * Can the Bookstore manager run the print desk's office, in Dhivehi?
 * (NAME_TAGS_PLAN §4, slice NT1, STATUS §5ro.)
 *
 * Under /dv the system admin opens the print desk, adds a paper with a
 * Dhivehi name and a colour price and sees it listed in Dhivehi, adds a
 * subject set from three lists, and tries the service switch: where this
 * server has no Chrome for documents the switch is refused in Dhivehi; where
 * it has one, the service turns on and the product goes on sale, and the walk
 * turns it off again. The planted rows are removed. Local server only.
 *
 *   node scripts/smoke/print-office.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const MARK = 'SMOKE NT1';

if (!LOCAL) {
    console.log('print-office plants rows with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const cleanUp = () => tinker(`DB::table('print_paper_options')->where('name', 'like', '${MARK}%')->delete(); DB::table('print_subject_sets')->where('label', 'like', '${MARK}%')->delete(); echo 'ok';`);
cleanUp();
const canPrint = tinker(`echo app(\\App\\Support\\Contracts\\PdfConverterInterface::class)->enabled() && app(\\App\\Support\\Contracts\\PageImageInterface::class)->enabled() ? 'yes' : 'no';`) === 'yes';
const wasEnabled = tinker(`echo \\App\\Domains\\Bookshop\\Support\\PrintDesk::settings()['enabled'] ? 'yes' : 'no';`) === 'yes';
// It starts from the desk off and the product off sale, whatever an earlier walk left; the end puts the desk back.
tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '0', 'updated_at' => now(), 'created_at' => now()]); \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()?->update(['status' => 'draft']); Cache::flush(); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(STAFF);
await page.locator('input[name="password"]').fill(PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
check('the system admin signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

await page.goto(`${BASE}/dv/admin/bookshop/print`, { waitUntil: 'networkidle' });
check('/dv the print desk opens, right to left, in Dhivehi', await page.locator('html').getAttribute('dir') === 'rtl' && flat(await page.locator('h1').first().textContent()) === say('print.title', 'dv'));
check('it names the product as not on sale', (await page.locator('[data-testid="print-product"]').textContent()).includes(say('print.status_draft', 'dv')));

// A paper, priced in colour, active.
await page.locator('[data-testid="print-paper-name"]').fill(`${MARK} Pearl`);
await page.locator('[data-testid="print-paper-name-dv"]').fill('ޕާލް ކާޑު');
await page.locator('[data-testid="print-paper-price-bw"]').fill('0');
await page.locator('[data-testid="print-paper-price-colour"]').fill('4.50');
await page.locator('[data-testid="print-paper-active"]').check();
await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="print-paper-save"]').click()]);
await page.waitForTimeout(400);
const paperRow = page.locator('[data-testid="print-papers"] tbody tr', { hasText: `${MARK} Pearl` });
check('the paper is listed with its Dhivehi name, its colour price and active', await paperRow.count() === 1 && /ޕާލް ކާޑު/.test(await paperRow.textContent()) && /4\.50/.test(await paperRow.textContent()) && (await paperRow.textContent()).includes(say('print.active', 'dv')), flat(await paperRow.textContent().catch(() => '')).slice(0, 160));

// A subject set from three lists.
await page.locator('[data-testid="print-set-label"]').fill(`${MARK} Grade 5`);
await page.locator('[data-testid="print-set-en"]').fill('Dhivehi\nEnglish');
await page.locator('[data-testid="print-set-dv"]').fill('ދިވެހި\nއިނގިރޭސި');
await page.locator('[data-testid="print-set-ar"]').fill('الديفيهية');
await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="print-set-save"]').click()]);
await page.waitForTimeout(400);
const mismatch = flat(await page.locator('[data-testid="print-subject-set-form"]').textContent());
check('lists of different lengths are refused in Dhivehi', mismatch.includes(say('print.error_subjects_count', 'dv').split(':en')[0].trim()), mismatch.slice(-200));
await page.locator('[data-testid="print-set-ar"]').fill('الديفيهية\nالإنجليزية');
await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="print-set-save"]').click()]);
await page.waitForTimeout(400);
check('the subject set is listed with two subjects', await page.locator('[data-testid="print-subject_sets"] li', { hasText: `${MARK} Grade 5` }).count() === 1);

// The switch.
await page.locator('[data-testid="print-enabled"]').check();
await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="print-save-settings"]').click()]);
await page.waitForTimeout(400);
if (canPrint) {
    check('this server can print: the service turns on and the product goes on sale', tinker(`echo \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->status->value;`) === 'active');
    tinker(`app(\\App\\Domains\\Bookshop\\Actions\\Print\\SavePrintSettingsAction::class)->execute(['enabled' => ${wasEnabled ? 'true' : 'false'}, 'lead_days' => 2, 'min_sheets' => 1]); echo 'ok';`);
} else {
    const refusal = flat(await page.locator('[data-testid="print-enabled-error"]').textContent().catch(() => ''));
    check('this server cannot print: the switch is refused in Dhivehi', refusal === say('print.error_enable_no_chrome', 'dv') && /\p{Script=Thaana}/u.test(refusal), refusal.slice(0, 160));
    check('the product stays off sale', tinker(`echo \\App\\Domains\\Bookshop\\Support\\PrintDesk::product()->status->value;`) === 'draft');
}

await browser.close();
cleanUp();
if (!canPrint) tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '${wasEnabled ? 1 : 0}', 'updated_at' => now(), 'created_at' => now()]); Cache::flush(); echo 'ok';`);

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
