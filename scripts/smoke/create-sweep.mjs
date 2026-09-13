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

// Unique per run, so re-running cannot collide with its own earlier rows.
const RUN = Date.now().toString().slice(-6);
const dayOffset = (n) => {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
};

const SCREENS = [
  { slice: 'S2.1  rooms',        path: '/en/academics/rooms',   button: /create room/i },
  { slice: 'S2.5  calendar days', path: '/en/academics/calendar', button: /add|create|save/i, date: dayOffset(20 + (Number(RUN) % 40)) },
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

const browser = await chromium.launch(
  process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}
);
const page = await (await browser.newContext()).newPage();
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
      else if (type === 'date') await control.fill(date ?? dayOffset(0));
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
