/**
 * Can the office invite a vendor, and can a vendor open its shop and list a
 * product? (BOOKSHOP_PLAN slice B1a.)
 *
 * Three logins:
 *
 *   1. the office opens the Online Store screen, sees Fitrah and its
 *      owner, invites a new vendor and is shown the owner's one-time
 *      password once; the CSV has the vendors;
 *   2. that new owner signs in with the one-time password, is asked to
 *      choose their own, and meets the Vendor Agreement;
 *   3. Fitrah's owner finds "My shop" in the menu, accepts the agreement,
 *      sees Fitrah's products (and not another shop's), lists a new one with
 *      a photo, a variant and book details, is told plainly when a "was"
 *      price is too low, finds it by search and in the CSV, adds a member
 *      of staff, and reads the portal in Dhivehi.
 *
 * `SmokeMarkerSeeder::vendorCycle()` plants Fitrah (owner
 * `vendor@akuru.edu.mv`, staging only), a second shop with one product, and
 * clears what a run leaves: the invited vendors, the listed products and
 * their photos, the staff added.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/vendor.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_PASSWORD,
 * SMOKE_CHROMIUM.
 */
import { resolve } from 'node:path';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const RUN = Date.now().toString(36).slice(-5);
const INVITED = `SMOKE-Invited ${RUN}`;
const INVITED_EMAIL = `smoke-invited-${RUN}@example.test`;
const PRODUCT = `SMOKE-Walk Qaida ${RUN}`;
const STAFF_EMAIL = `smoke-staff-${RUN}@example.test`;
const PHOTO = resolve(process.cwd(), 'database/seeders/fixtures/vendors/fitrah-logo.jpg');

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
    const context = await browser.newContext();
    context.setDefaultNavigationTimeout(60000);
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

async function signIn(email, password = PASSWORD) {
    const page = await newPage(email);
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');

    return page;
}

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
// An Inertia visit is an XHR, so "networkidle" says nothing about it: wait
// for what the step expects to appear (or give up quietly and let the
// check report what is there).
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};

// ------------------------------------------------------------ 1. the office

const office = await signIn(ADMIN);
check('the office signs in', !office.url().includes('/login'), office.url());

await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
const fitrahRow = office.locator('[data-testid="vendor-row-fitrah"]');
check('the Online Store screen lists Fitrah with its owner', (await fitrahRow.count()) === 1 && (await fitrahRow.innerText()).includes(VENDOR), (await fitrahRow.count()) ? (await fitrahRow.innerText()).replace(/\s+/g, ' ') : (await text(office)).slice(0, 160));

await office.fill('[data-testid="invite-vendor"] [data-testid="vendor-name"]', INVITED);
await office.fill('[data-testid="owner-name"]', 'Smoke Invited Owner');
await office.fill('[data-testid="owner-email"]', INVITED_EMAIL);
await office.click('[data-testid="create-vendor"]');
await settle(office, '[data-testid="vendor-invite"]');
const invite = office.locator('[data-testid="vendor-invite"]');
const oneTime = (await office.locator('[data-testid="invite-password"]').count()) ? (await office.locator('[data-testid="invite-password"]').innerText()).trim() : '';
check('inviting a vendor shows the owner\'s one-time password once', (await invite.count()) === 1 && /^[A-Za-z0-9]{12}$/.test(oneTime), (await invite.count()) ? (await invite.innerText()).replace(/\s+/g, ' ').slice(0, 160) : (await text(office)).slice(0, 160));

await office.reload({ waitUntil: 'networkidle' });
check('and it is gone after a reload', (await office.locator('[data-testid="invite-password"]').count()) === 0);
const invitedSlug = INVITED.toLowerCase().replace(/[^a-z0-9]+/g, '-');
check('the new vendor is in the list with its address', (await office.locator(`[data-testid="vendor-row-${invitedSlug}"]`).count()) === 1, invitedSlug);

const vendorsCsv = await office.request.get(`${BASE}/en/admin/bookshop/vendors/export`);
const vendorsCsvText = await vendorsCsv.text();
check('the office can export the vendors', vendorsCsv.status() === 200 && vendorsCsvText.includes('Fitrah') && vendorsCsvText.includes(INVITED), `HTTP ${vendorsCsv.status()}`);

// ------------------------------------------------------ 2. the invited owner

const invited = await signIn(INVITED_EMAIL, oneTime);
check('the invited owner signs in with the one-time password', !invited.url().includes('/login'), invited.url());
await invited.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
check('is asked to choose their own password', (await invited.locator('[data-testid="set-password-notice"]').count()) === 1);
check('and meets the Vendor Agreement before anything else', (await invited.locator('[data-testid="agreement-form"]').count()) === 1 && (await invited.locator('[data-testid="product-list"]').count()) === 0);

// ------------------------------------------------------------ 3. the vendor

const vendor = await signIn(VENDOR);
check('Fitrah\'s owner signs in', !vendor.url().includes('/login'), vendor.url());

await vendor.goto(`${BASE}/en/portal/home`, { waitUntil: 'networkidle' });
const more = vendor.locator('button', { hasText: /^More/ }).first();
if (await more.count()) {
    await more.click();
    await vendor.waitForTimeout(300);
}
const shellLinks = await vendor.locator('#app-shell-more a, nav a').evaluateAll((els) => els.map((el) => [el.innerText.trim(), el.getAttribute('href') ?? '']));
check('the menu offers My shop', shellLinks.some(([label, href]) => label === 'My shop' && /\/vendor$/.test(href)), shellLinks.filter(([, h]) => /vendor/.test(h)).map(([l, h]) => `${l}→${h}`).join(' · ') || 'no vendor link');

