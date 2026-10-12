/**
 * Can a customer design name tags freely, in Arabic? (NAME_TAGS_PLAN §3.3,
 * §7.1, slice NT6, STATUS §5rt.)
 *
 * The walk switches the print desk on for itself, then under /ar the
 * customer starts *Design your own* from the door, adds a rectangle and a
 * text in the designer, types Arabic into the text, turns it 12°,
 * sees each change saved and kept on reload, types a name, makes the proof
 * — the real Chrome draws it — and finds the template they did not use
 * untouched. Local server only.
 *
 *   DOCUMENTS_CHROME_PATH=… node scripts/smoke/print-freeform.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const CUSTOMER = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const ARABIC = 'مدرسة أكورو';

if (!LOCAL) {
    console.log('print-freeform changes the print desk with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key) => tinker(`echo __('${key}', [], 'ar');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const saved = JSON.parse(tinker(`echo json_encode(['enabled' => DB::table('settings')->where('key', 'bookshop_print_enabled')->value('value'), 'paper' => DB::table('print_paper_options')->where('kind', 'glossy')->first(['id', 'price_colour', 'price_bw', 'is_active'])]);`));
const paperId = saved.paper.id;
tinker(`\\App\\Domains\\Bookshop\\Support\\PrintDesk::ensure(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => 4.50, 'price_bw' => 2.00, 'is_active' => true]); DB::table('settings')->updateOrInsert(['key' => 'bookshop_print_enabled'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); Cache::flush(); echo 'ok';`);
const customerId = tinker(`echo DB::table('users')->where('email', '${CUSTOMER}')->value('id');`);
const templatesBefore = tinker(`echo md5(json_encode(DB::table('print_templates')->orderBy('id')->pluck('design')));`);
const cleanUp = () => {
    tinker(`DB::table('print_jobs')->where('user_id', ${customerId})->where('source', 'freeform')->where('created_at', '>=', now()->subHours(3))->delete(); DB::table('print_paper_options')->where('id', ${paperId})->update(['price_colour' => ${saved.paper.price_colour ?? 0}, 'price_bw' => ${saved.paper.price_bw ?? 0}, 'is_active' => ${saved.paper.is_active ? 1 : 0}]); ${saved.enabled === null ? "DB::table('settings')->where('key', 'bookshop_print_enabled')->delete();" : `DB::table('settings')->where('key', 'bookshop_print_enabled')->update(['value' => '${saved.enabled}']);`} Cache::flush(); echo 'ok';`);
};

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });
const savedOnce = async () => {
    await page.waitForResponse((r) => /\/shop\/print\/jobs\/\d+$/.test(new URL(r.url()).pathname) && r.request().method() === 'POST', { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(300);
};
const jobDesign = (id) => { try { return JSON.parse(tinker(`echo DB::table('print_jobs')->where('id', ${id})->value('design');`) || '{}'); } catch { return {}; } };

try {
    await page.goto(`${BASE}/ar/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="identifier"]').fill(CUSTOMER);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);

    await page.goto(`${BASE}/ar/shop/print/name-tags`, { waitUntil: 'networkidle' });
    const door = page.locator('[data-testid="print-door-freeform"]');
    check('/ar the door offers Design your own, right to left, in Arabic', await page.locator('html').getAttribute('dir') === 'rtl' && flat(await door.locator('h2').textContent()) === say('print.freeform_door_heading'));
    await Promise.all([page.waitForURL(/\/shop\/print\/jobs\/\d+$/), door.locator('[data-testid="print-door-freeform-start"]').click()]);
    await page.waitForLoadState('networkidle');
    const jobId = Number(new URL(page.url()).pathname.split('/').pop());
    await page.waitForSelector('[data-testid="print-designer"]', { timeout: 15000 }).catch(() => {});
    check('the designer loads (its own chunk) on the new design', (await page.locator('[data-testid="print-designer"]').count()) === 1 && (await page.locator('[data-testid="designer-toolbar"]').count()) === 1);

    // A shape first, so the text drawn after it sits on top.
    await page.locator('[data-testid="designer-add-rect"]').click();
    await savedOnce();
    await page.locator('[data-testid="designer-add-text"]').click();
    await page.locator('[data-testid="prop-text"]').fill(ARABIC);
    await page.locator('[data-testid="prop-rotate"]').fill('12');
    await savedOnce();
    await page.waitForTimeout(800);
    const design = jobDesign(jobId);
    const texts = (design.elements || []).filter((e) => e.type === 'text');
    check('the text, its Arabic and its 12° turn are saved by the server', texts.some((e) => e.text === ARABIC && Number(e.rotate) === 12), JSON.stringify(texts).slice(0, 200));
    check('and the rectangle', (design.elements || []).some((e) => e.type === 'rect'));

    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForSelector('[data-testid="print-designer"]', { timeout: 15000 }).catch(() => {});
    const layers = flat(await page.locator('[data-testid="designer-layers"]').textContent().catch(() => ''));
    check('a reload shows them still there', layers.includes(ARABIC), layers.slice(0, 160));

    await page.locator('[data-testid="print-field-name"]').fill('علي');
    await page.locator(`[data-testid="print-paper-${paperId}"]`).check();
    await savedOnce();
    await page.waitForTimeout(800);
    check('the server prices one sheet: 4.50', flat(await page.locator('[data-testid="print-price-total"]').textContent()).includes('4.50'));
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/proof') && r.request().method() === 'POST', { timeout: 120000 }), page.locator('[data-testid="print-make-proof"]').click()]);
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('img[data-testid="print-proof-1"]', { timeout: 60000 }).catch(() => {});
    await page.waitForFunction(() => { const i = document.querySelector('img[data-testid="print-proof-1"]'); return i && i.complete && i.naturalWidth > 0; }, null, { timeout: 30000 }).catch(() => {});
    const width = await page.locator('img[data-testid="print-proof-1"]').evaluate((i) => i.naturalWidth).catch(() => 0);
    check('Chrome draws the proof of the free design', width > 700, String(width));
    check('the design reads Proof ready, in Arabic', flat(await page.locator('[data-testid="print-job-status"]').textContent()) === say('print.job_status_proofed'));
    check('the templates are untouched', tinker(`echo md5(json_encode(DB::table('print_templates')->orderBy('id')->pluck('design')));`) === templatesBefore);
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
