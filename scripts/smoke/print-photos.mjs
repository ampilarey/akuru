/**
 * Can a customer order a photo print on a phone, in Dhivehi?
 * (PRINT_PRODUCTS_PLAN PP5, STATUS §5tm.)
 *
 * With the print desk on, Photo prints on sale and the glossy paper priced
 * (set with tinker), at 390 × 844 under /dv the customer opens the photos
 * door — an upload, no designs to choose — and starts one. They upload a
 * 1800 × 1200 photo: it reads 300 dpi, nothing to warn, and fills the
 * print. They switch to *Fit the whole photo*; the job keeps it. Two prints
 * are counted on the 4 × 6 in size, and the server prices them. Nothing
 * scrolls sideways. Everything is put back. Local server only.
 *
 *   node scripts/smoke/print-photos.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CUSTOMER, SMOKE_PASSWORD, SMOKE_CHROMIUM, SMOKE_SHOTS.
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_CUSTOMER ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const SHOTS = process.env.SMOKE_SHOTS ?? null;

if (!LOCAL) {
    console.log('print-photos changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale, vars = '[]') => tinker(`echo __('${key}', ${vars}, '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

// A 1800 × 1200 photo, made with PHP's GD.
const dir = mkdtempSync(join(tmpdir(), 'pp5-'));
const photo = join(dir, 'photo.png');
writeFileSync(photo, Buffer.from(tinker(`$i = imagecreatetruecolor(1800, 1200); imagefill($i, 0, 0, imagecolorallocate($i, 15, 76, 129)); imagefilledellipse($i, 900, 600, 700, 700, imagecolorallocate($i, 122, 31, 43)); ob_start(); imagepng($i); echo base64_encode(ob_get_clean());`), 'base64'));

tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); echo 'ok';`);
const before = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->where('kind', 'glossy')->where('sheet_size', 'a4')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`));
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
const restore = () => tinker(`DB::table('print_jobs')->where('user_id', ${customerId})->where('created_at', '>=', now()->subHours(3))->whereIn('product_id', DB::table('products')->where('slug', 'print-photos')->pluck('id'))->delete(); DB::table('products')->where('slug', 'print-photos')->update(['status' => 'draft']); DB::table('print_paper_options')->where('id', ${before.paper.id})->update(['price_colour' => ${before.paper.price_colour ?? 0}, 'price_bw' => ${before.paper.price_bw ?? 0}, 'is_active' => ${before.paper.is_active ? 1 : 0}]); ${before.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${before.enabled}']);`} Cache::flush(); echo 'ok';`);
tinker(`DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); DB::table('products')->where('slug', 'print-photos')->update(['status' => 'active']); DB::table('print_paper_options')->where('id', ${before.paper.id})->update(['price_colour' => 6.00, 'price_bw' => 3.00, 'is_active' => true]); Cache::flush(); echo 'ok';`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
const sideways = () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const shot = async (name) => { if (SHOTS) await page.screenshot({ path: `${SHOTS}/${name}.png` }); };

try {
    await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(CUSTOMER);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
    check('the customer signs in', !new URL(page.url()).pathname.endsWith('/login'));

    await page.goto(`${BASE}/dv/shop/print/photos`, { waitUntil: 'networkidle' });
    check('/dv the photos door offers an upload, in Dhivehi', flat(await page.locator('[data-testid="print-door-upload"] h2').textContent()) === say('print.photo_door_heading', 'dv'));
    check('/dv and no designs or blank designer', await page.locator('[data-testid="print-door-freeform"]').count() === 0 && await page.locator('[data-testid="print-door-gallery"]').count() === 0);
    check('/dv the door does not scroll sideways at 390 px', await sideways() <= 1, `${await sideways()} px`);
    await shot('pp5-door-dv');

    await Promise.all([page.waitForURL(/\/shop\/print\/jobs\/\d+$/), page.locator('[data-testid="print-door-upload-start"]').click()]);
    const jobId = Number(new URL(page.url()).pathname.split('/').pop());
    check('a photo print opens as the customer\'s own job', jobId > 0);
    check('/dv one picture per print: no whole-sheet choice', await page.locator('[data-testid="print-upload-kind-sheet"]').count() === 0);

    await page.locator('[data-testid="print-upload-input"]').setInputFiles(photo);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="print-upload-send"]').click()]);
    await page.waitForSelector('[data-testid="print-upload-dpi"]');
    check('the photo reads 300 dpi on the 4 × 6 in print, with nothing to warn', flat(await page.locator('[data-testid="print-upload-dpi"]').textContent()) === '300' && await page.locator('[data-testid="print-job-warnings"]').count() === 0, flat(await page.locator('[data-testid="print-upload-dpi"]').textContent()));
    check('/dv it fills the print, said in Dhivehi', await page.locator('[data-testid="print-photo-fit-cover"]').isChecked() && flat(await page.locator('[data-testid="print-photo-fit"]').textContent()).includes(say('print.upload_fit_cover', 'dv')));
    const count = flat(await page.locator('[data-testid="print-photo-count"]').textContent());
    check('/dv two prints are counted on the sheet', count === say('print.photo_prints_count', 'dv', "['count' => 2]"), count);

    await Promise.all([page.waitForResponse((r) => r.url().endsWith(`/shop/print/jobs/${jobId}`) && r.request().method() === 'POST'), page.locator('[data-testid="print-photo-fit-contain"]').check()]);
    await page.waitForTimeout(300);
    const fit = tinker(`echo json_decode(DB::table('print_jobs')->where('id', ${jobId})->value('design'), true)['elements'][0]['fit'];`);
    check('switched to fit the whole photo, the job keeps it', fit === 'contain', fit);
    const priced = tinker(`echo DB::table('print_jobs')->where('id', ${jobId})->value('price_total');`);
    check('the server prices it from the glossy paper', Number(priced) > 0, priced);
    check('/dv the job page does not scroll sideways at 390 px', await sideways() <= 1, `${await sideways()} px`);
    await page.locator('[data-testid="print-job-options"]').scrollIntoViewIfNeeded();
    await shot('pp5-job-dv');
} finally {
    await browser.close();
    restore();
}

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
