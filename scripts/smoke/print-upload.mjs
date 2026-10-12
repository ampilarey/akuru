/**
 * Can a customer upload their own name-tag artwork and get a proof, with
 * the file checked first, in Dhivehi? (NAME_TAGS_PLAN §3.4, §5.5, slice NT5,
 * STATUS §5rs.)
 *
 * The walk switches the print desk on for itself, then under /dv the
 * customer finds *Upload your design* on the door, uploads a 72-dpi picture
 * and is warned in Dhivehi that it is too small (refused: under 150 dpi),
 * uploads a 200-dpi one and is warned it may print soft, then a 300-dpi one
 * that passes; the proof is refused until *I have the right to print this*
 * is ticked; the real Chrome then draws the proof sheets. Local server only.
 *
 *   DOCUMENTS_CHROME_PATH=… node scripts/smoke/print-upload.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('print-upload changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

// The desk on, and its first layout's tag size to draw pictures for.
const saved = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->where('kind', 'glossy')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`));
const paperId = saved.paper.id;
tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => 4.50, 'price_bw' => 2.00, 'is_active' => true]); DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); Cache::flush(); echo 'ok';`);
const layout = JSON.parse(tinker(`echo json_encode(\\App\\Domains\\Bookshop\\Models\\PrintLayout::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first(['tag_w_mm', 'tag_h_mm']));`));
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
const dir = mkdtempSync(join(tmpdir(), 'print-upload-'));
const picture = (dpi) => {
    const path = join(dir, `tag-${dpi}.png`);
    const w = Math.ceil(layout.tag_w_mm / 25.4 * dpi);
    const h = Math.ceil(layout.tag_h_mm / 25.4 * dpi);
    tinker(`$i = imagecreatetruecolor(${w}, ${h}); imagefill($i, 0, 0, imagecolorallocate($i, 122, 31, 43)); imagefilledrectangle($i, (int) (${w} / 8), (int) (${h} / 3), (int) (${w} * 7 / 8), (int) (${h} * 2 / 3), imagecolorallocate($i, 233, 216, 180)); imagepng($i, '${path}'); echo 'ok';`);
    return path;
};
const say = (key, vars = '[]') => tinker(`echo __('${key}', ${vars}, 'dv');`);
const cleanUp = () => {
    tinker(`DB::table('print_jobs')->where('user_id', ${customerId})->where('source', 'upload')->where('created_at', '>=', now()->subHours(3))->delete(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => ${saved.paper.price_colour ?? 0}, 'price_bw' => ${saved.paper.price_bw ?? 0}, 'is_active' => ${saved.paper.is_active ? 1 : 0}]); ${saved.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${saved.enabled}']);`} Cache::flush(); echo 'ok';`);
};

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
const upload = async (file, dpi) => {
    await page.locator('[data-testid="print-upload-input"]').setInputFiles(file);
    await Promise.all([page.waitForResponse((r) => r.url().includes('/upload') && r.request().method() === 'POST'), page.locator('[data-testid="print-upload-send"]').click()]);
    await page.waitForLoadState('networkidle');
    // The page shows the new file's resolution once it has the job back.
    await page.waitForFunction((want) => Math.abs(Number(document.querySelector('[data-testid="print-upload-dpi"]')?.textContent) - want) <= 2, dpi, { timeout: 15000 }).catch(() => {});
};
const shows = (text) => page.waitForFunction((t) => document.body.innerText.includes(t), text, { timeout: 15000 }).then(() => true).catch(() => false);

try {
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(CUSTOMER);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);

    await page.goto(`${BASE}/dv/shop/print/name-tags`, { waitUntil: 'networkidle' });
    const door = page.locator('[data-testid="print-door-upload"]');
    check('/dv the door offers Upload your design, in Dhivehi', (await door.count()) === 1 && flat(await door.locator('h2').textContent()) === say('print.upload_door_heading'));
    await Promise.all([page.waitForURL(/\/shop\/print\/jobs\/\d+$/), door.locator('[data-testid="print-door-upload-start"]').click()]);
    await page.waitForLoadState('networkidle');
    check('an upload design opens, right to left, as a draft', await page.locator('html').getAttribute('dir') === 'rtl' && (await page.locator('[data-testid="print-upload-file"]').count()) === 1);

    await upload(picture(72), 72);
    const refusal = flat(await page.locator('[data-testid="print-upload-refusals"]').textContent().catch(() => ''));
    check('a 72-dpi picture is refused as too small, in Dhivehi', /\p{Script=Thaana}/u.test(refusal) && refusal.includes('72'), refusal.slice(0, 160));
    check('and the proof cannot be made', await page.locator('[data-testid="print-make-proof"]').isDisabled());

    await upload(picture(200), 200);
    const warning = flat(await page.locator('[data-testid="print-job-warnings"]').textContent().catch(() => ''));
    check('a 200-dpi picture is warned it may print soft, in Dhivehi', /\p{Script=Thaana}/u.test(warning) && warning.includes('200'), warning.slice(0, 160));

    await upload(picture(300), 300);
    check('a 300-dpi picture passes: no warning, no refusal', (await page.locator('[data-testid="print-job-warnings"]').count()) === 0 && (await page.locator('[data-testid="print-upload-refusals"]').count()) === 0 && Number(await page.locator('[data-testid="print-upload-dpi"]').textContent()) >= 300);
    await page.locator(`[data-testid="print-paper-${paperId}"]`).check();
    await page.locator('[data-testid="print-sheets"]').fill('2');
    await page.waitForFunction(() => document.querySelector('[data-testid="print-job-saved"]')?.textContent?.length > 0);
    await page.waitForTimeout(800);
    check('the server prices it: MVR 9.00', flat(await page.locator('[data-testid="print-price-total"]').textContent()).includes('9.00'));

    // The declaration is required.
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/proof') && r.request().method() === 'POST'), page.locator('[data-testid="print-make-proof"]').click()]);
    await page.waitForLoadState('networkidle');
    const needDeclaration = say('print.upload_error_declaration');
    check('the proof is refused without the declaration, in Dhivehi', (await shows(needDeclaration)) && (await page.locator('[data-testid="print-proof-sheets"]').count()) === 0, needDeclaration);
    await page.locator('[data-testid="print-upload-declare"]').check();
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/proof') && r.request().method() === 'POST', { timeout: 120000 }), page.locator('[data-testid="print-make-proof"]').click()]);
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('[data-testid="print-proof-sheets"]', { timeout: 60000 }).catch(() => {});
    await page.waitForFunction(() => [...document.querySelectorAll('[data-testid^="print-proof-"] img, img[data-testid^="print-proof-"]')].every((i) => i.complete && i.naturalWidth > 0), null, { timeout: 30000 }).catch(() => {});
    const sheets = await page.locator('img[data-testid^="print-proof-"]').evaluateAll((imgs) => imgs.map((i) => i.naturalWidth));
    check('with it, Chrome draws two watermarked proof sheets', sheets.length === 2 && sheets.every((w) => w > 700), JSON.stringify(sheets));
    check('and the design reads Proof ready', flat(await page.locator('[data-testid="print-job-status"]').textContent()) === say('print.job_status_proofed'));
    if (process.env.SMOKE_SHOT) await page.screenshot({ path: process.env.SMOKE_SHOT, fullPage: true });
} catch (e) {
    problems.push(String(e).slice(0, 300));
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
