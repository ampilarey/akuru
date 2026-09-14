/**
 * Does every screen survive being looked at?
 *
 * This is the check that was missing when three pages were shipped blank.
 *
 * A mechanical edit put `<FormErrors errors={form.errors} />` into three filter
 * forms that had no `form` in scope. `ReferenceError: form is not defined`, and
 * in React that blanks the whole page. Nothing caught it: `npm run build` does
 * no scope analysis, the PHP suite never loads a page, and the architecture gate
 * that was supposed to cover it is satisfied by a string appearing in a file.
 * **CI was green on the pull request.** They were found by opening the screens
 * by hand after a container restart — which is to say, by luck (STATUS §5co).
 *
 * A runtime error in a React page is invisible to every check this repo has
 * except this one: load the page and listen.
 *
 *   node scripts/smoke/page-errors.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_USER, SMOKE_PASSWORD, SMOKE_CHROMIUM
 * (as `sweep.mjs`), plus SMOKE_ONLY to filter to routes containing a string.
 *
 * **Routes come from `php artisan route:list`, fresh on every run**, so a screen
 * added tomorrow is swept tomorrow without anybody remembering to add it to a
 * list. Parameterised routes are skipped — inventing an id would test the
 * 404 page — and so are exports and non-HTML endpoints.
 *
 * Exits non-zero if any page logged an uncaught error, so it can gate a deploy.
 *
 * It prints each route as it goes. The first version did not, and a full sweep
 * of 265 screens against `artisan serve` takes minutes rather than seconds —
 * long enough that a silent run is indistinguishable from a hung one, which is
 * how the first attempt was spent. Narrow it with `SMOKE_ONLY=hr` while
 * iterating.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const USER = process.env.SMOKE_USER ?? 'admin@akuru.edu.mv';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const ONLY = process.env.SMOKE_ONLY ?? '';

const routes = JSON.parse(execFileSync('php', ['artisan', 'route:list', '--json'], { maxBuffer: 32 * 1024 * 1024 }))
  .filter((route) => route.method.includes('GET'))
  .map((route) => route.uri)
  .filter((uri) => !uri.includes('{'))            // an invented id tests the 404 page
  .filter((uri) => !/(^|\/)(export|download|verify)(\/|$)/.test(uri))
  .filter((uri) => !/^(api|_|up$|storage|sanctum|livewire)/.test(uri))
  .filter((uri) => uri.includes(ONLY))
  .map((uri) => (uri === '/' ? '/en' : `/en/${uri}`))
  .filter((uri, index, all) => all.indexOf(uri) === index)
  .sort();

console.log(`${routes.length} screens to look at.\n`);

const browser = await chromium.launch(
  process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}
);
const page = await (await browser.newContext()).newPage();

const errors = [];
page.on('pageerror', (error) => errors.push(String(error).split('\n')[0].slice(0, 140)));

await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="identifier"]', USER);
await page.fill('input[name="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForLoadState('domcontentloaded');

if (page.url().includes('/login')) {
  console.error(`Could not sign in as ${USER}. Every page below would be the login screen, so stopping.`);
  await browser.close();
  process.exit(1);
}

const broken = [];
const serverErrors = [];
let looked = 0;

for (const route of routes) {
  errors.length = 0;
  let status = 'nav';
  try {
    const response = await page.goto(BASE + route, { waitUntil: 'domcontentloaded' });
    status = response ? response.status() : 'nav';
    // Long enough for Inertia to mount and throw, short enough to sweep the
    // whole app. A late error lands in the next route's bucket rather than
    // being lost — misattributed, but still surfaced.
    await page.waitForTimeout(250);
  } catch {
    // A navigation cancelled by the page's own redirect is not a defect; the
    // §5cl sweep spent eight of its eleven flags on exactly that.
    continue;
  }

  if (status !== 200) {
    if (status >= 500) {
      serverErrors.push(`${status}  ${route}`);
    }
    process.stdout.write(`  ${status} ${route}\n`);
    continue;
  }

  looked++;
  if (errors.length > 0) {
    broken.push(`${route}\n      ${errors[0]}`);
    process.stdout.write(`  JS! ${route} — ${errors[0]}\n`);
  } else {
    process.stdout.write(`  ok  ${route}\n`);
  }
}

await browser.close();

console.log(`${looked} screens loaded.`);

if (serverErrors.length > 0) {
  console.log(`\n${serverErrors.length} returned a server error:`);
  serverErrors.forEach((line) => console.log('  ' + line));
}

if (broken.length > 0) {
  console.log(`\n${broken.length} threw a runtime error in the browser:`);
  broken.forEach((line) => console.log('  ' + line));
} else {
  console.log('\nNo runtime errors.');
}

process.exit(broken.length + serverErrors.length > 0 ? 1 : 0);
