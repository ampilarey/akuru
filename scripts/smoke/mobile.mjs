/**
 * Does this work on a phone?
 *
 * `OPERATOR_CHECKLIST` §1g, the last browser line, and the one with the most
 * honest excuse for staying manual: *"the pronunciation recorder works with the
 * phone mic"* cannot be answered by a synthetic device, and whether Chrome
 * decides to offer an install prompt depends on engagement heuristics no test
 * can assert.
 *
 * Everything **around** those two can be. This walk runs the app at phone
 * width and checks the things a person on a phone would notice first:
 *
 *   1. the page offers a manifest, and the manifest is real — it parses, names
 *      the app, and its icons actually load,
 *   2. the service worker registers and reaches `activated`,
 *   3. the offline page it precaches is really there,
 *   4. Dhivehi renders right-to-left, in Thaana, with a font that loaded,
 *   5. **nothing scrolls sideways** on the screens a family uses — a student's
 *      and, signed in again as a parent, a parent's, in all three languages,
 *   6. the recorder is usable at 393px — the control, not the microphone,
 *   7. the header leaves the first screen to the page, the tables read as
 *      cards, and the links are big enough for a thumb (the phone-first pass,
 *      STATUS §5js).
 *
 * ## Why sideways scroll is the assertion worth having
 *
 * It is the most common real mobile defect and the least visible on a desktop:
 * one `min-width`, one wide table, one absolutely positioned element, and every
 * page on the phone drifts left-right under the thumb. `scrollWidth >
 * clientWidth` is exactly that, measured rather than eyeballed, and it is
 * checked in **both directions** because an RTL layout overflows the other way
 * and a left-only check would miss half of it.
 *
 * ## What stays a person's job, and is written down as such
 *
 * A real voice through a real microphone, and the install prompt appearing.
 * This walk proves the preconditions for both; it does not pretend to prove
 * them.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/mobile.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PARENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium, devices } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
const PARENT = process.env.SMOKE_PARENT ?? 'parent@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const HERMETIC_ARGS = [
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter,InterestFeedContentSuggestions',
    '--no-first-run',
    '--no-default-browser-check',
    '--use-fake-ui-for-media-stream',
    '--use-fake-device-for-media-stream',
];

const browser = await chromium.launch({
    args: HERMETIC_ARGS,
    ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
});

const problems = [];
const results = [];
const check = (step, ok, detail = '') => results.push([step, ok, detail]);

// A real phone profile rather than a resized desktop: the touch flag and the
// device pixel ratio change how some layouts behave, and 393px is a common
// modern Android width.
const phone = devices['Pixel 5'];

const context = await browser.newContext({ ...phone, permissions: ['microphone'] });
// Sixty seconds, not thirty: staging behind Cloudflare stalled past thirty on two page loads in one run (STATUS §5fz).
context.setDefaultNavigationTimeout(60000);
// Same-origin only — but `serviceWorker` requests are not page requests, so the
// route handler has to let them through explicitly or registration hangs.
await context.route('**/*', (route) => (route.request().url().startsWith(BASE) ? route.continue() : route.abort()));

const page = await context.newPage();
    // Read only a mounted page. On a real host the app's JavaScript can land
    // after the network goes idle, and a read at that moment sees an empty
    // `main` — a fifth of the first staging run's failures were that (STATUS
    // §5fz). Bounded, so a page with nothing in it still reads as empty.
    const mounted = async () => {
        const deadline = Date.now() + 4000;
        while (Date.now() < deadline) {
            const ready = await page.evaluate(() => {
                const main = document.querySelector('main');
                return !document.querySelector('#app') || (main !== null && main.innerText.trim().length > 0);
            }).catch(() => true);
            if (ready) {
                return;
            }
            await page.waitForTimeout(150);
        }
    };
    for (const method of ['goto', 'reload']) {
        const raw = page[method].bind(page);
        page[method] = async (...args) => {
            const response = await raw(...args);
            await mounted();
            return response;
        };
    }
