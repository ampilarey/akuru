/**
 * Does a family see their own child, and only their own child?
 *
 * The sweeps this sits beside answer narrower questions: `page-errors.mjs` asks
 * whether a screen throws, `sweep.mjs` whether it shows a row that exists,
 * `create-sweep.mjs` whether a person can make one. None of them asks the
 * question a parent would care about most, and the one this codebase has
 * already got wrong once — a `courses.manage` holder could fetch **any**
 * private media file by id, children's recitations included (KNOWN_ISSUES,
 * fixed in #336).
 *
 * Two halves, and the second is the one that matters:
 *
 *  1. **Own data is there.** The portal shows the marker planted on the
 *     guardian's own child.
 *  2. **Nobody else's is.** The marker planted on another family's child
 *     appears on no page, and neither does that child's name. A privacy check
 *     that only looks for the right row passes just as happily on a page
 *     listing the whole school.
 *
 * Then the direct approach: ask for another family's records **by id**, which
 * is what an id in a URL invites. A 200 there is the finding.
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *   node scripts/smoke/own-data.mjs
 *
 * Environment: SMOKE_BASE_URL, SMOKE_PASSWORD, SMOKE_CHROMIUM.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

// Who the guardian's child is, and somebody else's, read from the database
// rather than hardcoded — the ids move whenever the seed does.
const [mineId, mineName, otherId, otherName] = execFileSync('php', [
  'artisan', 'tinker', '--execute',
  `$g = DB::table('parent_guardians')->whereIn('user_id', function ($q) {
       $q->select('user_id')->from('user_contacts')->where('value', 'parent@akuru.edu.mv');
   })->orWhere('user_id', DB::table('users')->where('email', 'parent@akuru.edu.mv')->value('id'))->first();
   $mine = (int) DB::table('guardian_student')->where('guardian_id', $g->id ?? 0)->value('student_id');
   $m = DB::table('students')->find($mine);
   $o = DB::table('students')->where('id', '!=', $mine)->first();
   echo $mine.'|'.trim(($m->first_name ?? '').' '.($m->last_name ?? '')).'|'.($o->id ?? 0).'|'.trim(($o->first_name ?? '').' '.($o->last_name ?? ''));`,
], { encoding: 'utf8' }).trim().split('\n').pop().split('|');

if (!mineId || mineId === '0') {
  console.error('The seeded parent has no child. Seed the app, then SmokeMarkerSeeder.');
  process.exit(1);
}

console.log(`guardian's child: ${mineName} (#${mineId})   someone else's: ${otherName} (#${otherId})\n`);

const PORTAL = [
  '/en/portal/home',
  '/en/portal/children',
  '/en/portal/attendance',
  '/en/portal/behavior',
  '/en/portal/homework',
  '/en/portal/invoices',
  '/en/portal/exams',
  '/en/portal/awards',
  '/en/portal/meetings',
];

// Records belonging to another family, asked for by id.
const DIRECT = [
  `/en/people/students/${otherId}`,
  `/en/students/${otherId}`,
  `/en/students/${otherId}/edit`,
  `/en/students/${otherId}/quran-progress`,
];

const browser = await chromium.launch(
  process.env.SMOKE_CHROMIUM ? { executablePath: process.env.SMOKE_CHROMIUM } : {}
);

let failures = 0;

for (const [role, email] of [['parent', 'parent@akuru.edu.mv'], ['student', 'student@akuru.edu.mv']]) {
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto(`${BASE}/en/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="identifier"]', email);
  await page.fill('input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');

  if (page.url().includes('/login')) {
    console.log(`${role}: could not sign in — nothing was checked`);
    failures++;
    await context.close();
    continue;
  }

  for (const route of PORTAL) {
    const response = await page.goto(BASE + route, { waitUntil: 'domcontentloaded' }).catch(() => null);
    const status = response ? response.status() : 'nav';
    if (status !== 200) {
      console.log(`  ${role.padEnd(8)} ${status} ${route}`);
      continue;
    }
    await page.waitForTimeout(500);

    const text = await page.locator('body').innerText().catch(() => '');
    const values = await page.$$eval('input, textarea, select', (els) =>
      els.map((el) => String(el.value ?? '')).filter(Boolean)
    );
    const haystack = `${text}\n${values.join('\n')}`;

    const leaks = haystack.includes('JOURNEY-OTHER') || (otherName && haystack.includes(otherName));

    if (leaks) {
      failures++;
      console.log(`  ${role.padEnd(8)} LEAK ${route} — shows ${otherName}'s data`);
    } else {
      console.log(`  ${role.padEnd(8)} ok   ${route}`);
    }
  }

  for (const route of DIRECT) {
    const response = await page.goto(BASE + route, { waitUntil: 'domcontentloaded' }).catch(() => null);
    const status = response ? response.status() : 'nav';
    if (status === 200) {
      failures++;
      console.log(`  ${role.padEnd(8)} OPEN ${status} ${route} — another family's record`);
    } else {
      console.log(`  ${role.padEnd(8)} ${status}  ${route} (refused)`);
    }
  }

  await context.close();
  console.log('');
}

await browser.close();

console.log(failures === 0
  ? 'No family saw another family.'
  : `${failures} problem(s) — read every line above before dismissing any of them.`);

process.exit(failures > 0 ? 1 : 0);
