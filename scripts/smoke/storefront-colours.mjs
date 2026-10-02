/**
 * A shop's own colours reach its live page (STATUS §5mz).
 *
 * The owner, 2026-10-02: "when the vendor changes the colour of the vendor
 * page it's not changing, but when he changes the theme it is". A preset
 * always reads; a hand-picked background left the text on it unreadable,
 * Publish was disabled, and the live page kept the old colours. Now the
 * designer picks the readable text as the vendor picks, says so, checks
 * readability live, and offers one click to fix what is left.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/storefront-colours.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const GOLD = '#F5C542';

const browser = await chromium.launch({
    args: ['--disable-background-networking', '--disable-component-update', '--no-first-run', '--no-default-browser-check'],
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
    const failed = results.filter(([, ok]) => !ok).length;
    for (const [step, ok, detail] of results) {
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(84)} ${detail}`);
    }
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}

const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
context.setDefaultNavigationTimeout(60000);
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const page = await context.newPage();
page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
page.on('response', (response) => {
    if (response.status() >= 500) problems.push(`HTTP ${response.status()} ${response.url()}`);
});

const settle = async (selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(300);
};
// What the colour picker does in a browser: set the value and fire input/change.
const pick = async (slot, hex) => page.locator(`[data-testid="color-${slot}"]`).locator('xpath=preceding-sibling::input[@type="color"]').evaluate((el, v) => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, v.toLowerCase());
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
}, hex);
const typeColour = async (slot, hex) => page.fill(`[data-testid="color-${slot}"]`, hex);
const saveDraft = async () => {
    await Promise.all([page.waitForResponse((r) => r.url().includes('/storefront/draft'), { timeout: 20000 }).catch(() => null), page.locator('[data-testid="designer-form"] button[type="submit"]').first().click()]);
    await settle('[data-testid="flash-success"]');
};
const publish = async () => {
    await Promise.all([page.waitForResponse((r) => r.url().includes('/storefront/publish'), { timeout: 20000 }).catch(() => null), page.locator('[data-testid="publish-now"]').click()]);
    await settle('[data-testid="flash-success"]');
};
const liveVar = async (name) => {
    const html = await (await context.request.get(`${BASE}/en/shop/fitrah`)).text();
    return (new RegExp(`${name}:\\s*(#[0-9A-Fa-f]{6})`).exec(html) ?? [])[1] ?? null;
};

// ------------------------------------------------------------ the vendor, on a phone

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', VENDOR);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
await page.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await page.locator('[data-testid="accept-agreement"]').count()) > 0) {
    await page.check('[data-testid="accept-agreement"]');
    await page.click('[data-testid="agreement-form"] button[type=submit]');
    await settle();
}
await page.goto(`${BASE}/en/vendor/storefront`, { waitUntil: 'networkidle' });
await settle('[data-testid="colors"]');
check('the designer opens with the colour inputs', (await page.locator('[data-testid="color-primary"]').count()) === 1);

// 1. The owner's case: a light primary from the picker.
const textBefore = await page.inputValue('[data-testid="color-on_primary"]');
await pick('primary', GOLD);
await page.waitForTimeout(200);
const textAfter = await page.inputValue('[data-testid="color-on_primary"]');
check('picking a light primary switches the text on it to dark, and says so', (await page.inputValue('[data-testid="color-primary"]')).toUpperCase() === GOLD && textBefore === '#FFFFFF' && textAfter === '#15151A' && (await page.locator('[data-testid="contrast-auto"]').count()) === 1, `${textBefore} → ${textAfter}`);
check('the live check says every pair reads, before saving', (await page.locator('[data-testid="contrast-problems"]').count()) === 0 && (await page.locator('[data-testid="contrast-ok"]').count()) === 1);
await saveDraft();
check('saving says plainly that it saved, with nothing hard to read', /Draft saved\.$|Draft saved\b(?!.*hard to read)/.test(await page.locator('[data-testid="flash-success"]').innerText()), (await page.locator('[data-testid="flash-success"]').innerText()).slice(0, 80));
check('and Publish is open', (await page.locator('[data-testid="publish-now"]').count()) === 1 && !(await page.locator('[data-testid="publish-now"]').isDisabled()));
await publish();
check('publishing puts the gold on the live shop page, with dark text on it', (await liveVar('--sf-primary'))?.toUpperCase() === GOLD && (await liveVar('--sf-on-primary'))?.toUpperCase() === '#15151A', `${await liveVar('--sf-primary')} / ${await liveVar('--sf-on-primary')}`);

// 2. A vendor who types colours that clash: the check is live, and one click fixes it.
await typeColour('on_primary', '#F0F0F0');
await typeColour('accent', '#F2DCD3');
await page.waitForTimeout(200);
const live = await page.locator('[data-testid="contrast-problems"]').innerText().catch(() => '');
check('typing clashing colours shows the problem at once, with Make it readable', /Text on primary/.test(live) && (await page.locator('[data-testid="make-readable"]').count()) === 1, live.replace(/\s+/g, ' ').slice(0, 120));
await page.click('[data-testid="make-readable"]');
await page.waitForTimeout(200);
check('one click makes every pair readable', (await page.locator('[data-testid="contrast-problems"]').count()) === 0 && (await page.locator('[data-testid="contrast-ok"]').count()) === 1, `on_primary ${await page.inputValue('[data-testid="color-on_primary"]')}, accent ${await page.inputValue('[data-testid="color-accent"]')}`);
const fixedAccent = (await page.inputValue('[data-testid="color-accent"]')).toUpperCase();
await saveDraft();
await publish();
check('and it publishes: the shaded accent is on the live page', (await liveVar('--sf-accent'))?.toUpperCase() === fixedAccent, `${await liveVar('--sf-accent')}`);

// 3. A preset still works as before.
await page.click('[data-testid="preset-ocean"]');
await saveDraft();
await publish();
check('choosing a preset and publishing still works', (await liveVar('--sf-primary'))?.toUpperCase() === '#0F4C81', `${await liveVar('--sf-primary')}`);

await finish();
