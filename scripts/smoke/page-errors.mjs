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
 * **It sweeps as every seeded role at once.** The first version signed in as
 * `admin` alone, and said so in its own limitations: *"a screen that works for
 * admin and throws for a parent would pass this sweep."* A portal page is
 * exactly where a role-specific runtime error would live, and those are the
 * pages families use. The six contexts run concurrently, so six roles cost
 * little more wall-clock than one.
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PASSWORD, SMOKE_CHROMIUM (as `sweep.mjs`),
 * SMOKE_ONLY to filter to routes containing a string, and SMOKE_ACCOUNTS to
 * override the `role:email,role:email` list.
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
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';
const ONLY = process.env.SMOKE_ONLY ?? '';

// The six roles `UserSeeder` creates. A 403 for one of them is usually the
// system working; a runtime error for one of them is never.
const ACCOUNTS = (process.env.SMOKE_ACCOUNTS ?? [
  'admin:admin@akuru.edu.mv',
  'headmaster:headmaster@akuru.edu.mv',
  'supervisor:supervisor@akuru.edu.mv',
  'teacher:teacher@akuru.edu.mv',
  'student:student@akuru.edu.mv',
  'parent:parent@akuru.edu.mv',
].join(',')).split(',').map((pair) => {
  const [role, email] = pair.split(':');

  return { role, email };
});

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

console.log(`${routes.length} screens × ${ACCOUNTS.length} roles.\n`);

const browser = await chromium.launch(
  process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}
);

/**
 * One role's pass over every screen.
 *
 * Runs in its own browser context so the six sessions cannot share a cookie
 * jar — which would silently sweep the same role six times and report a clean
 * bill of health for five roles nobody looked at.
 */
async function sweepAs({ role, email }) {
  const context = await browser.newContext();
  const page = await context.newPage();

  const errors = [];
  page.on('pageerror', (error) => errors.push(String(error).split('\n')[0].slice(0, 140)));

  await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="identifier"]', email);
  await page.fill('input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');

  if (page.url().includes('/login')) {
    await context.close();

    return { role, signedIn: false, looked: 0, denied: 0, broken: [], serverErrors: [] };
  }

  const broken = [];
  const serverErrors = [];
  let looked = 0;
  let denied = 0;

  for (const [index, route] of routes.entries()) {
    // A heartbeat, because six roles printing every route would interleave into
    // noise and printing nothing leaves an hour-long run indistinguishable from
    // a hung one. The single-role version learned this the expensive way; the
    // multi-role version re-learned it by being silent for twenty minutes.
    if (index > 0 && index % 50 === 0) {
      process.stdout.write(`  ${role.padEnd(11)} ${index}/${routes.length}…\n`);
    }

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
        process.stdout.write(`  ${role.padEnd(11)} ${status} ${route}\n`);
      } else {
        denied++;
      }
      continue;
    }

    looked++;
    if (errors.length > 0) {
      broken.push(`${route}\n      ${errors[0]}`);
      process.stdout.write(`  ${role.padEnd(11)} JS! ${route} — ${errors[0]}\n`);
    }
  }

  await context.close();
  process.stdout.write(`  ${role.padEnd(11)} done — ${looked} loaded, ${denied} denied, ${broken.length} broken\n`);

  return { role, signedIn: true, looked, denied, broken, serverErrors };
}

const results = await Promise.all(ACCOUNTS.map(sweepAs));

await browser.close();

console.log('');
let failures = 0;

for (const result of results) {
  if (! result.signedIn) {
    console.log(`${result.role.padEnd(11)} COULD NOT SIGN IN — nothing was checked for this role`);
    failures++;
    continue;
  }

  console.log(
    `${result.role.padEnd(11)} ${String(result.looked).padStart(3)} loaded, ` +
    `${String(result.denied).padStart(3)} denied, ` +
    `${result.broken.length} runtime error(s), ${result.serverErrors.length} server error(s)`
  );

  failures += result.broken.length + result.serverErrors.length;

  for (const line of [...result.broken, ...result.serverErrors]) {
    console.log('    ' + line);
  }
}

console.log(failures === 0 ? '\nNo runtime or server errors for any role.' : `\n${failures} problem(s).`);

process.exit(failures > 0 ? 1 : 0);
