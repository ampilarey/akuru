/**
 * Can a customer make name tags from a template and approve a proof, in
 * Dhivehi? (NAME_TAGS_PLAN §3, slice NT3, STATUS §5rq.)
 *
 * The walk switches the print desk on for itself (a glossy paper priced, the
 * service on), then under /dv a customer opens the door, picks a design,
 * types a Thaana name, chooses glossy colour and two sheets, sees the price
 * the server works out, makes the proof — the real Chrome renders it here —
 * and sees two watermarked sheets, finds no way to download anything, is
 * refused the print file, approves, and finds the design locked. The desk is
 * put back as it was and the walk's jobs are removed. Local server only.
 *
 *   DOCUMENTS_CHROME_PATH=… node scripts/smoke/print-design.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const NAME = 'އައިޝަތު ނަދާ';

if (!LOCAL) {
    console.log('print-design changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale, vars = '[]') => tinker(`echo __('${key}', ${vars}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

// The desk, as this walk needs it; put back afterwards.
const before = tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->where('kind', 'glossy')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`);
const saved = JSON.parse(before);
const paperId = saved.paper.id;
tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => 4.50, 'price_bw' => 2.00, 'is_active' => true]); DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); Cache::flush(); echo 'ok';`);
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
const cleanUp = () => {
    tinker(`DB::table('print_jobs')->where('user_id', ${customerId})->where('created_at', '>=', now()->subHours(3))->delete(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => ${saved.paper.price_colour ?? 0}, 'price_bw' => ${saved.paper.price_bw ?? 0}, 'is_active' => ${saved.paper.is_active ? 1 : 0}]); ${saved.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${saved.enabled}']);`} Cache::flush(); echo 'ok';`);
};

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

try {
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(CUSTOMER);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    check('the customer signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

    // The door.
    await page.goto(`${BASE}/dv/shop/print/name-tags`, { waitUntil: 'networkidle' });
    check('/dv the name-tags door opens, right to left, in Dhivehi', await page.locator('html').getAttribute('dir') === 'rtl' && flat(await page.locator('h1').first().textContent()) === say('print.door_title', 'dv'));
    const gallery = page.locator('[data-testid="print-door-gallery"] > li');
    check('Akuru\'s designs are shown, each drawn with a sample name', await gallery.count() >= 5 && (await page.locator('[data-testid^="print-door-thumb-"]').first().textContent()).includes(say('print.sample_name', 'dv')), String(await gallery.count()));

    await Promise.all([page.waitForURL(/\/shop\/print\/jobs\/\d+$/), gallery.first().locator('button').click()]);
    await page.waitForLoadState('networkidle');
    const jobId = Number(new URL(page.url()).pathname.split('/').pop());
    check('a design opens as the customer\'s own job, a draft', jobId > 0 && flat(await page.locator('[data-testid="print-job-status"]').textContent()) === say('print.job_status_draft', 'dv'));

    // The name, the paper, colour, two sheets: autosaved and priced by the server.
    await page.locator('[data-testid="print-field-name"]').fill(NAME);
    await page.locator('[data-testid="print-field-class"]').fill('3B');
    await page.locator(`[data-testid="print-paper-${paperId}"]`).check();
    await page.locator('[data-testid="print-colour"]').check();
    await page.locator('[data-testid="print-sheets"]').fill('2');
    await page.waitForFunction(() => document.querySelector('[data-testid="print-job-saved"]')?.textContent?.length > 0);
    await page.waitForTimeout(1500);
    check('the tag shows the Thaana name as it is typed', (await page.locator('[data-testid="print-job-tag"]').textContent()).includes(NAME));
    const total = flat(await page.locator('[data-testid="print-price-total"]').textContent());
    check('the price is the server\'s: glossy colour, 2 sheets, MVR 9.00', total.endsWith('9.00') && (await page.locator('[data-testid="print-job-price"]').textContent()).includes(say('print.price_total', 'dv')), total);
    check('the job is saved with the name, the paper and the sheets', tinker(`$j = DB::table('print_jobs')->find(${jobId}); echo json_decode($j->entry, true)['row']['name'] . '|' . $j->print_paper_option_id . '|' . $j->sheets . '|' . $j->price_total;`) === `${NAME}|${paperId}|2|9.00`);

    // The proof — rendered for real by Chrome on this server.
    await page.locator('[data-testid="print-make-proof"]').click();
    await page.waitForSelector('[data-testid="print-proof-sheets"]', { timeout: 60000 });
    await page.waitForLoadState('networkidle');
    await page.waitForFunction(() => [...document.querySelectorAll('img[data-testid^="print-proof-"]')].every((i) => i.complete && i.naturalWidth > 0), null, { timeout: 30000 }).catch(() => {});
    const loaded = await page.locator('img[data-testid^="print-proof-"]').evaluateAll((imgs) => imgs.map((i) => i.complete && i.naturalWidth));
    check('the proof shows two sheet pictures, drawn by Chrome', loaded.length === 2 && loaded.every((w) => w >= 700), JSON.stringify(loaded));
    check('the job is proofed, said in Dhivehi', flat(await page.locator('[data-testid="print-job-status"]').textContent()) === say('print.job_status_proofed', 'dv') && (await page.locator('body').textContent()).includes(say('print.flash_proofed', 'dv')));
    if (process.env.SMOKE_SHOT) await page.screenshot({ path: process.env.SMOKE_SHOT, fullPage: true });
    const src = await page.locator('img[data-testid="print-proof-1"]').getAttribute('src');
    check('each picture comes by a signed link that expires', /signature=/.test(src) && /expires=/.test(src), src.slice(0, 120));

    // No way to download, and no print file for the customer.
    const downloadLinks = await page.locator('a[download], a[href*=".pdf"], a[href*="/file"]').count();
    check('nothing on the page downloads the proof or the file', downloadLinks === 0, String(downloadLinks));
    const pdf = await page.request.get(`${BASE}/dv/admin/bookshop/print/jobs/${jobId}/file`, { maxRedirects: 0 });
    check('the print file is refused to the customer', pdf.status() === 403, String(pdf.status()));
    const unsigned = await page.request.get(`${BASE}/dv/shop/print/proof/${jobId}/1`, { maxRedirects: 0 });
    check('a proof picture without its signature is refused', unsigned.status() === 403, String(unsigned.status()));
    check('the print file exists for the office: a real PDF', tinker(`$d = DB::table('documents')->find(DB::table('print_jobs')->where('id', ${jobId})->value('print_document_id')); echo substr(Storage::disk('local')->get($d->media_path), 0, 4);`) === '%PDF');

    // Approve, then locked.
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="print-approve"]').click()]);
    await page.waitForSelector('[data-testid="print-job-locked"]');
    check('approved: the design is locked and its price held, in Dhivehi', flat(await page.locator('[data-testid="print-job-status"]').textContent()) === say('print.job_status_approved', 'dv') && flat(await page.locator('[data-testid="print-job-locked"]').textContent()).includes(say('print.locked_heading', 'dv')));
    check('the fields can no longer be typed in', await page.locator('[data-testid="print-field-name"]').isDisabled());
    const late = await page.evaluate(async ([url]) => {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ sheets: 5 }) });
        return [r.status, await r.text()];
    }, [`${BASE}/dv/shop/print/jobs/${jobId}`]);
    let refusal = '';
    try { refusal = JSON.parse(late[1]).errors?.job?.[0] ?? ''; } catch { refusal = ''; }
    check('a change sent anyway is refused, in Dhivehi', late[0] === 422 && refusal === say('print.error_locked', 'dv') && /\p{Script=Thaana}/u.test(refusal), `${late[0]} ${refusal.slice(0, 120)}`);

    // My designs.
    await page.goto(`${BASE}/dv/my-designs`, { waitUntil: 'networkidle' });
    check('My designs lists it as approved', (await page.locator(`[data-testid="print-design-${jobId}"]`).getAttribute('data-status')) === 'approved');
} finally {
    await browser.close();
    cleanUp();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
