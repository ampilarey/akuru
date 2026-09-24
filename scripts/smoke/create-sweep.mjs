/**
 * Can a person actually make one of these records, through the form?
 *
 * `sweep.mjs` (§5cm) answers "does the screen show a row that exists". That is
 * strictly weaker than the question the UNVERIFIED rows are really asking,
 * because the rows it looks for were planted in the database. This one types
 * into the real form, clicks the real button, and then **reloads the page**
 * before looking.
 *
 * The reload is the whole design. The marker is typed INTO the form, so a form
 * that does not clear on submit would make every screen report success — the
 * exact mirror of the `innerText` false negative that the read sweep shipped
 * with. On the first run of this script, without the reload, all seven screens
 * reported CREATED; with it, three of those turned out to be the form still
 * holding what I had typed.
 *
 *   node scripts/smoke/create-sweep.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_USER, SMOKE_PASSWORD, SMOKE_CHROMIUM
 * (same as sweep.mjs).
 *
 * **Per-screen hints.** A generic filler cannot guess a foreign key. Where a
 * field needs a real id or a value with a meaning, `hints` supplies it, keyed
 * by the placeholder the field shows. A screen that needs a hint is worth
 * noticing in itself — `/academics/behavior` asks for a **student id typed as a
 * number**, which is a real thing to raise about that form rather than a
 * limitation of this script.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const USER = process.env.SMOKE_USER ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

/**
 * Unique per run, which the old marker only looked like being.
 *
 * It was `Date.now().toString().slice(-6)`, the last six digits of the epoch in
 * milliseconds, and those **repeat every 1,000 seconds**. Two runs about
 * sixteen minutes apart get the same marker, and then a step passes because the
 * *earlier* run's row is still on the screen. That is a false green, which is
 * worse than the false reds this sweep has been producing: it reports a form as
 * working without ever testing it.
 *
 * Base-36 time plus four random characters, uppercased so it survives being
 * typed into a name field and read back out of one.
 */
