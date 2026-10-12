/**
 * Can the Bookstore manager make a name-tag template in the designer, in
 * Dhivehi? (NAME_TAGS_PLAN §4.7, §7.1, slice NT2, STATUS §5rp.)
 *
 * Under /dv the system admin opens the templates list and sees the five
 * shipped designs drawn with the sample fields; opens the designer, adds a
 * text, types a Thaana name and marks it as the name field, drags it on
 * the tag, saves, sees the template's sample sheet, makes it active, and
 * retires it. The planted template is removed. Local server only.
 *
 *   node scripts/smoke/print-templates.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const MARK = 'SMOKE NT2';

if (!LOCAL) {
    console.log('print-templates plants rows with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const cleanUp = () => tinker(`DB::table('print_templates')->where('title', 'like', '${MARK}%')->delete(); echo 'ok';`);
cleanUp();

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
page.on('pageerror', (e) => problems.push(`page error: ${String(e).slice(0, 140)}`));
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(STAFF);
await page.locator('input[name="password"]').fill(PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
check('the system admin signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

// The list: five shipped designs, each drawn with the Dhivehi sample name.
await page.goto(`${BASE}/dv/admin/bookshop/print/templates`, { waitUntil: 'networkidle' });
check('/dv the templates list opens, right to left, in Dhivehi', await page.locator('html').getAttribute('dir') === 'rtl' && flat(await page.locator('h1').first().textContent()) === say('print.templates_title', 'dv'));
const cards = page.locator('[data-testid="templates-list"] > li');
check('the five shipped templates are listed, active', await cards.count() >= 5 && await page.locator('[data-testid="templates-list"] > li[data-status="active"]').count() >= 5, String(await cards.count()));
const thumbs = await page.locator('[data-testid^="template-thumb-"]').evaluateAll((els) => els.map((el) => el.textContent));
check('each is drawn with the sample name in Thaana', thumbs.length >= 5 && thumbs.every((s) => s.includes(say('print.sample_name', 'dv'))), thumbs[0]?.slice(0, 60));

// The designer: a text, a Thaana name as the name field, dragged across the tag, saved.
await page.locator('[data-testid="templates-new"]').click();
await page.waitForURL(/\/print\/templates\/new$/);
await page.waitForLoadState('networkidle');
check('the designer opens', await page.locator('[data-testid="print-designer"]').count() === 1 && new URL(page.url()).pathname.endsWith('/print/templates/new'));
await page.locator('[data-testid="template-title"]').fill(`${MARK} Crest`);
await page.locator('[data-testid="template-title-dv"]').fill('ސްމޯކް ކްރެސްޓް');
await page.locator('[data-testid="template-layout"]').selectOption({ index: 1 });
await page.locator('[data-testid="designer-add-rect"]').click();
await page.locator('[data-testid="designer-add-text"]').click();
await page.locator('[data-testid="prop-field"]').selectOption('name');
await page.locator('[data-testid="prop-text"]').fill('ނަން: {name}');
const drawn = flat(await page.locator('[data-testid="designer-frame"] [data-type="text"]').textContent());
check('a Thaana text marked as the name field shows the sample name on the tag', drawn.includes(say('print.sample_name', 'dv')) && drawn.includes('ނަން:') && (await page.locator('[data-testid="designer-fields"]').textContent()).includes('{name}'), drawn);

if (process.env.SMOKE_SHOT) await page.screenshot({ path: process.env.SMOKE_SHOT, fullPage: true });
const before = Number(await page.locator('[data-testid="prop-y"]').inputValue());
const box = await page.locator('[data-testid="designer-frame"] [data-type="text"] rect[style*="cursor: move"]').boundingBox();
await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
await page.mouse.down();
await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2 + 40, { steps: 6 });
await page.mouse.up();
const after = Number(await page.locator('[data-testid="prop-y"]').inputValue());
check('dragging the text moves it down the tag, in millimetres', after > before, `${before} → ${after}`);
await page.locator('[data-testid="designer-undo"]').click();
check('undo puts it back', Number(await page.locator('[data-testid="prop-y"]').inputValue()) === before, await page.locator('[data-testid="prop-y"]').inputValue());

await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="template-save"]').click()]);
await page.waitForTimeout(400);
const id = tinker(`echo DB::table('print_templates')->where('title', '${MARK} Crest')->value('id') ?? 0;`);
check('the template is saved as a draft and the designer reopens on it', Number(id) > 0 && new URL(page.url()).pathname.endsWith(`/print/templates/${id}`) && flat(await page.locator('[data-testid="template-status"]').textContent()) === say('print.template_status_draft', 'dv'), new URL(page.url()).pathname);
check('its design keeps the Thaana text as the name field', tinker(`$d = json_decode(DB::table('print_templates')->where('id', ${id})->value('design'), true); echo collect($d['elements'])->where('type', 'text')->first()['field'] . ' ' . count($d['elements']) . ' ' . $d['fields'][0]['key'];`) === 'name 2 name');

// The sample sheet, drawn with the samples, in the browser.
const sheet = await page.request.get(`${BASE}/dv/admin/bookshop/print/templates/${id}/sheet`);
const html = await sheet.text();
check('the sample sheet draws a tag per cell with the sample name', sheet.status() === 200 && (html.match(/class="tag"/g) || []).length === 8 && html.includes(say('print.sample_name', 'dv')) && !html.includes('{name}'), `${sheet.status()} tags=${(html.match(/class="tag"/g) || []).length}`);

// Active, then retired — never deleted.
await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="template-activate"]').click()]);
await page.waitForTimeout(400);
check('the template goes active, said in Dhivehi', flat(await page.locator('[data-testid="template-status"]').textContent()) === say('print.template_status_active', 'dv') && (await page.locator('body').textContent()).includes(say('print.flash_template_status', 'dv')));
await Promise.all([page.waitForLoadState('networkidle'), page.locator('[data-testid="template-retire"]').click()]);
await page.waitForTimeout(400);
check('retired, it still exists', flat(await page.locator('[data-testid="template-status"]').textContent()) === say('print.template_status_retired', 'dv') && tinker(`echo DB::table('print_templates')->where('id', ${id})->exists() ? 'yes' : 'no';`) === 'yes');

await browser.close();
cleanUp();

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