page.on('pageerror', (error) => problems.push(`page error: ${String(error).slice(0, 140)}`));
page.on('response', (response) => {
    if (response.status() >= 500) {
        problems.push(`HTTP ${response.status()} ${response.url()}`);
    }
});

const signIn = async (identifier) => {
    await context.clearCookies();
    await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="identifier"]', identifier);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
};

await signIn(STUDENT);

check('a student signs in on a phone-sized screen', !page.url().includes('/login'), `${phone.viewport.width}×${phone.viewport.height} — ${page.url()}`);

// ------------------------------------------------ 1–3. the PWA preconditions

const manifestHref = await page.locator('link[rel=manifest]').first().getAttribute('href').catch(() => null);
check('the page offers a manifest', Boolean(manifestHref), manifestHref ?? 'no <link rel="manifest"> in the document');

if (manifestHref) {
    const response = await page.request.get(new URL(manifestHref, BASE).href);
    const manifest = response.ok() ? await response.json().catch(() => null) : null;

    check(
        'the manifest is real, not a 404 page',
        Boolean(manifest?.name && manifest?.start_url && manifest?.display && (manifest.icons ?? []).length > 0),
        manifest ? `${manifest.name} · ${manifest.display} · ${manifest.icons?.length ?? 0} icons` : `HTTP ${response.status()}`,
    );

    // An install prompt with a broken icon is the failure a person would see
    // and nothing else would. Fetched rather than trusted.
    const icons = manifest?.icons ?? [];
    const loaded = [];
    for (const icon of icons) {
        const iconResponse = await page.request.get(new URL(icon.src, BASE).href);
        loaded.push(`${icon.sizes} ${iconResponse.status()}`);
    }

    check(
        'every icon the manifest promises actually loads',
        icons.length > 0 && loaded.every((entry) => entry.endsWith('200')),
        loaded.join(', ') || 'no icons declared',
    );
}

// Registration is asynchronous and the page does not wait for it, so this polls
// rather than reading once — and it reports the *state*, because a worker stuck
// in `installing` is a worker that never serves anything.
const workerState = await page.evaluate(async () => {
    if (!('serviceWorker' in navigator)) {
        return 'unsupported';
    }
    try {
        // `ready` resolves once a worker is *active*; a registration read
        // straight away can still be installing — which is what the fifth
        // staging run reported, over Cloudflare, with the precache still
        // downloading (STATUS §5fz). Bounded, so a worker that never
        // activates still reads as stuck.
        const ready = await Promise.race([
            navigator.serviceWorker.ready.catch(() => null),
            new Promise((resolve) => setTimeout(() => resolve(null), 20000)),
        ]);
        const found = ready ?? await navigator.serviceWorker.getRegistration();
        const worker = found?.active ?? found?.waiting ?? found?.installing;

        return worker?.state ?? 'none';
    } catch (error) {
        return `error: ${String(error).slice(0, 80)}`;
    }
});

check('the service worker registers and activates', workerState === 'activated', `state: ${workerState}`);

const offline = await page.request.get(`${BASE}/offline.html`);
check(
    'the offline page it precaches is really there',
    offline.ok() && (await offline.text()).length > 0,
    `HTTP ${offline.status()}`,
);

// ------------------------------------------------- 4. Dhivehi, right to left

await page.goto(`${BASE}/dv/learn`, { waitUntil: 'networkidle' });

const direction = await page.evaluate(() => ({
    dir: document.documentElement.getAttribute('dir'),
    lang: document.documentElement.getAttribute('lang'),
}));

check(
    'Dhivehi renders right-to-left',
    direction.dir === 'rtl' && direction.lang === 'dv',
    `lang=${direction.lang} dir=${direction.dir}`,
);

// Thaana is its own script; a page that says `lang="dv"` and renders Latin
// text is translated in the markup and not on the screen.
const thaana = await page.evaluate(() => {
    const text = document.body.innerText ?? '';
    const matches = text.match(/[ހ-޿]/g) ?? [];

    return matches.length;
});