const RUN = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`.toUpperCase();

const dayOffset = (n) => {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
};

/**
 * The day after the last calendar entry there is, read off the screen.
 *
 * The date used to be `dayOffset(20 + (Number(RUN) % 40))` under a comment
 * claiming re-running could not collide. It is a pick from forty slots, not a
 * unique value: after twelve runs there were twelve entries scattered across
 * that window and a thirteenth run had roughly a one-in-three chance of landing
 * on one — at which point the form correctly refused a duplicate and the sweep
 * reported a working screen as broken. **The same fault the meetings walk had**
 * (STATUS §5ed), in a walk nobody had gone back to.
 *
 * Asking the screen what already exists removes the guess entirely: the next
 * day is free however many times this has run.
 */
const dayAfterLastCalendarEntry = async (page) => {
  // Scoped to the table rather than the whole body, so a date that appears in
  // navigation or a footer cannot decide where this row goes.
  const text = await page.locator('tbody').first().innerText().catch(() => '');
  const dates = (text.match(/\d{4}-\d{2}-\d{2}/g) ?? []).sort();
  const last = dates[dates.length - 1];

  if (!last) {
    return dayOffset(20);
  }

  const next = new Date(`${last}T00:00:00Z`);
  next.setUTCDate(next.getUTCDate() + 1);

  return next.toISOString().slice(0, 10);
};

const SCREENS = [
  { slice: 'S2.1  rooms',        path: '/en/academics/rooms',   button: /create room/i },
  { slice: 'S2.5  calendar days', path: '/en/academics/calendar', button: /add|create|save/i, date: dayAfterLastCalendarEntry },
  // The first field on this form is a bare text box for a **student id** —
  // no placeholder, no picker, a number the person recording behaviour is
  // expected to know. Hence a positional hint, and hence the note in STATUS.
  { slice: 'S2.9  behaviour',    path: '/en/academics/behavior', button: /^record$/i, byIndex: { 0: '1' } },
  { slice: 'S3.5  standards',    path: '/en/exams/standards',   button: /create standard/i },
  // `Hours` is a text input rather than type=number, so a generic filler puts
  // a word in it. That is what surfaced the silent-refusal defect this sweep
  // found, and the hint is here so the sweep tests creation rather than
  // re-testing the message.
  { slice: 'S5.5  cpd',          path: '/en/hr/cpd',            button: /save cpd/i, hints: { Hours: '4' } },
  { slice: 'S5.5  observations', path: '/en/hr/observations',   button: /save|record/i },
];

/**
 * Refuse everything that is not this application.
 *
 * Without this the sweep hangs. Pages ask for Google Fonts, bunny.net and
 * Chromium's own autofill endpoint; in a sandbox with no route to them each
 * request waits for its timeout, and a run that should take two minutes
 * produces no output for forty. One run here logged **313** failed outbound
 * connections before it was killed.
 *
 * It is also the right behaviour regardless of sandbox: a smoke sweep should
 * measure this application, not a font CDN's availability, and should give the
 * same answer on a train.
 */
async function blockOffsiteRequests(context, base) {
  const host = new URL(base).host;

  await context.route('**/*', (route) => {
    const url = route.request().url();
    const local = url.startsWith('data:') || url.startsWith('blob:') || new URL(url).host === host;

    return local ? route.continue() : route.abort();
  });
}

/**
 * Chromium's own background traffic, which `context.route()` cannot touch
 * because it is not a page request.
 *
 * The autofill service alone accounted for most of **1,172** failed outbound
 * connections in one run here. Each waits for its timeout, so a sweep that
 * should take two minutes produced no output for forty and had to be killed.
 * With these flags the login page loads in ~500ms.
 */
const HERMETIC_ARGS = [
  '--disable-background-networking',
  '--disable-component-update',
  '--disable-features=AutofillServerCommunication,OptimizationHints,Translate,MediaRouter,InterestFeedContentSuggestions',
  '--no-first-run',
  '--no-default-browser-check',
];

const browser = await chromium.launch({
  ...(process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}),
  args: HERMETIC_ARGS,
});
const context = await browser.newContext();
// Sixty seconds, not thirty: staging behind Cloudflare stalled past thirty on two page loads in one run (STATUS §5fz).
context.setDefaultNavigationTimeout(60000);
await blockOffsiteRequests(context, BASE);
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
page.on('dialog', (d) => d.accept());

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', USER);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForLoadState('domcontentloaded');

if (page.url().includes('/login')) {
  console.error(`Could not sign in as ${USER}.`);
  await browser.close();
  process.exit(1);
}

let failures = 0;
for (const { slice, path, button, hints = {}, byIndex = {}, date } of SCREENS) {
  await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);

  // Resolved here, not when SCREENS was built: a date that has to avoid what is
  // already stored can only be chosen once the screen showing it is open.
  const wantedDate = typeof date === 'function' ? await date(page) : date;

  let target = null;
  for (const b of await page.$$('button')) {
    if (button.test((await b.innerText().catch(() => '')).trim())) { target = b; break; }
  }
  if (!target) { console.log(`${slice.padEnd(22)} NO CREATE BUTTON MATCHED "${button}"`); failures++; continue; }

  const form = await target.evaluateHandle((b) => b.closest('form') ?? b.parentElement);
  const marker = `MADE${RUN}${slice.split(/\s+/)[0].replace(/\./g, '')}`;
  let filledText = false;

  const controls = await form.$$('input:not([type=hidden]), select, textarea');
  for (const [index, control] of controls.entries()) {
    const tag = await control.evaluate((e) => e.tagName.toLowerCase());
    const type = await control.evaluate((e) => e.type ?? '');
    const placeholder = await control.evaluate((e) => e.getAttribute('placeholder') ?? '');
    try {
      if (byIndex[index] !== undefined) await control.fill(byIndex[index]);
      else if (hints[placeholder] !== undefined) await control.fill(hints[placeholder]);
      else if (tag === 'select') {
        const options = await control.$$eval('option', (os) => os.map((o) => o.value).filter(Boolean));
        if (options.length) await control.selectOption(options[0]);
      } else if (type === 'checkbox' || type === 'radio') { /* keep the default */ }
      else if (type === 'number') await control.fill('3');
      else if (type === 'date') await control.fill(wantedDate ?? dayOffset(0));
      else if (type === 'time') await control.fill('11:00');
      else if (!filledText) { await control.fill(marker); filledText = true; }
      else await control.fill('smoke');
    } catch (error) {
      // Never silent: swallowing this hid a bug in this very script for two
      // runs, and reported it as six screens refusing their own forms.
      console.log(`    [could not fill a field on ${path}] ${String(error).slice(0, 110)}`);
    }
  }

  await target.click();
  await page.waitForTimeout(1800);
  const afterSubmit = await page.locator('body').innerText().catch(() => '');

  // Reload before looking — see the note at the top of this file.
  await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1200);

  const text = await page.locator('body').innerText().catch(() => '');
  const values = await page.$$eval('input, textarea, select', (els) =>
    els.map((e) => String(e.value ?? '')).filter(Boolean)
  );

  if ((text + '\n' + values.join('\n')).includes(marker)) {
    console.log(`${slice.padEnd(22)} created through the form, and the row is there after a reload`);
  } else {
    const refusal = (afterSubmit.match(/^.*(required|must be|invalid|already|error).*$/im) || [])[0];
    console.log(`${slice.padEnd(22)} ${refusal ? 'form refused: ' + refusal.trim().slice(0, 80) : 'submitted, but no row afterwards'}`);
    failures++;
  }
}

console.log(`\n${SCREENS.length - failures}/${SCREENS.length} screens created a record through their own form.`);
await browser.close();
process.exit(failures > 0 ? 1 : 0);
