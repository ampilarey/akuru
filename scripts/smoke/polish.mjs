/**
 * Can a customer find, save, trust and be nudged in the shop, and can the
 * shop and the office run the polish around it?
 * (BOOKSHOP_PLAN slice B7: shop polish and trust.)
 *
 * `SmokeMarkerSeeder::vendorCycle()` leaves the student one delivered
 * order of two tracing books (the B6 matured order), a sold-out Wooden
 * Quran Stand, Fitrah with no codes, badges, reviews or free-delivery
 * amount, and no office home picks.
 *
 * The student:
 *   1. types in the shop's search box and picks the tracing book from the
 *      suggestions with the keyboard; saves it to the wishlist; finds it on
 *      My wishlist; asks to be told when the sold-out stand is back.
 * Fitrah's owner:
 *   2. puts the stand back in stock (the student is told), gives the
 *      tracing book a "Staff pick" badge, sets free delivery over MVR 200,
 *      makes a 10% code FITWALK; drags a section into place in the designer.
 * The student:
 *   3. sees the notice, reviews the tracing book from the delivered order;
 *      the cart nudges them towards free delivery; the code takes MVR 8.50
 *      off Fitrah's book at checkout.
 * Fitrah:
 *   4. replies to the review in public.
 * The office:
 *   5. merchandises the shop home (a hero and a featured product), then
 *      hides the review with a note; the product page drops it.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/polish.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_ADMIN, SMOKE_VENDOR, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN = process.env.SMOKE_ADMIN ?? 'admin@akuru.edu.mv';
const VENDOR = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const BOOK = 'smoke-arabic-letters-tracing-book';
const STAND = 'smoke-quran-stand';
const BADGE = 'Staff pick';
const CODE = 'FITWALK';
const REVIEW = 'SMOKE-Review: clear letters, thick paper.';
const REPLY = 'SMOKE-Reply: thank you for writing!';
const HERO = 'SMOKE-Hero: ready for school';

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

async function signIn(email) {
    const context = await browser.newContext({ viewport: { width: 1400, height: 950 } });
    context.setDefaultNavigationTimeout(60000);
    await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
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

const text = async (page) => (await ((await page.locator('main').count()) ? page.innerText('main') : page.innerText('body'))).replace(/\s+/g, ' ');
const settle = async (page, selector = null) => {
    await page.waitForLoadState('networkidle').catch(() => {});
    if (selector) {
        await page.locator(selector).first().waitFor({ timeout: 20000 }).catch(() => {});
    }
    await page.waitForTimeout(300);
};
const count = (page, selector) => page.locator(selector).count();
const inner = async (page, selector) => (await page.locator(selector).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
// A Blade form post: wait for the navigation it starts.
const submit = async (page, selector) => {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click(selector)]);
    await page.waitForTimeout(200);
};

// ------------------------------------------------------------ 1. find and save

const student = await signIn(STUDENT);
await student.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
await student.locator('[data-testid="shop-search"]').pressSequentially('traci', { delay: 60 });
await student.locator('[data-testid="shop-suggest"] [role="option"]').first().waitFor({ timeout: 10000 }).catch(() => {});
check('typing in the search box suggests the tracing book', (await inner(student, '[data-testid="shop-suggest"]')).includes('Arabic Letters Tracing Book'), await inner(student, '[data-testid="shop-suggest"]'));
await student.keyboard.press('ArrowDown');
await Promise.all([student.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), student.keyboard.press('Enter')]);
check('ArrowDown and Enter open it', student.url().endsWith(`/shop/products/${BOOK}`), student.url().replace(BASE, ''));
check('the book wears the New badge', (await inner(student, '[data-testid="product-badges"]')).includes('New'), await inner(student, '[data-testid="product-badges"]'));

await submit(student, '[data-testid="wishlist-toggle"]');
check('saved to the wishlist, the heart is pressed', (await student.locator('[data-testid="wishlist-toggle"]').getAttribute('aria-pressed')) === 'true' && (await count(student, '[data-testid="flash-success"]')) === 1);
await student.goto(`${BASE}/en/my-wishlist`, { waitUntil: 'networkidle' });
check('My wishlist lists it, with a CSV', (await inner(student, '[data-testid="wishlist-grid"]')).includes('Arabic Letters Tracing Book') && (await count(student, 'a[href$="/my-wishlist/export"]')) === 1, await inner(student, '[data-testid="wishlist-grid"]'));

await student.goto(`${BASE}/en/shop/products/${STAND}`, { waitUntil: 'networkidle' });
check('the sold-out stand offers Notify me instead of Add to cart', (await count(student, '[data-testid="notify-me"]')) === 1 && (await count(student, '[data-testid="add-to-cart"]')) === 0);
await submit(student, '[data-testid="notify-me"]');
check('asked, the page says it will tell them', (await count(student, '[data-testid="alert-on"]')) === 1);
await student.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('the shop home remembers what they looked at', (await inner(student, '[data-testid="shop-recently-viewed"]')).includes('Wooden Quran Stand'), (await inner(student, '[data-testid="shop-recently-viewed"]')).slice(0, 120));

// ------------------------------------------------------------ 2. the shop's side

const vendor = await signIn(VENDOR);
await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
if ((await count(vendor, '[data-testid="accept-agreement"]')) > 0) {
    await vendor.check('[data-testid="accept-agreement"]');
    await vendor.click('[data-testid="agreement-form"] button[type=submit]');
    await settle(vendor, `[data-testid="edit-${STAND}"]`);
}
await vendor.click(`[data-testid="edit-${STAND}"]`);
await vendor.fill('[data-testid="product-stock"]', '5');
await vendor.click('[data-testid="save-product"]');
await settle(vendor, '[data-testid="flash-success"]');
check('the owner puts the stand back in stock', (await inner(vendor, `[data-testid="product-row-${STAND}"]`)).includes('5'), await inner(vendor, `[data-testid="product-row-${STAND}"]`));

await vendor.click(`[data-testid="edit-${BOOK}"]`);
await vendor.fill('[data-testid="product-badge"]', BADGE);
await vendor.click('[data-testid="save-product"]');
await settle(vendor, '[data-testid="flash-success"]');

await vendor.fill('[data-testid="free-delivery-over"]', '200');
await vendor.click('[data-testid="save-settings"]');
await settle(vendor, '[data-testid="flash-success"]');

await vendor.fill('[data-testid="code-code"]', CODE.toLowerCase());
await vendor.selectOption('[data-testid="code-type"]', 'percentage');
await vendor.fill('[data-testid="code-value"]', '10');
await vendor.click('[data-testid="save-code"]');
await settle(vendor, `[data-testid="code-${CODE}"]`);
check(`the owner's code ${CODE} is listed, uppercased and on`, (await count(vendor, `[data-testid="code-${CODE}"]`)) === 1 && (await inner(vendor, `[data-testid="code-${CODE}"]`)).includes('10'), await inner(vendor, `[data-testid="code-${CODE}"]`));

// Drag-to-order in the designer: two sections, the second dragged above the first.
await vendor.click('[data-testid="open-sections"]');
await settle(vendor, '[data-testid="sections-heading"]');
await vendor.click('[data-testid="tab-home"]').catch(() => {});
const before = await count(vendor, '[data-testid^="home-row-"]');
for (const type of ['faq', 'video']) {
    await vendor.selectOption('[data-testid="home-add-type"]', type);
    await vendor.click('[data-testid="home-add"]');
}
const last = before + 1;
const typeAt = (i) => vendor.locator(`[data-testid="home-row-${i}"]`).getAttribute('data-type');
check('two sections are added, FAQ then video', (await typeAt(before)) === 'faq' && (await typeAt(last)) === 'video');
await vendor.dragAndDrop(`[data-testid="home-${last}-handle"]`, `[data-testid="home-row-${before}"]`);
await vendor.waitForTimeout(300);
check('dragging the video by its handle puts it above the FAQ', (await typeAt(before)) === 'video' && (await typeAt(last)) === 'faq', `${await typeAt(before)}, ${await typeAt(last)}`);

// ------------------------------------------------------------ 3. told, reviews, nudged, discounted

await student.goto(`${BASE}/en/portal/notifications`, { waitUntil: 'networkidle' });
check('the student is told the stand is back', (await text(student)).includes('Back in stock') && (await text(student)).includes('Wooden Quran Stand'), (await text(student)).slice(0, 120));
await student.goto(`${BASE}/en/shop/products/${STAND}`, { waitUntil: 'networkidle' });
check('and can add it to the cart now', (await count(student, '[data-testid="add-to-cart"]')) === 1 && (await count(student, '[data-testid="alert-on"]')) === 0);

await student.goto(`${BASE}/en/my-orders`, { waitUntil: 'networkidle' });
const delivered = await student.locator('a[href*="/my-orders/SMK-"]').first().getAttribute('href').catch(() => null);
if (delivered) {
    await student.goto(delivered.startsWith('http') ? delivered : `${BASE}${delivered}`, { waitUntil: 'networkidle' });
}
check('the delivered order offers Write a review', (await count(student, '[data-testid="write-review"]')) === 1, student.url().replace(BASE, ''));
await Promise.all([student.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), student.click('[data-testid="write-review"]')]);
check('it opens the review form on the product', (await count(student, '[data-testid="review-form"]')) === 1 && (await inner(student, '[data-testid="product-badges"]')).includes(BADGE));
await student.check('[data-testid="rating-4"]');
await student.fill('[data-testid="review-body"]', REVIEW);
await submit(student, '[data-testid="submit-review"]');
check('the review is up with four stars, the form gone', (await inner(student, '[data-testid="reviews"]')).includes(REVIEW) && (await inner(student, '[data-testid="product-rating"]')).includes('4.0') && (await count(student, '[data-testid="review-form"]')) === 0, (await inner(student, '[data-testid="reviews"]')).slice(0, 160));

await student.fill('[data-testid="quantity"]', '1');
await submit(student, '[data-testid="add-to-cart"]');
await student.goto(`${BASE}/en/shop/cart`, { waitUntil: 'networkidle' });
check('the cart nudges: MVR 115.00 more for free delivery from Fitrah', (await inner(student, '[data-testid="free-delivery-fitrah"]')).includes('115.00'), await inner(student, '[data-testid="free-delivery-fitrah"]'));
await submit(student, '[data-testid="go-to-checkout"]');
const methods = student.locator('[data-testid^="delivery-fitrah"] input[type=radio], input[name="delivery[fitrah]"]');
if ((await methods.count()) > 0 && !(await methods.first().isChecked())) {
    await methods.first().check();
}
if ((await count(student, '[data-testid="recipient-name"]')) > 0 && (await student.locator('[data-testid="recipient-name"]').isVisible())) {
    await student.fill('[data-testid="recipient-name"]', 'Smoke Customer');
    await student.fill('[data-testid="phone"]', '7700000');
    await student.fill('[data-testid="atoll"]', 'K');
    await student.fill('[data-testid="island"]', 'Malé');
    await student.fill('[data-testid="street"]', 'M. Smoke Villa');
}
await student.fill('input[name="discount_code"]', CODE);
await student.check('input[name="payment_method"][value="wallet"]').catch(() => {});
await submit(student, '[data-testid="place-order"]');
const orderLink = await student.locator('[data-testid="checkout-orders"] a[href*="/my-orders/"]').first().getAttribute('href').catch(() => null);
check('paid, with the shop\'s code applied', (await student.locator('[data-testid="checkout-status"]').getAttribute('data-status').catch(() => '')) === 'paid', `${student.url().replace(BASE, '')} ${(await text(student)).slice(0, 160)}`);
if (orderLink) {
    await student.goto(orderLink.startsWith('http') ? orderLink : `${BASE}${orderLink}`, { waitUntil: 'networkidle' });
}
check(`the receipt shows MVR 8.50 off Fitrah's book`, (await inner(student, '[data-testid="receipt-discount"]')).includes('8.50'), await inner(student, '[data-testid="receipt-discount"]'));

// ------------------------------------------------------------ 4. the shop replies

await vendor.goto(`${BASE}/en/vendor`, { waitUntil: 'networkidle' });
await vendor.click('[data-testid="open-reviews"]');
await settle(vendor, '[data-testid="vendor-reviews"]');
const reviewRow = vendor.locator('[data-testid^="vendor-review-"]').first();
const reviewId = ((await reviewRow.getAttribute('data-testid').catch(() => '')) || '').replace('vendor-review-', '');
check('the shop sees the review under Reviews', reviewId !== '' && (await reviewRow.innerText()).includes(REVIEW));
await vendor.fill(`[data-testid="reply-text-${reviewId}"]`, REPLY);
await vendor.click(`[data-testid="reply-save-${reviewId}"]`);
await settle(vendor, '[data-testid="flash-success"]');
await student.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
check('the reply shows under the review on the product', (await inner(student, `[data-testid="review-${reviewId}"]`)).includes(REPLY), await inner(student, `[data-testid="review-${reviewId}"]`));

// ------------------------------------------------------------ 5. the office

const office = await signIn(ADMIN);
await office.goto(`${BASE}/en/admin/bookshop`, { waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-home"]');
await office.fill('[data-testid="hero-heading"]', HERO);
await office.selectOption('[data-testid="hero-link-kind"]', 'vendor');
await office.selectOption('[data-testid="hero-link-target"]', 'fitrah');
await office.click('[data-testid="add-hero"]');
await settle(office, '[data-testid="flash-success"]');
const bookOption = await office.locator('[data-testid="feature-product"] option', { hasText: 'Arabic Letters Tracing Book' }).first().getAttribute('value').catch(() => null);
if (bookOption) {
    await office.selectOption('[data-testid="feature-product"]', bookOption);
}
await office.click('[data-testid="add-featured"]');
await settle(office, '[data-testid="home-featured"] li');
check('the office adds a hero and features the tracing book', (await inner(office, '[data-testid="office-home"]')).includes(HERO) && (await inner(office, '[data-testid="home-featured"]')).includes('Arabic Letters Tracing Book'));

const guest = await browser.newContext({ viewport: { width: 1400, height: 950 } });
await guest.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));
const visitor = await guest.newPage();
visitor.on('response', (response) => { if (response.status() >= 500) problems.push(`guest: HTTP ${response.status()} ${response.url()}`); });
await visitor.goto(`${BASE}/en/shop`, { waitUntil: 'networkidle' });
check('a guest sees the hero, linking to Fitrah', (await inner(visitor, '[data-testid="shop-hero"]')).includes(HERO) && (await count(visitor, '[data-testid="shop-hero"] a[href$="/shop/fitrah"]')) >= 1, (await inner(visitor, '[data-testid="shop-hero"]')).slice(0, 120));
const featuredCard = await inner(visitor, `[data-testid="shop-featured"] [data-product="${BOOK}"]`);
check(`and the featured book, wearing "${BADGE}" and its stars`, featuredCard.includes(BADGE) && featuredCard.includes('★★★★☆'), featuredCard.slice(0, 160));
await visitor.goto(`${BASE}/en/shop?sort=top_rated`, { waitUntil: 'networkidle' });
check('Top rated puts the reviewed book first', ((await visitor.locator('[data-testid="shop-grid"] [data-product]').first().getAttribute('data-product').catch(() => '')) ?? '') === BOOK, await visitor.locator('[data-testid="shop-grid"] [data-product]').first().getAttribute('data-product').catch(() => ''));

await office.reload({ waitUntil: 'networkidle' });
await settle(office, '[data-testid="office-reviews"]');
check('the office sees the review with the reply', (await inner(office, `[data-testid="office-review-${reviewId}"]`)).includes(REVIEW) && (await inner(office, `[data-testid="office-review-${reviewId}"]`)).includes(REPLY));
await office.fill(`[data-testid="review-note-${reviewId}"]`, 'SMOKE-Note: names a teacher');
await office.click(`[data-testid="review-hide-${reviewId}"]`);
await settle(office, `[data-testid="office-review-${reviewId}"][data-review-status="hidden"]`);
check('hidden, with its note', (await office.locator(`[data-testid="office-review-${reviewId}"]`).getAttribute('data-review-status')) === 'hidden');
await visitor.goto(`${BASE}/en/shop/products/${BOOK}`, { waitUntil: 'networkidle' });
check('the product page drops the review and the stars', !(await text(visitor)).includes(REVIEW) && (await count(visitor, '[data-testid="product-rating"]')) === 0);

await finish();
