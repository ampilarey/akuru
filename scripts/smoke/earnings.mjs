/**
 * Does a sale become a writer's money, and does the payout gate say why not?
 *
 * L6 (§21–§24, §9.4) — the last uncovered Library loop, and the only one with
 * money in it.
 *
 *   1. the writer publishes a **paid** item,
 *   2. a reader buys it with their wallet,
 *   3. the writer's earnings card shows the sale, at the right split,
 *   4. the writer saves bank details,
 *   5. the writer asks for a payout and is **refused, with the reason**,
 *   6. the office's payouts queue renders and the earnings CSV downloads.
 *
 * ## Why the wallet, and not BML
 *
 * `BML_WEBHOOK_SECRET` is unset on every environment (`OWNER_ACTIONS` item 2),
 * so no card payment can confirm anywhere. The wallet branch (§43.14) is
 * internal money and grants immediately through the same grant action, which
 * makes it the only purchase path that completes today — and it accrues the
 * writer's earning through the same code the webhook uses. `SmokeMarkerSeeder`
 * tops the reader up through `CreditWalletAction`, the one way money enters a
 * wallet (rule 12).
 *
 * ## Step 5 is the point
 *
 * `LIBRARY_PAYOUTS_ENABLED` is **off** by design until the tax treatment is
 * decided (§9.4, `OPERATOR_CHECKLIST` §2a), so asking for a payout is supposed
 * to be refused. §1c asks for "a clear gate message" — and the last gate walked
 * this way turned out to refuse in complete silence (KNOWN_ISSUES #32). So the
 * walk does not check that the request fails; it checks that the **writer is
 * told why**, and that the sentence is the reassuring one the action actually
 * writes: earnings keep accruing and stay theirs.
 *
 * Do **not** switch the flag on to make this pass. The refusal is the
 * behaviour; the request path itself is covered by Pest.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/earnings.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_APPLICANT (the writer), SMOKE_READER,
 * SMOKE_STAFF, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const WRITER = process.env.SMOKE_APPLICANT ?? 'student@akuru.edu.mv';
const READER = process.env.SMOKE_READER ?? 'parent@akuru.edu.mv';
const STAFF = process.env.SMOKE_STAFF ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const STAMP = new Date().toISOString().replace(/[^0-9]/g, '').slice(8, 14);
const TITLE = `SMOKE-Paid ${STAMP}`;
const PRICE = 100;
// §22's default split is writer 70 / Akuru 30, so a 100 sale is 70 to the
// writer. Asserting the number rather than "an earning appeared" is what
// makes this a test of the split and not of the existence of a row.
const WRITER_SHARE = '70';

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

async function signIn(email) {
    const context = await browser.newContext();
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
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    return page;
}

const text = async (page) => (await page.innerText('main')).replace(/\s+/g, ' ');

async function settles(page, needle, ms = 6000) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        if ((await text(page)).includes(needle)) {
            return true;
        }
        await page.waitForTimeout(100);
    }

    return false;
}

const rowWith = (page, title, label) => page.locator('tr, li, article')
    .filter({ hasText: title })
    .filter({ has: page.locator(`button:has-text("${label}")`) })
    .last();

const writer = await signIn(WRITER);
const staff = await signIn(STAFF);

// ------------------------------------------- 1. the writer publishes a paid item

await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });

if ((await text(writer)).includes('Apply to publish')) {
    check('the walker is an approved writer', false, 'not a writer yet — run scripts/smoke/library.mjs first');
} else {
    check('the walker is an approved writer', true);

    const open = writer.locator('button:has-text("New draft")').first();
    if (await open.count()) {
        await open.click();
        await writer.waitForTimeout(300);
    }

    await writer.fill('input[placeholder="Title"]', TITLE);
    // The second select is access; `paid` is what makes the item saleable at
    // all, and a price of zero would be free whatever the access says.
    await writer.selectOption('select >> nth=1', 'paid');
    await writer.fill('input[placeholder*="price"]', String(PRICE));
    await writer.fill('textarea[placeholder="Abstract"]', 'SMOKE-Paid-Abstract');
    await writer.fill('textarea[placeholder*="Body"]', 'SMOKE-Paid-Body: worth a hundred rufiyaa.');
    await writer.click('button:has-text("Save draft")');

    const drafted = await settles(writer, TITLE);
    check('the writer saves a paid draft', drafted, (await text(writer)).slice(0, 160));

    if (drafted) {
        const row = rowWith(writer, TITLE, 'Submit for review');
        if (await row.count()) {
            await row.locator('button:has-text("Submit for review")').first().click();
            await settles(writer, 'submitted');
        }
    }

    await staff.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const approveRow = rowWith(staff, TITLE, 'Approve');
    if (await approveRow.count()) {
        await approveRow.locator('button:has-text("Approve")').first().click();
        await staff.waitForTimeout(2000);
        check('the office publishes it', true);
    } else {
        check('the office publishes it', false, 'no Approve button on the paid submission');
    }

    // The writer's balance **before** the sale. The earnings card is
    // cumulative and this fixture accrues across runs, so looking for a bare
    // "MVR 70" on it never matches — the figure is the running total. The
    // delta is what tests the split, and it is the same number whatever has
    // happened before.
    const pendingOf = async (page) => {
        await page.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
        const match = (await text(page)).match(/MVR ([\d.]+) pending/);
        return match ? Number(match[1]) : null;
    };

    const pendingBefore = await pendingOf(writer);
    check('the writer\'s pending balance is readable', pendingBefore !== null, String(pendingBefore));

    // ---------------------------------- 2. a reader buys it with their wallet

    const reader = await signIn(READER);
    await reader.goto(`${BASE}/en/library`, { waitUntil: 'networkidle' });

    const link = (await reader.$$eval('a', (as) => as.map((a) => [a.innerText.trim(), a.getAttribute('href') || ''])))
        .find(([label]) => label.includes(TITLE) || label.includes('SMOKE-Paid'));

    // The listing shows a card per item; the title is the link to it.
    const itemHref = link ? link[1] : (await reader.$$eval('a', (as) => as.map((a) => a.getAttribute('href') || '')))
        .find((href) => /\/library\/[^/]+$/.test(href) && !href.endsWith('/library'));

    check('the paid item is on the public shelf', Boolean(itemHref), itemHref ?? 'no item link on /library');

    let bought = false;

    if (itemHref) {
        await reader.goto(new URL(itemHref, BASE).href, { waitUntil: 'networkidle' });
        const wallet = reader.locator('button:has-text("Pay with wallet")').first();

        check('a signed-in reader is offered the wallet', (await wallet.count()) > 0, (await text(reader)).slice(0, 160));

        if (await wallet.count()) {
            await wallet.click();
            await reader.waitForLoadState('networkidle');
            // Buying grants access immediately — wallet money is internal, so
            // there is no webhook to wait for. It drops the reader **straight
            // into the book**, which is why looking for a "Read online" button
            // failed: by then they are already reading it. The body text is the
            // proof, and it is a better one.
            const after = await text(reader);
            bought = after.includes('Purchase complete') && after.includes('SMOKE-Paid-Body');

            // Each run spends 100 of the reader's seeded 500, so the fifth run
            // in a row finds an empty wallet. The product handles that well —
            // it refuses with **"Insufficient wallet balance"** rather than
            // half-completing — so this is a fixture that needs topping up, not
            // a defect, and the walk should say which.
            const broke = after.includes('Insufficient wallet balance');

            check(
                'paying with the wallet opens the book there and then',
                bought,
                broke
                    ? 'the reader\'s wallet is empty — re-seed to top it up: '
                        + 'php artisan db:seed --class=SmokeMarkerSeeder'
                    : after.slice(0, 200),
            );
        }
    }

    // -------------------- 3. and it becomes the writer's money, at the right split

    if (bought && pendingBefore !== null) {
        const pendingAfter = await pendingOf(writer);
        const gained = pendingAfter === null ? null : Math.round((pendingAfter - pendingBefore) * 100) / 100;

        // §22: writer 70 / Akuru 30, so a 100 sale moves the writer's pending
        // balance by exactly 70 — and by *pending*, not available, because §24
        // holds it until the refund window closes.
        check(
            'the sale reaches the writer at the 70/30 split',
            gained === Number(WRITER_SHARE),
            `pending ${pendingBefore} → ${pendingAfter} (expected +${WRITER_SHARE})`,
        );
    }

    // ------------------------------------------- 4. bank details, 5. the refusal

    await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });

    const bank = writer.locator('input[placeholder*="Bank"], input[placeholder*="bank"]').first();
    if (await bank.count()) {
        await writer.fill('input[placeholder*="Bank name"], input[placeholder*="Bank"]', 'SMOKE-Bank');
        await writer.fill('input[placeholder*="Account name"]', 'SMOKE-Account-Holder');
        await writer.fill('input[placeholder*="Account number"]', '7730000012345');
        await writer.click('button:has-text("Save bank details"), button:has-text("Update bank details")');
        check('the writer can save bank details', await settles(writer, 'Bank details saved.'), (await text(writer)).slice(0, 160));
    } else {
        check('the writer can save bank details', false, 'no bank-details fields on the writer portal');
    }

    // The gate — and it turns out to be the **good** shape, which is worth
    // recording as clearly as a defect would be.
    //
    // §1c expects "a clear payouts-not-enabled gate message" from *requesting*
    // one. What the portal actually does is better: with
    // `LIBRARY_PAYOUTS_ENABLED` off it does not offer the button at all and
    // explains instead — *"Payouts open soon — earnings keep accruing and stay
    // yours."* The writer is told before they press something, rather than
    // after.
    //
    // That is the exact opposite of the peer-review gate walked an hour
    // earlier, which refused in total silence (KNOWN_ISSUES #32). Same
    // codebase, same week, two gates, opposite manners — so this asserts both
    // halves: the explanation is there, **and** no button is dangled that
    // cannot work.
    await writer.goto(`${BASE}/en/write`, { waitUntil: 'networkidle' });
    const portalText = await text(writer);
    const requestButton = await writer.locator('button:has-text("Request payout")').count();

    check(
        'the writer is told why payouts are closed, before pressing anything',
        portalText.includes('Payouts open soon') && portalText.includes('stay yours'),
        portalText.includes('Payouts open soon') ? '' : portalText.slice(0, 220),
    );
    check(
        'and no payout button is offered that could not work',
        requestButton === 0,
        requestButton === 0 ? '' : 'a Request payout button is shown while LIBRARY_PAYOUTS_ENABLED is off',
    );

    // ------------------------------ 6. the office's own money screens render

    await staff.goto(`${BASE}/en/admin/library`, { waitUntil: 'networkidle' });
    const adminText = await text(staff);

    // The payouts queue hides itself when there is nothing in it, and with
    // `LIBRARY_PAYOUTS_ENABLED` off there can never be a request — so its
    // absence here is correct, not a missing screen, and asserting it were
    // present would be asserting the flag was on. What must be there is the
    // earnings route the office actually uses today.
    check('the office has the earnings export', adminText.includes('Earnings CSV'), adminText.slice(0, 200));

    // Fetched through the page's request context, not navigated to: it is a
    // download, and `page.goto` throws "Download is starting" rather than
    // returning a response.
    const csv = await staff.request.get(`${BASE}/en/admin/library/earnings/export`);
    const csvBody = csv.ok() ? await csv.text() : '';

    // The export is **per writer**, not per item — `writer,pending,available,
    // paid,refunded` — so looking for the item title in it found nothing and
    // said so about a file that was perfectly correct. What it must carry is
    // the writer and a pending figure that is not zero.
    const writerRow = csvBody.split('\n').find((line) => line.includes('SMOKE-Writer'));
    const pending = writerRow ? Number(writerRow.split(',')[1]) : 0;

    check(
        'the earnings CSV downloads, with the writer\'s accrued balance in it',
        csv.ok() && Boolean(writerRow) && pending > 0,
        csv.ok() ? (writerRow ?? csvBody.slice(0, 160)) : `HTTP ${csv.status()}`,
    );
}

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);

await browser.close();
process.exit(failed === 0 ? 0 : 1);