await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
const agreement = vendor.locator('[data-testid="agreement-form"]');
const continueButton = agreement.locator('button[type=submit]');
check('the portal opens on the Vendor Agreement, with its page linked', (await agreement.count()) === 1 && /vendor-agreement$/.test((await vendor.locator('[data-testid="agreement-link"]').getAttribute('href')) ?? ''));
check('Continue waits for the tick', await continueButton.isDisabled());
await vendor.check('[data-testid="accept-agreement"]');
await continueButton.click();
await settle(vendor, '[data-testid="product-list"]');

const list = vendor.locator('[data-testid="product-list"]');
const listText = (await list.count()) ? (await list.innerText()).replace(/\s+/g, ' ') : '';
check('accepting opens the shop with Fitrah\'s products', listText.includes('Arabic Letters Tracing Book') && listText.includes('Wooden Alphabet Puzzle'), listText.slice(0, 160) || (await text(vendor)).slice(0, 160));
check('and never another shop\'s', !listText.includes('SMOKE-Other-Secret'));
check('a product at its warning level says low stock', /Kids Prayer Mat.*Low stock/.test(listText), listText.match(/Kids Prayer Mat[^]*?(Draft|For sale)/)?.[0] ?? '');

// A new product, with a photo, a variant and book details.
await vendor.click('[data-testid="new-product"]');
const editor = vendor.locator('[data-testid="product-editor"]');
await editor.locator('[data-testid="product-title"]').fill(PRODUCT);
await editor.locator('[data-testid="product-price"]').fill('95');
await editor.locator('[data-testid="product-status"]').selectOption('active');
await editor.locator('[data-testid="product-tax-class"]').selectOption('zero_rated');
await editor.locator('[data-testid="product-category"]').selectOption({ label: 'Qur’an and tajweed' });
await editor.locator('[data-testid="product-stock"]').fill('30');
await editor.locator('[data-testid="product-sku"]').fill(`WALK-${RUN}`);
await editor.locator('[data-testid="detail-author"]').fill('Akuru Press');
await editor.locator('[data-testid="add-variant"]').click();
await editor.locator('[data-testid="variant-name-0"]').fill('Paperback');
await editor.locator('[data-testid="variant-stock-0"]').fill('12');
await editor.locator('[data-testid="product-photos"]').setInputFiles(PHOTO);
await editor.locator('[data-testid="save-product"]').click();
const productSlug = PRODUCT.toLowerCase().replace(/[^a-z0-9]+/g, '-');
await settle(vendor, `[data-testid="product-row-${productSlug}"]`);

const newRow = vendor.locator(`[data-testid="product-row-${productSlug}"]`);
const newRowText = (await newRow.count()) ? (await newRow.innerText()).replace(/\s+/g, ' ') : '';
check('a new product is listed with its category, variant, price and stock', newRowText.includes('Qur’an and tajweed') && newRowText.includes('Paperback') && newRowText.includes('95.00') && newRowText.includes('30'), newRowText || (await text(vendor)).slice(0, 200));
const thumb = (await newRow.count()) ? await newRow.locator('img').first().getAttribute('src') : null;
check('with its photo', Boolean(thumb) && thumb.includes('shop-products/'), thumb ?? 'no image');
const thumbLoads = thumb ? await vendor.request.get(thumb.startsWith('http') ? thumb : `${BASE}${thumb}`) : null;
check('and the photo loads', thumbLoads?.status() === 200, thumbLoads ? `HTTP ${thumbLoads.status()}` : '');

// The office's rule, said to the vendor: a "was" price must be above the price.
await vendor.click(`[data-testid="edit-${productSlug}"]`);
const edit = vendor.locator('[data-testid="product-editor"]');
await edit.locator('input[type=number]').nth(1).fill('50');
await edit.locator('[data-testid="save-product"]').click();
await settle(vendor, '[data-testid="product-editor"] [role="alert"]');
check('a "was" price below the price is refused, plainly', /must be higher than the price/.test(await edit.innerText().catch(() => '')), (await edit.innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 160));
check('and the photo is there to rearrange', (await edit.locator('[data-testid="product-images"] img').count()) === 1);
await edit.locator('button', { hasText: 'Cancel' }).click();

await vendor.goto(`${BASE}/en/vendor?q=${encodeURIComponent('SMOKE-Walk')}`, { waitUntil: 'networkidle' });
const found = await vendor.locator('[data-testid^="product-row-"]').count();
check('search finds it and only it', found === 1 && (await vendor.locator(`[data-testid="product-row-${productSlug}"]`).count()) === 1, `${found} rows`);

const productsCsv = await vendor.request.get(`${BASE}/en/vendor/products/export`);
const productsCsvText = await productsCsv.text();
check('the products export has it and not another shop\'s', productsCsv.status() === 200 && productsCsvText.includes(PRODUCT) && !productsCsvText.includes('SMOKE-Other-Secret'), `HTTP ${productsCsv.status()}`);

// People.
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
const people = vendor.locator('[data-testid="add-member-form"]');
await people.locator('input').nth(0).fill('Smoke Staff');
await people.locator('input[type=email]').fill(STAFF_EMAIL);
await people.locator('button[type=submit]').click();
await settle(vendor, '[data-testid="temporary-password"]');
const staffPassword = vendor.locator('[data-testid="temporary-password"]');
check('the owner adds a member of staff and sees their one-time password', (await staffPassword.count()) === 1 && (await vendor.locator('[data-testid="members"]').innerText()).includes(STAFF_EMAIL));

// Dhivehi.
await vendor.goto(`${BASE}/dv/vendor`, { waitUntil: 'networkidle' });
const dv = await text(vendor);
check('the portal reads in Dhivehi', dv.includes('އާ ތަކެތި') && dv.includes('މި ފިހާރައިގެ މީހުން'), dv.slice(0, 120));

await finish();
