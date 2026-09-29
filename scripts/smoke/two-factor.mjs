/**
 * Two-step sign-in (STATUS §5lk), walked on a phone as a person would:
 *
 *   1. My accounts links to Two-step sign-in, which is off;
 *   2. turning it on shows a QR code and the secret typed out; a wrong first
 *      code is refused, the right one turns it on and shows eight recovery
 *      codes, once;
 *   3. signing in with the password stops at the challenge, not signed in;
 *      a wrong code is refused, a code from the app lets them in;
 *   4. a recovery code lets them in once, and the page then counts seven;
 *   5. turning it off needs the password: a wrong one is refused, the right
 *      one turns it off, and a password alone signs in again.
 *
 * The codes are worked out here from the secret the page shows, as an
 * authenticator app would. Ends with two-step sign-in off, as it began.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/two-factor.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_LEARNER, SMOKE_PASSWORD, SMOKE_CHROMIUM,
 * SMOKE_SHOTS (a folder for screenshots).
 */
import { createHmac } from 'node:crypto';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const WHO = process.env.SMOKE_LEARNER ?? 'smoke-learner@akuru.edu.mv';

const HERMETIC_ARGS = [
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter,InterestFeedContentSuggestions',
    '--no-first-run',
    '--no-default-browser-check',
];

const browser = await chromium.launch({
    args: HERMETIC_ARGS,
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => {
        check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200));
        await finish();
    });
}

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

// RFC 6238, as an authenticator app works it out: HMAC-SHA1 over the 30-second step.
function totp(secret, offset = 0) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const bits = secret.replace(/[^A-Z2-7]/gi, '').toUpperCase().split('').map((c) => alphabet.indexOf(c).toString(2).padStart(5, '0')).join('');
    const key = Buffer.from((bits.match(/.{8}/g) ?? []).map((b) => parseInt(b, 2)));
    const counter = Buffer.alloc(8);
    counter.writeUInt32BE(Math.floor(Date.now() / 1000 / 30) + offset, 4);
    const hash = createHmac('sha1', key).update(counter).digest();
    const at = hash[19] & 0x0f;
    const value = ((hash[at] & 0x7f) << 24) | (hash[at + 1] << 16) | (hash[at + 2] << 8) | hash[at + 3];

    return String(value % 1_000_000).padStart(6, '0');
}

const path = (href) => (href || '').replace(BASE, '').replace(/^\/(en|dv|ar)(?=\/|$)/, '') || '/';
const shot = async (page, name) => process.env.SMOKE_SHOTS && page.screenshot({ path: `${process.env.SMOKE_SHOTS}/two-factor-${name}.png`, fullPage: true });

async function signIn() {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`HTTP ${response.status()} ${response.url()}`);
        }
    });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', WHO);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

async function answer(page, code) {
    await page.fill('[data-testid="two-factor-code"]', code);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('[data-testid="two-factor-submit"]')]);
}

const state = async (page) => (await page.locator('[data-testid="two-factor-state"]').innerText()).replace(/\s+/g, ' ').trim();
const noSideScroll = async (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);

// ------------------------------------------------------------ 1. off, and found
const me = await signIn();
check('signs in with the password alone while it is off', !path(me.url()).startsWith('/two-factor') && !path(me.url()).startsWith('/login'), path(me.url()));
await me.goto(`${BASE}/en/account/linked`, { waitUntil: 'networkidle' });
const link = me.locator('[data-testid="two-factor-link"]');
check('My accounts links to Two-step sign-in, marked off', (await link.count()) === 1 && /off/i.test(await link.innerText()), (await link.innerText().catch(() => '')).replace(/\s+/g, ' '));
await Promise.all([me.waitForURL(/\/account\/two-factor$/).catch(() => {}), link.click()]);
await me.waitForSelector('[data-testid="two-factor-state"]');
check('the page opens, off', path(me.url()) === '/account/two-factor' && /^Off\b/.test(await state(me)), `${path(me.url())} · ${await state(me)}`);

