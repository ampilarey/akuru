/**
 * Can a vendor make its page look like its brand, inside the Akuru frame?
 * (BOOKSHOP_PLAN slice B4: the storefront designer, part 1.)
 *
 * Fitrah's owner:
 *
 *   1. opens the designer from the portal: presets on offer, Akuru's own
 *      palette locked (the office has not marked Fitrah an Akuru partner);
 *   2. sets the kit's first colours — cream on dusty blue — saves, and is
 *      told which pairs are hard to read; Publish is held;
 *   3. corrects them to the checked palette, chooses the kit's fonts, adds
 *      the logo, a banner, the story, phone and Instagram, saves: the
 *      preview shows the real page in the new look, still marked a draft;
 *   4. publishes as "Brand look": v1 is live, and a guest sees the themed
 *      page — logo, tagline, "at Akuru Bookstore", the Verified badge, the
 *      story, the contact block — with the products under it;
 *   5. switches to the Forest preset and publishes v2, then rolls back to
 *      v1: v3 is live and the guest's page is dusty blue again.
 *
 * `SmokeMarkerSeeder::vendorCycle()` clears Fitrah's storefront and marks
 * her Verified.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/storefront.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_VENDOR, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const LOGO = new URL('../../database/seeders/fixtures/vendors/fitrah-logo.jpg', import.meta.url).pathname;
const STORY = 'SMOKE-We make learning materials for little hands.';
const PALETTE = { primary: '#7B9AA5', secondary: '#F2C778', accent: '#B0553F', page_bg: '#FBF7F1', card_bg: '#FFFFFF', text: '#2F3A40', on_primary: '#1A2226', on_accent: '#FFFFFF' };

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

async function newPage(label) {
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 } });
    context.setDefaultNavigationTimeout(60000);
    // Google Fonts is off-host and aborted like everything else: the page must read without it.
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${label}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (response) => {
        if (response.status() >= 500) {
            problems.push(`${label}: HTTP ${response.status()} ${response.url()}`);
        }
    });

    return page;
}

async function signIn(email) {
    const page = await newPage(email);
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const cssVar = async (page, name) => page.locator('[data-testid="storefront"]').evaluate((el, n) => getComputedStyle(el).getPropertyValue(n).trim(), name).catch(() => '');

async function setColors(page, colors) {
    for (const [slot, value] of Object.entries(colors)) {
        await page.fill(`[data-testid="color-${slot}"]`, value);
    }
}

async function saveDraft(page) {
    await page.click('[data-testid="save-draft"]');
    await settle(page, '[data-testid="flash-success"]');
}

// ------------------------------------------------------------ 1. the designer

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await vendor.locator('[data-testid="accept-agreement"]').count()) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, '[data-testid="open-designer"]');
}
await vendor.click('[data-testid="open-designer"]');
await settle(vendor, '[data-testid="designer-form"]');
check('the portal opens the storefront designer', /\/vendor\/storefront$/.test(vendor.url()) && (await vendor.locator('[data-testid="designer-heading"]').innerText()).includes('Fitrah'), vendor.url().replace(BASE, ''));
check('with six presets, Akuru\'s own locked', (await vendor.locator('[data-testid="presets"] button').count()) === 6 && (await vendor.locator('[data-testid="preset-akuru"]').isDisabled()) && !(await vendor.locator('[data-testid="preset-sand"]').isDisabled()));
check('and nothing published yet', (await text(vendor)).includes('Not published yet'));

// ------------------------------------------------------------ 2. colours that do not read

await setColors(vendor, { ...PALETTE, on_primary: '#FBF7F1', accent: '#EBAD99' });
await saveDraft(vendor);
const problemsBox = vendor.locator('[data-testid="contrast-problems"]');
check('saving cream on dusty blue names the two pairs that are hard to read', (await problemsBox.count()) === 1 && (await problemsBox.innerText()).includes('Text on primary / Primary') && (await problemsBox.innerText()).includes('Accent (buttons, links) / Page background'), (await problemsBox.count()) ? (await problemsBox.innerText()).replace(/\s+/g, ' ').slice(0, 160) : 'no problems shown');
check('and Publish is held', await vendor.locator('[data-testid="publish"]').isDisabled());

// ------------------------------------------------------------ 3. the brand look

await setColors(vendor, PALETTE);
await vendor.selectOption('[data-testid="font-heading"]', 'Bree Serif');
await vendor.selectOption('[data-testid="font-body"]', 'Inter');
await vendor.selectOption('[data-testid="shape-card"]', 'flat');
await vendor.setInputFiles('[data-testid="image-logo"]', LOGO);
await vendor.setInputFiles('[data-testid="image-banner"]', LOGO);
await vendor.fill('[data-testid="story"]', STORY);
await vendor.fill('[data-testid="contact-phone"]', '7000000');
await vendor.fill('[data-testid="social-instagram"]', 'instagram.com/fitrah.mv');
await saveDraft(vendor);
check('the corrected palette reads, with the logo, banner and story saved', (await vendor.locator('[data-testid="contrast-ok"]').count()) === 1 && (await vendor.locator('[data-testid="preview-logo"]').count()) === 1 && (await vendor.locator('[data-testid="preview-banner"]').count()) === 1, (await text(vendor)).slice(0, 160));

const frame = vendor.frameLocator('[data-testid="preview-frame"]');
await frame.locator('[data-testid="storefront"]').waitFor({ timeout: 20000 }).catch(() => {});
const previewText = (await frame.locator('body').innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the preview is the real page in the new look, marked a draft', previewText.includes('Preview') && previewText.includes(STORY) && (await frame.locator('[data-testid="storefront-logo"]').count()) === 1 && (await frame.locator('[data-testid="storefront"]').getAttribute('data-preview').catch(() => '')) === '1', previewText.slice(0, 160));

const guest = await newPage('guest');
await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
check('customers still see the plain page', (await guest.locator('[data-testid="storefront"]').count()) === 0 && (await text(guest)).includes('iman.noor.ihsan'));

// ------------------------------------------------------------ 4. publish

await vendor.fill('[data-testid="version-note"]', 'Brand look');
await vendor.click('[data-testid="publish"]');
await settle(vendor, '[data-testid="version-1"]');
check('publishing makes v1 live', (await vendor.locator('[data-testid="version-1"]').getAttribute('data-live').catch(() => '')) === '1' && (await vendor.locator('[data-testid="version-1"]').innerText()).includes('Brand look'));

await guest.goto(`${BASE}/en/shop/fitrah`, { waitUntil: 'networkidle' });
const page = await text(guest);
check('a guest sees the themed page: logo, name, tagline, at Akuru Bookstore, the Verified badge', (await guest.locator('[data-testid="storefront-logo"]').count()) === 1 && page.includes('Fitrah') && page.includes('iman.noor.ihsan') && page.includes('at Akuru Bookstore') && (await guest.locator('[data-testid="badge-verified"]').count()) === 1, page.slice(0, 160));
check('the story, phone and Instagram link', page.includes(STORY) && page.includes('7000000') && (await guest.locator('[data-testid="storefront-socials"] a[href*="instagram.com/fitrah.mv"]').count()) === 1);
const primary = await cssVar(guest, '--sf-primary');
check('in Fitrah\'s dusty blue, with Bree Serif headings', primary.toUpperCase() === PALETTE.primary && (await cssVar(guest, '--sf-font-heading')).includes('Bree Serif'), `${primary} / ${await cssVar(guest, '--sf-font-heading')}`);
check('with her products under it, and no preview banner', (await guest.locator('[data-testid="storefront"] [data-product="smoke-arabic-letters-tracing-book"]').count()) === 1 && (await guest.locator('[data-testid="preview-banner"]').count()) === 0);

// ------------------------------------------------------------ 5. versions

await vendor.click('[data-testid="preset-forest"]');
await saveDraft(vendor);
await vendor.click('[data-testid="publish"]');
await settle(vendor, '[data-testid="version-2"]');
await guest.reload({ waitUntil: 'networkidle' });
check('a second look publishes as v2 and the guest\'s page turns forest green', (await vendor.locator('[data-testid="version-2"]').getAttribute('data-live').catch(() => '')) === '1' && (await cssVar(guest, '--sf-primary')).toUpperCase() === '#1F5F3F', await cssVar(guest, '--sf-primary'));

await vendor.click('[data-testid="roll-back-1"]');
await settle(vendor, '[data-testid="version-3"]');
await guest.reload({ waitUntil: 'networkidle' });
check('rolling back to v1 publishes v3 and the guest\'s page is dusty blue again', (await vendor.locator('[data-testid="version-3"]').getAttribute('data-live').catch(() => '')) === '1' && (await vendor.locator('[data-testid="version-3"]').innerText()).includes('Rolled back to v1') && (await cssVar(guest, '--sf-primary')).toUpperCase() === PALETTE.primary, await cssVar(guest, '--sf-primary'));

await finish();