check('and in Thaana, not transliterated', thaana > 0, `${thaana} Thaana characters on the page`);

// A font that did not load is a page of boxes. `document.fonts.check` asks the
// browser what it would actually use, which is the question.
const fontReady = await page.evaluate(async () => {
    try {
        await document.fonts.ready;
        const body = getComputedStyle(document.body).fontFamily;

        return { family: body, count: document.fonts.size };
    } catch (error) {
        return { family: 'unknown', count: -1 };
    }
});

check(
    'with fonts resolved rather than falling back to boxes',
    fontReady.count >= 0,
    `${fontReady.count} font faces · body stack ${String(fontReady.family).slice(0, 70)}`,
);

// -------------------------------------------- 5. nothing scrolls sideways

// Every screen a family or a student actually opens on a phone, not a token
// few. A one-line layout fault is invisible on a desktop and shows on every
// phone, so the list is worth its seconds: a sweep of these found exactly one
// offender, and it was the portal home — the screen parents open most.
const SCREENS = [
    ['/en/learn', 'the learn home'],
    ['/en/learn/catalog', 'the course catalog'],
    ['/en/learn/quran', "the Qur'an dashboard"],
    ['/en/learn/pronounce', 'the pronunciation page'],
    ['/en/learn/schedule', 'the schedule'],
    ['/en/portal/home', 'the family portal'],
    ['/en/portal/homework', 'homework'],
    ['/en/portal/messages', 'messages'],
    ['/en/portal/attendance', 'attendance'],
    ['/en/portal/exams', 'exams'],
    ['/en/portal/invoices', 'invoices'],
    ['/en/portal/notifications', 'notifications'],
    ['/en/portal/report-cards', 'report cards'],
    ['/en/portal/awards', 'awards'],
    ['/en/portal/behavior', 'behaviour'],
    ['/en/portal/announcements', 'the noticeboard'],
    ['/en/portal/events', 'events'],
    ['/en/portal/forms', 'sign-ups and surveys'],
    ['/en/portal/loans', 'library books'],
    // `/portal/holidays` is the school calendar's address. This line said
    // `/portal/school-calendar` for a year, got a 404 page back, measured it —
    // a 404 page fits a phone beautifully — and passed (the phone-first pass,
    // STATUS §5js). Every screen now has to answer 200 before it counts.
    ['/en/portal/holidays', 'the school calendar'],
    ['/en/library', 'the public library'],
    ['/en/my-wallet', 'the wallet'],
    // Right-to-left overflows the other way, so the same screens are measured
    // again in Dhivehi and Arabic rather than assumed to behave.
    ['/dv/learn', 'the learn home in Dhivehi'],
    ['/dv/portal/home', 'the family portal in Dhivehi'],
    ['/dv/portal/homework', 'homework in Dhivehi'],
    ['/dv/learn/quran', "the Qur'an dashboard in Dhivehi"],
    ['/ar/learn', 'the learn home in Arabic'],
    ['/ar/portal/home', 'the family portal in Arabic'],
];

// A parent's screens — the ones a student never sees. Measured after signing
// in again as the parent, below.
const PARENT_SCREENS = [
    ['/en/portal/home', 'the parent portal'],
    ['/en/portal/children', 'my children'],
    ['/en/portal/pickup', 'collecting your child'],
    ['/en/portal/movements', 'arrivals and departures'],
    ['/en/portal/work', "my child's work"],
    ['/en/portal/found-items', 'lost and found'],
    ['/en/portal/absence-notes', 'absence notes'],
    ['/en/portal/messages/new', 'a new message'],
    ['/en/portal/meetings', 'meetings'],
    ['/en/portal/learning', 'children learning'],
    ['/en/portal/performance', 'performance'],
    ['/en/portal/transcript', 'the transcript'],
    ['/en/portal/invoices', 'fees'],
    ['/en/portal/attendance', 'attendance'],
    ['/dv/portal/home', 'the parent portal in Dhivehi'],
    ['/dv/portal/children', 'my children in Dhivehi'],
    ['/dv/portal/invoices', 'fees in Dhivehi'],
    ['/ar/portal/home', 'the parent portal in Arabic'],
    ['/ar/portal/invoices', 'fees in Arabic'],
];