// ------------------------------------------------------------ 2. turned on
await me.click('[data-testid="two-factor-start"]');
await me.waitForSelector('[data-testid="two-factor-setup"]');
await me.waitForSelector('[data-testid="two-factor-qr"]', { timeout: 10000 }).catch(() => {});
const secret = (await me.locator('[data-testid="two-factor-secret"]').innerText()).replace(/\s+/g, '');
check('turning it on shows a QR code and the secret typed out', (await me.locator('[data-testid="two-factor-qr"]').count()) === 1 && /^[A-Z2-7]{32}$/.test(secret), `${secret.length} characters`);
check('the setup fits a phone, no sideways scroll', await noSideScroll(me));
await shot(me, 'setup');

await me.fill('[data-testid="two-factor-confirm-code"]', '000000');
await me.click('[data-testid="two-factor-confirm"]');
await me.waitForTimeout(1200);
check('a wrong first code is refused and it stays off', (await me.locator('[data-testid="two-factor-setup"]').count()) === 1 && /^Off\b/.test(await state(me)), await state(me));

// Last step's code, so the current one is still unused for the sign-in below.
await me.fill('[data-testid="two-factor-confirm-code"]', totp(secret, -1));
await me.click('[data-testid="two-factor-confirm"]');
await me.waitForSelector('[data-testid="recovery-codes"]', { timeout: 15000 }).catch(() => {});
const codes = (await me.locator('[data-testid="recovery-codes"] li').allInnerTexts()).map((c) => c.trim());
check('the right code turns it on and shows eight recovery codes', codes.length === 8 && /^On\b/.test(await state(me)) && /8/.test(await state(me)), `${codes.length} codes · ${await state(me)}`);
await shot(me, 'codes');
await me.reload({ waitUntil: 'networkidle' });
check('the recovery codes are shown once, not again on reload', (await me.locator('[data-testid="recovery-codes"]').count()) === 0);
await me.context().close();

// ------------------------------------------------------------ 3. the challenge
const next = await signIn();
check('the right password now stops at the challenge', path(next.url()) === '/two-factor-challenge' && (await next.locator('[data-testid="two-factor-form"]').count()) === 1, path(next.url()));
await next.goto(`${BASE}/en/account/two-factor`, { waitUntil: 'networkidle' });
check('and is not signed in yet', path(next.url()).startsWith('/login'), path(next.url()));
await next.goto(`${BASE}/en/two-factor-challenge`, { waitUntil: 'networkidle' });
check('the challenge is still waiting', (await next.locator('[data-testid="two-factor-form"]').count()) === 1, path(next.url()));
check('the challenge fits a phone, no sideways scroll', await noSideScroll(next));
await shot(next, 'challenge');
await answer(next, '123456');
check('a wrong code is refused', (await next.locator('[data-testid="two-factor-error"]').count()) === 1, (await next.locator('[data-testid="two-factor-error"]').innerText().catch(() => '')).trim());
await answer(next, totp(secret));
check('a code from the app signs them in', !/two-factor-challenge|login/.test(path(next.url())), path(next.url()));
await next.context().close();

// ------------------------------------------------------------ 4. a recovery code
const lost = await signIn();
await answer(lost, codes[0]);
check('a recovery code signs them in', !/two-factor-challenge|login/.test(path(lost.url())), path(lost.url()));
await lost.goto(`${BASE}/en/account/two-factor`, { waitUntil: 'networkidle' });
check('and is spent: seven left', /7/.test(await state(lost)), await state(lost));

// ------------------------------------------------------------ 5. turned off
await lost.fill('[data-testid="two-factor-password"]', 'not-the-password');
await lost.click('[data-testid="two-factor-disable"]');
await lost.waitForTimeout(1200);
check('turning it off with a wrong password is refused', /^On\b/.test(await state(lost)) && !/^Off\b/.test(await state(lost)), await state(lost));
await lost.fill('[data-testid="two-factor-password"]', PASSWORD);
await lost.click('[data-testid="two-factor-disable"]');
await lost.waitForSelector('[data-testid="two-factor-start"]', { timeout: 15000 }).catch(() => {});
check('the right password turns it off', /^Off\b/.test(await state(lost)), await state(lost));
await lost.context().close();

const after = await signIn();
check('and a password alone signs in again', !/two-factor-challenge|login/.test(path(after.url())), path(after.url()));
await after.context().close();

await finish();
