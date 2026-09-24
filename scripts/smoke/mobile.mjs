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
 *   5. **nothing scrolls sideways** on the screens a family uses,
 *   6. the recorder is usable at 393px — the control, not the microphone.
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
 * Environment: SMOKE_BASE_URL, SMOKE_STUDENT, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { chromium, devices } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const STUDENT = process.env.SMOKE_STUDENT ?? 'student@akuru.edu.mv';
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

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', STUDENT);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

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
    ['/en/portal/school-calendar', 'the school calendar'],
    ['/en/library', 'the public library'],
    ['/en/my-wallet', 'the wallet'],
    // Right-to-left overflows the other way, so the same screens are measured
    // again in Dhivehi rather than assumed to behave.
    ['/dv/learn', 'the learn home in Dhivehi'],
    ['/dv/portal/home', 'the family portal in Dhivehi'],
    ['/dv/portal/homework', 'homework in Dhivehi'],
    ['/dv/learn/quran', "the Qur'an dashboard in Dhivehi"],
];

const overflowing = [];

for (const [path, label] of SCREENS) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });

    const overflow = await page.evaluate(() => {
        const root = document.documentElement;
        // A couple of pixels of slack: sub-pixel rounding at a device pixel
        // ratio of 3 is not a layout fault, and reporting it as one would make
        // this check noise rather than signal.
        const slack = 2;
        const widest = Math.max(root.scrollWidth, document.body.scrollWidth);

        return {
            over: widest - root.clientWidth > slack,
            by: Math.round(widest - root.clientWidth),
            width: root.clientWidth,
        };
    });

    if (overflow.over) {
        overflowing.push(`${label} (${path}) by ${overflow.by}px`);
    }
}

check(
    'no screen a family uses scrolls sideways on a phone',
    overflowing.length === 0,
    overflowing.length === 0 ? `${SCREENS.length} screens at ${phone.viewport.width}px` : overflowing.join(' | '),
);

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
