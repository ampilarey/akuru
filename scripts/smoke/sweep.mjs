/**
 * Does each screen show the row that was planted for it?
 *
 * `STATUS.md` §5cl already swept all 116 staff screens for server errors and
 * blank shells, and found none. What that could not answer is the failure this
 * project actually keeps hitting — *"CI-green slices still left empty grids"*:
 * a screen that loads perfectly and shows nothing, because the read path and
 * the write path disagree about a filter, a year, or a column.
 *
 * So this plants one distinctive row per slice
 * (`database/seeders/SmokeMarkerSeeder.php`) and then asks each screen whether
 * that exact string came back.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/sweep.mjs
 *
 * Environment:
 *   SMOKE_BASE_URL   default http://127.0.0.1:8000
 *   SMOKE_USER       default admin@akuru.edu.mv
 *   SMOKE_PASSWORD   default password
 *   SMOKE_CHROMIUM   path to a Chromium binary; omit to let Playwright resolve
 *                    its own (this repo's container pre-installs one under
 *                    /opt/pw-browsers, which is why the variable exists).
 *
 * **It reads input values as well as text.** Several of these screens are
 * inline-edit grids whose cells are `<input value="...">`, and `innerText`
 * returns nothing for those — the first run of this sweep reported three false
 * negatives for exactly that reason, which is a better argument for writing it
 * down than any of the passes.
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const USER = process.env.SMOKE_USER ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// slice · path · the exact string SmokeMarkerSeeder planted, or null for a
// screen whose content is not a seeded row (a form, a report shell).
const CHECKS = [
  ['S1.3  consent',          '/en/people/students',        null],
  ['S1.4  staff profiles',   '/en/people/staff',           null],
  ['S2.1  rooms',            '/en/academics/rooms',        'SMOKE-Room-A'],
  ['S2.4  room bookings',    '/en/academics/bookings',     'SMOKE-Booking'],
  ['S2.5  calendar days',    '/en/academics/calendar',     'SMOKE-Holiday'],
  ['S2.9  behaviour',        '/en/academics/behavior',     'SMOKE-Category'],
  ['S2.10 requests',         '/en/academics/requests',     'SMOKE-Request'],
  ['S3.5  standards',        '/en/exams/standards',        'SMOKE-Standard'],
  ['S3.7  awards',           '/en/exams/awards',           'SMOKE-Award'],
  ['S4.2  fee structures',   '/en/finance/fee-structures', 'SMOKE-Structure'],
  ['S4.4  payment plans',    '/en/finance/payment-plans',  'SMOKE-INV-1'],
  ['S4.5  adjustments',      '/en/finance/adjustments',    '7.77'],
  ['S5.1  staff attendance', '/en/hr/attendance',          'SMOKE-Attendance'],
  ['S5.2  leave types',      '/en/hr/leave-types',         null],
  ['S5.2  leave balances',   '/en/hr/leave-balances',      '333'],
  ['S5.3  contracts',        '/en/hr/contracts',           '12345'],
  ['S5.5  appraisals',       '/en/hr/appraisals',          null],
  ['S5.5  cpd',              '/en/hr/cpd',                 'SMOKE-CPD'],
  ['S5.5  observations',     '/en/hr/observations',        'SMOKE-Observation'],
  ['S5.4  careers (public)', '/en/careers',                'SMOKE-Vacancy'],

  // The C-track. Everything above is the S-track, which is how those §2 rows
  // moved while 1A/1B/2 stayed UNVERIFIED — no marker had ever been planted in
  // the course engine, the larger half of the product.
  //
  // A seeded database already has courses, levels and audiences, so "does the
  // page show rows" would have passed on all three and proved nothing about
  // whether *this* row reaches the screen.
  ['1A.2  catalog courses',  '/en/catalog/courses',        'SMOKE-Course'],
  ['1A.6  glossary',         '/en/catalog/glossary',       'SMOKE-Term'],
  ['1B.1  offerings',        '/en/catalog/offerings',      'SMOKE-Offering'],
  ['1A.2  levels',           '/en/catalog/levels',         'SMOKE-Level'],
  ['1A.2  audiences',        '/en/catalog/audiences',      'SMOKE-Audience'],
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
await blockOffsiteRequests(context, BASE);
const page = await context.newPage();

const jsErrors = [];
page.on('pageerror', (e) => jsErrors.push(String(e).slice(0, 160)));

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', USER);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForLoadState('domcontentloaded');

if (page.url().includes('/login')) {
  console.error(`Could not sign in as ${USER}. Nothing below would mean anything, so stopping.`);
  await browser.close();
  process.exit(1);
}

let failures = 0;
const rows = [];

for (const [slice, path, marker] of CHECKS) {
  jsErrors.length = 0;
  let status = '—';
  try {
    const response = await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
    status = response ? response.status() : '—';
    await page.waitForTimeout(900);
  } catch (error) {
    rows.push([slice, status, 'NAV ERROR: ' + String(error).slice(0, 70), path]);
    failures++;
    continue;
  }

  const text = await page.locator('body').innerText().catch(() => '');
  // Inline-edit grids keep their data in input values, which innerText drops.
  const values = await page.$$eval('input, textarea, select', (els) =>
    els.map((el) => String(el.value ?? '')).filter(Boolean)
  );
  const haystack = text + '\n' + values.join('\n');

  let verdict;
  if (status !== 200) { verdict = `HTTP ${status}`; failures++; }
  else if (jsErrors.length) { verdict = 'JS ERROR: ' + jsErrors[0]; failures++; }
  else if (text.trim().length < 120) { verdict = 'BLANK'; failures++; }
  else if (marker === null) verdict = 'renders (no seeded row for this screen)';
  else if (haystack.includes(marker)) verdict = 'shows its row';
  else { verdict = `DOES NOT SHOW "${marker}"`; failures++; }

  rows.push([slice, status, verdict, path]);
}

const width = Math.max(...rows.map((r) => r[0].length));
for (const [slice, status, verdict, path] of rows) {
  console.log(`${slice.padEnd(width)}  ${String(status).padEnd(4)}  ${verdict.padEnd(42)}  ${path}`);
}
console.log(`\n${rows.length - failures}/${rows.length} screens showed what was planted for them.`);

await browser.close();
process.exit(failures > 0 ? 1 : 0);