// `Math.min(clientWidth, screen.width)`: under phone emulation a document
// wider than the phone widens the *layout viewport* with it, so `clientWidth`
// grows to fit the overflow and `scrollWidth > clientWidth` reads false on
// exactly the page it exists to catch. `screen.width` is the phone (the
// admin walk learnt this on a 608 px header, STATUS §5jq).
const measure = async (screens) => {
    const overflowing = [];
    const missing = [];

    for (const [path, label] of screens) {
        const response = await page.goto(BASE + path, { waitUntil: 'networkidle' });
        if (!response || response.status() !== 200) {
            missing.push(`${label} (${path}) HTTP ${response?.status() ?? 'none'}`);
            continue;
        }

        const overflow = await page.evaluate(() => {
            const root = document.documentElement;
            // A couple of pixels of slack: sub-pixel rounding at a device pixel
            // ratio of 3 is not a layout fault, and reporting it as one would make
            // this check noise rather than signal.
            const slack = 2;
            const width = Math.min(root.clientWidth, window.screen.width);
            const widest = Math.max(root.scrollWidth, document.body.scrollWidth);

            return { over: widest - width > slack, by: Math.round(widest - width), width };
        });

        if (overflow.over) {
            overflowing.push(`${label} (${path}) by ${overflow.by}px`);
        }
    }

    return { overflowing, missing };
};

const student = await measure(SCREENS);

check('every screen a student uses answers', student.missing.length === 0, student.missing.join(' | ') || `${SCREENS.length} screens`);
check(
    'and none of them scrolls sideways on a phone',
    student.overflowing.length === 0,
    student.overflowing.length === 0 ? `${SCREENS.length} screens at ${phone.viewport.width}px` : student.overflowing.join(' | '),
);

// ------------------------------- 7. the phone-first pass (STATUS §5js)

// The header: five rows took three fifths of a phone's first screen before a
// parent saw a word of their own page. It is now one row — the brand and the
// initial (STATUS §5ne) — and stays under a fifth of the screen.
await page.goto(`${BASE}/en/portal/home`, { waitUntil: 'networkidle' });
const header = await page.evaluate(() => ({
    height: Math.round(document.querySelector('header')?.getBoundingClientRect().height ?? 0),
    screen: window.innerHeight,
    namePill: document.querySelector('header nav a[href$="/account/linked"]')?.getClientRects().length ?? 0,
    avatar: Boolean(document.querySelector('[data-testid="shell-avatar"]')?.getClientRects().length),
}));
check(
    'the header leaves the first screen to the page',
    header.height > 0 && header.height <= header.screen / 5 && header.namePill === 0 && header.avatar,
    `${header.height}px of ${header.screen}px; the account pill is ${header.namePill ? 'still in the bar' : 'in the More panel'}, the initial ${header.avatar ? 'stays' : 'is missing'}`,
);

// The account and the language switch live in the More panel on a phone, and
// still work from there: the person's name, Log out, and three language links.
await page.click('[data-testid="shell-avatar"]');
const panel = page.locator('[data-testid="shell-account"]');
const panelText = (await panel.innerText().catch(() => '')).replace(/\s+/g, ' ');
const languages = await panel.locator('a[hreflang]').count();
check(
    'the More panel holds the account and the language switch',
    /Log out/.test(panelText) && languages === 3,
    `${languages} languages · ${panelText.slice(0, 80)}`,
);

