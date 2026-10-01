/**
 * Can Akuru pack and deliver for a shop, and charge for it?
 * (COMMERCE_PARITY_PLAN P6a.)
 *
 * `SMOKE-Akuru Packs` is seeded with Akuru packing and delivering, and ten of
 * its notebooks on Akuru's shelf.
 *
 *   1. the customer adds a notebook: the checkout offers *Delivered by Akuru*
 *      at Akuru's fee, and no shop courier; pays from the wallet;
 *   2. the office's Akuru page lists the order to pack, and the shelf one
 *      down; the office starts it and gives it to the seeded driver (P6b);
 *   3. the driver, told in the app it is theirs (P8b), on a phone marks it picked up and delivered with a
 *      photo; the office can open the photo, the customer cannot;
 *   4. the customer's order says Delivered; the CSV has it.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/akuru.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_CHROMIUM, SMOKE_ADMIN, SMOKE_STUDENT, SMOKE_PASSWORD.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'superadmin@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const DRIVER = process.env.SMOKE_DRIVER ?? 'driver@akuru.edu.mv';
const NOTEBOOK = 'smoke-akuru-packed-notebook';
const SHOP = 'smoke-akuru-packs';

const browser = await chromium.launch(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {});
const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

async function finish() {
    const width = Math.max(...results.map(([step]) => step.length));
    for (const [step, ok, detail] of results) console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
    console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');
    const failed = results.filter(([, ok]) => !ok).length;
    console.log(`\n${results.length - failed}/${results.length} steps passed.`);
    await browser.close();
    process.exit(failed === 0 ? 0 : 1);
}
for (const event of ['unhandledRejection', 'uncaughtException']) {
    process.on(event, async (error) => { check('the walk reached its end', false, String(error?.message ?? error).split('\n')[0].slice(0, 200)); await finish(); });
}

async function signIn(email) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
    const page = await context.newPage();
    page.on('pageerror', (error) => problems.push(`${email}: page error: ${String(error).slice(0, 140)}`));
    page.on('response', (r) => { if (r.status() >= 500) problems.push(`${email}: HTTP ${r.status()} ${r.url()}`); });
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);

    return page;
}
const submit = async (page, selector) => {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click(selector)]);
};
const settle = async (page, selector) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
};

// ------------------------------------------------------------ 1. the customer
const customer = await signIn(STUDENT);
await customer.goto(`${BASE}/en/shop/products/${NOTEBOOK}`, { waitUntil: 'networkidle' });
await submit(customer, '[data-testid="add-to-cart"]');
await customer.goto(`${BASE}/en/shop/checkout`, { waitUntil: 'networkidle' });
const group = customer.locator(`[data-testid="delivery-${SHOP}"]`);
const akuruOption = group.locator('input[data-delivery-kind="akuru_courier"]');
const groupText = (await group.innerText().catch(() => '')).replace(/\s+/g, ' ');
check('the checkout offers delivery by Akuru, at Akuru\'s fee, and no shop courier', (await akuruOption.count()) === 1 && /Delivered by Akuru/.test(groupText) && /30\.00/.test(groupText) && (await group.locator('input[data-delivery-kind="courier_male"]').count()) === 0, groupText.slice(0, 160));

if (await customer.locator('[data-testid="saved-address"]').count()) {
    await customer.locator('[data-testid="saved-address"]').first().check();
} else {
    await customer.fill('[data-testid="recipient-name"]', 'Smoke Customer');
    await customer.fill('[data-testid="phone"]', '7700000');
    await customer.fill('[data-testid="atoll"]', 'K');
    await customer.fill('[data-testid="island"]', 'Malé');
    await customer.fill('[data-testid="street"]', 'M. Smoke Villa');
}
for (const other of await customer.locator('[data-testid^="delivery-"]').all()) {
    const radio = other.locator('input[type=radio]:not([disabled])').first();
    if ((await other.getAttribute('data-testid')) !== `delivery-${SHOP}` && (await radio.count())) await radio.check();
}
await akuruOption.check();
await customer.check('[data-testid="pay-wallet"]');
await submit(customer, '[data-testid="place-order"]');
const orderNumber = (await customer.locator('[data-testid="checkout-orders"] [data-order]').evaluateAll((els) => els.map((el) => el.getAttribute('data-order')))).find((n) => n.endsWith('-SAK')) ?? '';
check('paid from the wallet, with an order for the shop Akuru packs for', (await customer.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid' && orderNumber !== '', orderNumber || customer.url().replace(BASE, ''));

// ------------------------------------------------------------ 2. the office packs and delivers
const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
check('the Bookstore office links to Akuru fulfilment', (await office.locator('[data-testid="open-akuru"]').count()) === 1);
await office.goto(`${BASE}/en/admin/bookshop/akuru`, { waitUntil: 'networkidle' });
const row = office.locator(`[data-testid="akuru-order-${orderNumber}"]`);
await settle(office, `[data-testid="akuru-order-${orderNumber}"]`);
check('the order waits in the office\'s queue with what is in it', (await row.count()) === 1 && /Akuru-Packed Notebook/.test(await row.innerText()), (await row.innerText().catch(() => 'not listed')).replace(/\s+/g, ' ').slice(0, 140));
const shelf = office.locator('[data-testid^="akuru-at-"]').filter({ hasText: /^9$/ });
check('and the notebook left Akuru\'s shelf: 9 of 10 there now', (await shelf.count()) >= 1);

// P6b: the office starts it and gives it to the driver.
await office.locator(`[data-testid="akuru-order-${orderNumber}"] [data-testid$="-processing"]`).first().click().catch(() => {});
await office.waitForLoadState('networkidle').catch(() => {});
await office.reload({ waitUntil: 'networkidle' });
const pick = office.locator(`[data-testid="akuru-order-${orderNumber}"] [data-testid^="driver-select-"]`);
await pick.selectOption({ label: 'SMOKE Driver' }).catch(() => {});
await office.locator(`[data-testid="akuru-order-${orderNumber}"] [data-testid^="driver-assign-"]`).click().catch(() => {});
await settle(office, `[data-testid="akuru-order-${orderNumber}"] [data-testid^="order-driver-"]`);
check('the office starts it and gives it to the driver', /SMOKE Driver/.test(await office.locator(`[data-testid="akuru-order-${orderNumber}"] [data-testid^="order-driver-"]`).innerText().catch(() => '')));

// ------------------------------------------------------------ 3. the driver, on a phone
const driver = await signIn(DRIVER);
check('the driver signs in to their deliveries', /\/deliveries/.test(driver.url()) || (await driver.locator('a[href$="/deliveries"]').count()) > 0, driver.url().replace(BASE, ''));
await driver.setViewportSize({ width: 390, height: 844 });
// P8b: the driver was told the order is theirs (in the app; a registered phone gets it as a push).
await driver.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
const driverNotices = (await driver.innerText("body")).replace(/\s+/g, " ");
check("the driver was told the delivery is theirs", driverNotices.includes(`A delivery for you: ${orderNumber}`), driverNotices.slice(0, 160));
await driver.goto(`${BASE}/en/deliveries`, { waitUntil: 'networkidle' });
const card = driver.locator(`[data-testid="delivery-${orderNumber}"]`);
check('the driver sees the delivery with the address and what to hand over', (await card.count()) === 1 && /M\. Smoke Villa/.test(await card.innerText()) && /Akuru-Packed Notebook/.test(await card.innerText()), (await card.innerText().catch(() => 'not listed')).replace(/\s+/g, ' ').slice(0, 140));
check('and the page fits a phone', (await driver.evaluate(() => document.documentElement.scrollWidth)) <= 390);
await card.locator('[data-testid^="picked-up-"]').click().catch(() => {});
await settle(driver, `[data-testid="delivery-${orderNumber}"][data-state="picked_up"]`);
check('Picked up: the order is out for delivery', (await driver.locator(`[data-testid="delivery-${orderNumber}"][data-state="picked_up"]`).count()) === 1);
const proofPhoto = { name: 'door.png', mimeType: 'image/png', buffer: await driver.screenshot({ clip: { x: 0, y: 0, width: 120, height: 120 } }) };
await driver.locator(`[data-testid="delivery-${orderNumber}"] [data-testid^="proof-"]`).setInputFiles(proofPhoto);
await driver.locator(`[data-testid="delivery-${orderNumber}"] [data-testid^="delivered-"]`).click().catch(() => {});
await settle(driver, `[data-testid="delivery-${orderNumber}"][data-state="delivered"]`);
const done = driver.locator(`[data-testid="delivery-${orderNumber}"][data-state="delivered"]`);
check('Delivered, with the photo', (await done.count()) === 1 && (await done.locator('[data-testid="proof-link"]').count()) === 1);
const proofHref = await done.locator('[data-testid="proof-link"]').first().getAttribute('href').catch(() => null);
const proofForOffice = proofHref ? await office.request.get(proofHref.startsWith('http') ? proofHref : `${BASE}${proofHref}`) : null;
check('the office can open the photo', proofForOffice?.status() === 200 && /image/.test(proofForOffice?.headers()['content-type'] ?? ''), proofHref ?? 'no link');
const proofForCustomer = proofHref ? await customer.request.get(proofHref.startsWith('http') ? proofHref : `${BASE}${proofHref}`) : null;
check('and the customer cannot', proofForCustomer?.status() === 403, `HTTP ${proofForCustomer?.status()}`);

await office.reload({ waitUntil: 'networkidle' });
check('the order leaves the office\'s queue once delivered', (await office.locator(`[data-testid="akuru-order-${orderNumber}"]`).count()) === 0);
const csv = await office.request.get(`${BASE}/en/admin/bookshop/akuru/export`);
const csvLine = (await csv.text()).split('\n').find((l) => l.startsWith(orderNumber)) ?? '';
check('the Akuru orders CSV has it, delivered', csv.status() === 200 && /SMOKE-Akuru Packs/.test(csvLine) && /,delivered,/.test(csvLine), csvLine.slice(0, 120) || `HTTP ${csv.status()}`);

// ------------------------------------------------------------ 4. the customer sees it
await customer.goto(`${BASE}/en/my-orders/${orderNumber}`, { waitUntil: 'networkidle' });
check('the customer\'s order says delivered', /Delivered/.test(await customer.locator('main').innerText().catch(() => '')));

await finish();
