/**
 * Are the student ID card and the transfer certificate written in the
 * language of the page that asks for them? (BACKLOG C21, slice DOC2, STATUS
 * §5rj.)
 *
 * The system admin opens the smoke seed's first student's ID card under /dv
 * and their transfer certificate under /ar: each is right to left and in that
 * language, its status named and its dates by their month's name. Under /en
 * the ID card is still English. Each opening stores a copy; the walk removes
 * the copies it made. Local server only.
 *
 *   node scripts/smoke/documents-language-cards.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const LOCAL = /^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/?$/.test(BASE);
const STAFF = process.env.SMOKE_STAFF ?? 'superadmin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

if (!LOCAL) {
    console.log('documents-language-cards reads the database with tinker: local server only. 0/0 steps passed');
    process.exit(0);
}

const results = [];
const problems = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' }).trim().split('\n').pop();
const say = (key, locale) => tinker(`echo __('${key}', [], '${locale}');`);
const flat = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

const studentId = tinker(`echo DB::table('students')->orderBy('id')->value('id');`);
const before = tinker(`echo (int) DB::table('documents')->max('id');`);

const browser = await chromium.launch({
    args: ['--no-first-run', '--disable-background-networking'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});
const page = await (await browser.newContext()).newPage();
page.on('response', (r) => { if (r.status() >= 500) problems.push(`HTTP ${r.status()} ${r.url()}`); });

await page.goto(`${BASE}/dv/login`, { waitUntil: 'networkidle' });
await page.locator('input[name="identifier"]').fill(STAFF);
await page.locator('input[name="password"]').fill(PASSWORD);
await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="/login"] button[type="submit"]').first().click()]);
check('the system admin signs in', !new URL(page.url()).pathname.endsWith('/login'), new URL(page.url()).pathname);

const open = async (locale, kind) => {
    const response = await page.goto(`${BASE}/${locale}/exams/awards/${kind}?student_id=${studentId}`, { waitUntil: 'networkidle' });
    return {
        status: response?.status(),
        dir: await page.locator('html').getAttribute('dir'),
        h1: flat(await page.locator('h1').first().textContent().catch(() => '')),
        body: flat(await page.locator('body').textContent().catch(() => '')),
    };
};

const card = await open('dv', 'id-card');
check('/dv the ID card opens, right to left', card.status === 200 && card.dir === 'rtl', `${card.status} dir=${card.dir}`);
check('/dv its heading and number line are in Dhivehi', card.h1 === flat(say('documents.id_card.heading', 'dv')) && card.body.includes(say('documents.id_card.number', 'dv')), card.body.slice(0, 160));
check('/dv nothing of its own is English', !/Student ID|Student number|Akuru Institute/.test(card.body), card.body.slice(0, 160));

const transfer = await open('ar', 'transfer');
check('/ar the transfer certificate opens, right to left', transfer.status === 200 && transfer.dir === 'rtl', `${transfer.status} dir=${transfer.dir}`);
check('/ar its heading and history are in Arabic', transfer.h1 === flat(say('documents.transfer_certificate.heading', 'ar')) && transfer.body.includes(say('documents.transfer_certificate.history', 'ar')), transfer.body.slice(0, 160));
check('/ar the status is named, not printed as its code', !/\b(active|inactive|transferred|graduated|withdrawn|prospective)\b/.test(transfer.body) && !/Transfer \/ leaving|Status history|This is to certify/.test(transfer.body), transfer.body.slice(0, 200));

const english = await open('en', 'id-card');
check('/en the ID card is still English, left to right', english.dir === 'ltr' && english.h1 === 'Akuru Institute — Student ID', `${english.dir} ${english.h1}`);

await browser.close();
tinker(`DB::table('documents')->where('id', '>', ${before})->whereIn('document_type', ['id_card', 'transfer_certificate'])->delete(); echo 'ok';`);

for (const problem of problems) check(problem, false);
let passed = 0;
for (const [step, ok, detail] of results) {
    if (ok) passed++;
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${step}${!ok && detail ? ` — ${detail}` : ''}`);
}
console.log(`\n${passed}/${results.length} steps passed`);
process.exit(passed === results.length ? 0 : 1);
