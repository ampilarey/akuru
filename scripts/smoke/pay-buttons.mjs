/**
 * Does a Pay button on an Inertia page reach the bank? (STATUS §5px)
 *
 * The family's fees page and the learner's catalogue post Pay and Enroll as
 * Inertia visits, which are XHRs. The server answered with a redirect to the
 * bank, the XHR followed it across origins, and the browser refused: the
 * button did nothing at all. `fees.mjs` and `buy.mjs` assert the buttons are
 * there and never press them, because no environment has a gateway
 * (`OWNER_ACTIONS` item 2) — which is how it went unseen.
 *
 * So this walk brings its own. It starts a stand-in for BML's transaction
 * API on 127.0.0.1:8011 and its own dev server on 127.0.0.1:8013 told to use
 * it (`BML_BASE_URL`), so it neither needs nor disturbs the server the other
 * walks use. The stand-in answers each transaction with a page on
 * `bank.example`, which the browser is handed by Playwright. The parent
 * presses Pay on `SMOKE-INV-1`, the student presses Enroll on
 * `SMOKE-Pay-Course`, and each must end on the bank's page — the document,
 * at the bank's address — having asked the bank for the right amount.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/pay-buttons.mjs
 *
 * Locally only: it starts PHP itself, so against another host it says so
 * and stops. Environment: SMOKE_PARENT, SMOKE_STUDENT, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const BANK_API_PORT = 8011;
const PORT = 8013;
const BASE = `http://127.0.0.1:${PORT}`;
const BANK_PAGE = 'https://bank.example/pay/';

if (process.env.SMOKE_BASE_URL && !/127\.0\.0\.1|localhost/.test(process.env.SMOKE_BASE_URL)) {
    console.log('pay-buttons starts its own local server and stand-in bank; skipped against', process.env.SMOKE_BASE_URL);
    process.exit(0);
}

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

// What the stand-in bank was asked for: one transaction per press.
const transactions = [];
const bank = createServer((request, response) => {
    let body = '';
    request.on('data', (chunk) => { body += chunk; });
    request.on('end', () => {
        if (request.method === 'POST' && request.url.endsWith('/transactions')) {
            const payload = JSON.parse(body || '{}');
            transactions.push(payload);
            response.writeHead(200, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ id: `WALK-${payload.localId}`, url: `${BANK_PAGE}${payload.localId}`, state: 'CREATED' }));

            return;
        }
        response.writeHead(404, { 'Content-Type': 'application/json' });
        response.end('{"error":"not found"}');
    });
});
await new Promise((resolve) => bank.listen(BANK_API_PORT, '127.0.0.1', resolve));

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: join(ROOT, 'public'),
    env: {
        ...process.env,
        PHP_CLI_SERVER_WORKERS: '4',
        BML_BASE_URL: `http://127.0.0.1:${BANK_API_PORT}`,
        BML_API_KEY: 'walk-stand-in',
        BML_APP_ID: 'walk-stand-in',
        BML_AUTH_MODE: 'raw',
    },
    stdio: 'ignore',
});

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--disable-component-update', '--no-first-run', '--no-default-browser-check'],
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => {
        check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200));
        await finish();
    });
}

async function finish() {
    const failed = results.filter(([, ok]) => !ok).length;
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(84)} ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    server.kill();
    bank.close();
    process.exit(failed === 0 ? 0 : 1);
}

// The walk's own server takes a moment to answer.
for (let tries = 0; tries < 60; tries++) {
    const ready = await fetch(`${BASE}/en/login`).then((response) => response.ok).catch(() => false);
    if (ready) break;
    await new Promise((resolve) => setTimeout(resolve, 500));
}

async function signIn(email) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => {
        const url = route.request().url();
        if (url.startsWith(BANK_PAGE)) {
            return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>Stand-in bank</title><h1 data-bank>STAND-IN BANK</h1>' });
        }

        return url.startsWith(BASE) ? route.continue() : route.abort();
    });
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${email}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${email}: HTTP ${response.status()} ${response.url()}`);
        }
    });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

/** Press, then wait for the browser to be somewhere else or give up. */
async function pressAndFollow(page, button) {
    const asked = transactions.length;
    await Promise.all([page.waitForURL((url) => url.href.startsWith(BANK_PAGE), { timeout: 15000 }).catch(() => {}), button.click()]);
    await page.waitForTimeout(500);

    return {
        atBank: page.url().startsWith(BANK_PAGE) && (await page.locator('[data-bank]').count()) === 1,
        asked: transactions.slice(asked),
        where: page.url().startsWith(BANK_PAGE) ? 'the bank' : new URL(page.url()).pathname,
    };
}

// 1. The family's fees: SMOKE-INV-1, 300 billed, 100 paid, on a plan.
const parent = await signIn(PARENT);
await parent.goto(`${BASE}/en/portal/invoices`, { waitUntil: 'networkidle' });
const row = parent.locator('tr', { hasText: 'SMOKE-INV-1' });
const full = row.locator('button', { hasText: /^Pay (?!next)/ });
check('the parent sees SMOKE-INV-1 with a button to pay it in full', (await full.count()) === 1, (await row.count()) ? (await row.first().innerText()).replace(/\s+/g, ' ').slice(0, 120) : 'no row');
if ((await full.count()) === 1) {
    const balance = Number(((await full.innerText()).match(/[\d.]+/) ?? ['0'])[0]);
    const paid = await pressAndFollow(parent, full);
    check('pressing Pay takes the browser to the bank, as the page itself', paid.atBank, paid.where);
    check('and the bank was asked for the balance, in laari', paid.asked.length === 1 && paid.asked[0].amount === Math.round(balance * 100), JSON.stringify(paid.asked.map((t) => t.amount)));
}
await parent.context().close();

// 2. A paid course from the learner's catalogue.
const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/learn/catalog`, { waitUntil: 'networkidle' });
const course = student.locator('tr', { hasText: 'SMOKE-Pay-Course' });
const enroll = course.locator('button', { hasText: /Enroll — MVR/ });
check('the student sees SMOKE-Pay-Course with Enroll for its fee', (await enroll.count()) === 1, (await course.count()) ? (await course.first().innerText()).replace(/\s+/g, ' ').slice(0, 120) : 'no row');
if ((await enroll.count()) === 1) {
    const enrolled = await pressAndFollow(student, enroll);
    check('pressing Enroll takes the browser to the bank, as the page itself', enrolled.atBank, enrolled.where);
    check('and the bank was asked for MVR 150, in laari', enrolled.asked.length === 1 && enrolled.asked[0].amount === 15000, JSON.stringify(enrolled.asked.map((t) => t.amount)));
}
await student.context().close();

await finish();
