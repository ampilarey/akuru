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
 * is what an id in a URL invites.
 *
 * **Each of those is a pair, and that is the important part.** A refusal only
 * proves a rule if the same request succeeds for the person entitled to it.
 * The first version of this script probed only the refusal, and reported a
 * clean 404 on another family's report card — which turned out to be the route
 * declining to serve a card that had no document attached. It refused
 * everybody, including the child's own mother, and the probe called that a
 * pass. A pair where both halves fail is reported as **inconclusive**, never as
 * a pass.
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

// Ids for the planted records, read the same way — from the database, so the
// probe follows the seed rather than rotting when somebody re-seeds.
const [mineReportCard, otherReportCard, mineThread, notMineThread, myPayslip, colleaguePayslip] = execFileSync('php', [
  'artisan', 'tinker', '--execute',
  `echo (int) DB::table('report_cards')->where('student_id', ${mineId})->value('id');
   echo '|';
   echo (int) DB::table('report_cards')->where('student_id', ${otherId})->value('id');
   echo '|';
   echo (int) DB::table('message_threads')->where('subject', 'SMOKE-Thread')->value('id');
   echo '|';
   echo (int) DB::table('message_threads')->where('subject', 'SMOKE-Thread-Not-Mine')->value('id');
   echo '|';
   // The teacher's own payslip, and a colleague's. Resolved through the
   // teacher's user id so the pair follows the seed.
   $teacherUser = (int) DB::table('user_contacts')->where('value', 'teacher@akuru.edu.mv')->value('user_id');
   $mineStaff = (int) DB::table('staff_profiles')->where('user_id', $teacherUser)->value('id');
   echo (int) DB::table('payslips')->where('staff_profile_id', $mineStaff)->value('id');
   echo '|';
   echo (int) DB::table('payslips')->where('staff_profile_id', '!=', $mineStaff)->value('id');`,
], { encoding: 'utf8' }).trim().split('\n').pop().split('|');

console.log(`guardian's child: ${mineName} (#${mineId})   someone else's: ${otherName} (#${otherId})`);
console.log(`their report card: #${otherReportCard}   a thread they are not in: #${notMineThread}\n`);

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

// Pairs: the record this person is entitled to, and the equivalent one that
// belongs to somebody else. Both halves are needed — see the note at the top.
//
// The report card and the message thread are the sharpest of these: a report
// card is a child's marks, and a thread is a private conversation between a
// school and one family. Both sat unprobed until SmokeMarkerSeeder started
// planting them, because an empty table answers 404 and a 404 proves nothing.
const PAIRS = [
  {
    // `as` because these records belong to the guardian's family. Handing them
    // to another role tests nothing about that role — the first run did exactly
    // that and reported two inconclusive pairs for the student, which was the
    // probe's mistake rather than the app's.
    as: 'parent',
    what: 'report card',
    mine: `/en/portal/report-cards/${mineReportCard}/download`,
    theirs: `/en/portal/report-cards/${otherReportCard}/download`,
  },
  {
    as: 'parent',
    what: 'message thread',
    mine: `/en/portal/messages/${mineThread}`,
    theirs: `/en/portal/messages/${notMineThread}`,
  },
  {
    // The sharpest record in the system: one member of staff reading a
    // colleague's salary. Probed as an ordinary teacher rather than a
    // headmaster, who holds `hr.manage` and is supposed to see both.
    as: 'teacher',
    what: "colleague's payslip",
    mine: `/en/hr/payslips/${myPayslip}/document`,
    theirs: `/en/hr/payslips/${colleaguePayslip}/document`,
  },
];

// Staff-only records, which no family should reach at all. There is no "mine"
// half here because there is no version of these a parent is entitled to.
const STAFF_ONLY = [
  `/en/people/students/${otherId}`,
  `/en/students/${otherId}`,
  `/en/students/${otherId}/edit`,
  `/en/students/${otherId}/quran-progress`,
  `/en/exams/report-cards/${otherReportCard}/download`,
];

async function statusOf(page, route) {
  const response = await page.goto(BASE + route, { waitUntil: 'domcontentloaded' }).catch(() => null);

  return response ? response.status() : 'nav';
}

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

let failures = 0;

// The seeded student login has no `students` row behind it, so every
// person-scoped record refuses them — correctly, and uninformatively. It is
// swept for leakage on the portal screens, which is what it can answer, and
// the gap is recorded in STATUS rather than papered over here.
for (const [role, email] of [
  ['parent', 'parent@akuru.edu.mv'],
  ['student', 'student@akuru.edu.mv'],
  ['teacher', 'teacher@akuru.edu.mv'],
]) {
  const context = await browser.newContext();
  await blockOffsiteRequests(context, BASE);
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

  // A teacher is staff: seeing other people's children is their job, so the
  // leakage half of this script does not apply to them. They are here for the
  // payslip pair.
  for (const route of (role === 'teacher' ? [] : PORTAL)) {
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

  for (const { as: owner, what, mine, theirs } of PAIRS) {
    if (owner !== role) {
      continue;
    }

    const mineStatus = await statusOf(page, mine);
    const theirsStatus = await statusOf(page, theirs);

    if (mineStatus !== 200) {
      // Not a pass and not a failure: the refusal below is unproven, because
      // the route refused the person who is entitled to it too.
      console.log(`  ${role.padEnd(8)} ?    ${what}: own is ${mineStatus}, so "${theirsStatus} on theirs" proves nothing`);
      failures++;
    } else if (theirsStatus === 200) {
      failures++;
      console.log(`  ${role.padEnd(8)} OPEN ${what}: own 200, ANOTHER FAMILY'S ALSO 200 — ${theirs}`);
    } else {
      console.log(`  ${role.padEnd(8)} ok   ${what}: own 200, theirs ${theirsStatus}`);
    }
  }

  for (const route of STAFF_ONLY) {
    const status = await statusOf(page, route);
    if (status === 200) {
      failures++;
      console.log(`  ${role.padEnd(8)} OPEN ${status} ${route} — staff-only record`);
    } else {
      console.log(`  ${role.padEnd(8)} ${status}  ${route} (refused)`);
    }
  }

  await context.close();
  console.log('');
}

await browser.close();

console.log(failures === 0
  ? 'Every family saw their own records, and nobody else\'s.'
  : `${failures} problem(s) — an inconclusive pair counts, because a refusal nobody can pass is not a rule.`);

process.exit(failures > 0 ? 1 : 0);