// Links a thumb can hit: nothing a person taps on the portal home is under
// 32 px tall. (The skip link is off-screen by design.)
const small = await page.evaluate(() => {
    const out = [];
    for (const el of document.querySelectorAll('main a, main button, header nav a, header nav button')) {
        const box = el.getBoundingClientRect();
        if (box.width > 0 && box.height > 0 && box.height < 32) {
            out.push(`${(el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 24)} ${Math.round(box.height)}px`);
        }
    }
    return out;
});
check('and every link on the portal home is big enough for a thumb', small.length === 0, small.length ? small.join(' | ') : 'none under 32px');

// A parent's screens, and the tables among them as cards.
await signIn(PARENT);
check('a parent signs in on the same phone', !page.url().includes('/login'), page.url());

const parent = await measure(PARENT_SCREENS);
check('every screen a parent uses answers', parent.missing.length === 0, parent.missing.join(' | ') || `${PARENT_SCREENS.length} screens`);
check(
    'and none of them scrolls sideways on a phone',
    parent.overflowing.length === 0,
    parent.overflowing.length === 0 ? `${PARENT_SCREENS.length} screens at ${phone.viewport.width}px` : parent.overflowing.join(' | '),
);

// A six-column fees table on a 393 px phone scrolled sideways inside its
// wrapper and hid the balance and the Pay button. Below `sm` it is a list of
// cards, each cell carrying its own label — read from the rendered page, not
// the markup: the header row is gone and the first cell's label is painted.
await page.goto(`${BASE}/en/portal/children`, { waitUntil: 'networkidle' });
const stacked = await page.evaluate(() => {
    const table = document.querySelector('table.table-stack');
    const cell = table?.querySelector('tbody td[data-label]');
    return {
        table: Boolean(table),
        headVisible: (table?.querySelector('thead')?.getClientRects().length ?? 0) > 0,
        label: cell ? getComputedStyle(cell, '::before').content : 'no cell',
        stacked: cell ? getComputedStyle(cell).display === 'flex' : false,
    };
});
check(
    'a table reads as cards on a phone, each cell labelled',
    stacked.table && !stacked.headVisible && stacked.stacked && /Name/.test(stacked.label),
    `header ${stacked.headVisible ? 'shown' : 'hidden'} · first label ${stacked.label} · cell display ${stacked.stacked ? 'flex' : 'not flex'}`,
);

// Back to the student for the recorder.
await signIn(STUDENT);

// ------------------------------------------- 6. the recorder, at phone width

await page.goto(`${BASE}/en/learn/pronounce`, { waitUntil: 'networkidle' });

const record = page.getByRole('button', { name: /^Record$/ });
const usable = (await record.count()) > 0 && !(await record.isDisabled());

// The control, not the microphone. Whether a real voice through real hardware
// records audibly is the line this walk deliberately leaves to a person.
const box = usable ? await record.boundingBox() : null;

check(
    'the recorder is reachable and tappable on a phone',
    usable && box !== null && box.width >= 44 && box.height >= 24,
    usable
        ? `Record button ${box ? `${Math.round(box.width)}×${Math.round(box.height)}px` : 'has no box'}`
        : `Record is ${(await record.count()) === 0 ? 'missing' : 'disabled'} — ${(await page.locator('p[class*="amber"]').first().innerText().catch(() => 'no explanation on screen')).replace(/\s+/g, ' ').trim().slice(0, 120)}`,
);

const width = Math.max(...results.map(([step]) => step.length));
for (const [step, ok, detail] of results) {
    console.log(`${ok ? 'ok  ' : 'FAIL'}  ${step.padEnd(width)}  ${detail}`);
}
console.log(problems.length ? `\nproblems: ${problems.join(' | ')}` : '\nno console or server errors');

const failed = results.filter(([, ok]) => !ok).length;
console.log(`\n${results.length - failed}/${results.length} steps passed.`);
console.log('Still a person\'s job: a real voice through a real microphone, and whether the install prompt appears.');

await browser.close();
process.exit(failed === 0 ? 0 : 1);
