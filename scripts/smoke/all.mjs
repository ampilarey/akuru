/**
 * Every walk, one command, one answer.
 *
 * Ten walk scripts had accumulated in this directory and **nine of them were
 * referenced nowhere an operator would look** (eleven now — `library` was
 * written against this runner rather than beside it) — not in `OPERATOR_CHECKLIST.md`,
 * not in `OWNER_ACTIONS.md`, not in any runner. `OWNER_ACTIONS` item 7, *walk
 * the app on staging*, is the gate the whole go-live path waits on, and it
 * named exactly one of them.
 *
 * So this is the entry point: `node scripts/smoke/all.mjs` runs the lot against
 * whatever `SMOKE_BASE_URL` points at and prints one table. It exits non-zero
 * if any walk fails, so it can gate a deploy.
 *
 * **About six and a half minutes** locally, measured, of which `page-errors`
 * is five — it loads 265 routes as six roles, which is some 1,600 page loads.
 * Against a real web server rather than `artisan serve` it should be quicker.
 *
 * ## Read-only and writing walks are separated, on purpose
 *
 * Four of these only look at screens. The other **twenty-nine write**: they build a course for a student, run a scheduled intake, set and sit an assessment, issue a certificate, sell a course for wallet money, tag an Arabic skill activity, set and mark a recitation and map a halaqa, enrol a pupil in a halaqa and approve their milestone, issue and redeem a gift card and read a protected book, set homework, post a notice, message a teacher and poll a class, send a trip sign-up with a fee and close it, publish a calendar day and mark a pupil late, double-book a teacher and be refused, enrol a
 * stranger on a course, submit and approve an absence note, request that a
 * child be collected, book a parent-teacher meeting, publish an article to
 * the library, publish an exam and a term's report cards to families, bill a
 * family and take their cash, and approve a staff member's leave and run
 * payroll where the flag allows. That is exactly what makes them worth running — and exactly why
 * nobody should discover it by pointing the runner at a live school.
 *
 *   node scripts/smoke/all.mjs            every walk (the default)
 *   node scripts/smoke/all.mjs --read     the four that only look
 *   node scripts/smoke/all.mjs --write    the twenty-nine that change data
 *   node scripts/smoke/all.mjs learn review     just those, by name
 *
 * A writing run against a host whose name does not look synthetic asks for
 * `SMOKE_I_KNOW_THIS_WRITES=yes` before it starts. That is a speed bump, not a
 * security control — it exists because the failure it prevents is an SMS to a
 * real family about a child who is not absent.
 *
 * ## What it needs
 *
 *   php artisan db:seed --class=SmokeMarkerSeeder
 *
 * Every walk reads markers that seeder plants, and it can now be run twice
 * (STATUS §5dz — it could not, for most of its life). Locally, a dev server on
 * `SMOKE_BASE_URL` and a Chromium at `SMOKE_CHROMIUM` if the bundled one is not
 * where Playwright expects.
 */
import { spawn } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const BASE = process.env.SMOKE_BASE_URL ?? 'http://127.0.0.1:8000';

/**
 * Find a Chromium once, here, instead of failing ten times.
 *
 * Playwright looks for a browser at a path baked in at install time, and on a
 * host where the browsers were installed separately — which is the normal
 * arrangement for a CI image or a managed runner — that path is wrong. Each
 * walk then dies with the same wall of text about running `playwright install`,
 * and the runner prints it ten times over.
 *
 * Only consulted when `SMOKE_CHROMIUM` is unset, and it says what it picked, so
 * it is a convenience and never a mystery.
 */
function findChromium() {
    const root = process.env.PLAYWRIGHT_BROWSERS_PATH;
    if (!root || !existsSync(root)) {
        return null;
    }

    for (const entry of readdirSync(root).filter((name) => name.startsWith('chromium-')).sort().reverse()) {
        const candidate = join(root, entry, 'chrome-linux', 'chrome');
        if (existsSync(candidate)) {
            return candidate;
        }
    }

    return null;
}

if (!process.env.SMOKE_CHROMIUM) {
    const found = findChromium();
    if (found) {
        process.env.SMOKE_CHROMIUM = found;
        console.log(`Using ${found} (set SMOKE_CHROMIUM to override).`);
    }
}

/**
 * `writes` is the honest bit: does running this change data on the host?
 *
 * `page-errors` signs in as six roles and loads 265 screens; `sweep` and
 * `own-data` read planted markers. Everything else fills in a form somebody
 * else will act on.
 */
const WALKS = [
    { name: 'page-errors', writes: false, asks: 'Does every screen survive being looked at?' },
    { name: 'sweep', writes: false, asks: 'Does each screen show the row planted for it?' },
    { name: 'own-data', writes: false, asks: 'Does a family see their own child, and only their own?' },
    { name: 'create-sweep', writes: true, asks: 'Can a person make one of these records, through the form?' },
    { name: 'register', writes: true, asks: 'Can a stranger enrol themselves?' },
    { name: 'author', writes: true, asks: 'Can a person build a course and get it in front of a student?' },
    { name: 'intake', writes: true, asks: 'Can the office run a scheduled intake, and can a student get a seat in it?' },
    { name: 'learn', writes: true, asks: 'Can a student take a lesson?' },
    { name: 'assess', writes: true, asks: 'Does a question a teacher writes become a mark a student gets?' },
    { name: 'certify', writes: true, asks: 'Does a student who finishes a course get a certificate a stranger can verify?' },
    { name: 'buy', writes: true, asks: 'Can a student buy a course, and does paying open it?' },
    { name: 'arabic', writes: true, asks: 'Does an Arabic skill activity, tagged to a letter, end up on the skill reports?' },
    { name: 'quran', writes: true, asks: 'Does a recitation range reach the student as a passage and come back marked, and does a halaqa map onto an offering?' },
    { name: 'hifz', writes: true, asks: 'Does what survives of the Hifz app still work for the people who use it?' },
    { name: 'reader', writes: true, asks: 'Can a reader read a protected book and keep their place, and does a gift card become wallet money?' },
    { name: 'family', writes: true, asks: 'Does the daily habit work — homework, the noticeboard, a message and its reply, a class poll?' },
    { name: 'signup', writes: true, asks: 'Does a sign-up sheet with a fee reach one class, wait for the parent, bill the family, and close?' },
    { name: 'school-day', writes: true, asks: 'Does a late mark reach the office\'s lists and the family, and does the calendar show families what is theirs?' },
    { name: 'timetable', writes: true, asks: 'Does the timetable refuse a teacher in two places at once, and let the office overrule it with a reason?' },
    { name: 'review', writes: true, asks: 'Does work a machine cannot mark come back marked?' },
    { name: 'absence', writes: true, asks: 'Does the register believe a family who reported an absence?' },
    { name: 'pickup', writes: true, asks: 'Does a child get handed over, and only to the adult who asked?' },
    { name: 'meetings', writes: true, asks: 'Does a teacher find out who booked a meeting with them?' },
    { name: 'library', writes: true, asks: 'Can somebody become a writer and get something published?' },
    { name: 'peer-review', writes: true, asks: 'Does the research peer-review gate refuse, and can it be satisfied?' },
    { name: 'earnings', writes: true, asks: 'Does a sale become the writer\'s money, and does the payout gate say why not?' },
    { name: 'money', writes: true, asks: 'Can the school take money without a gateway, and give it back?' },
    { name: 'pronounce', writes: true, asks: 'Can a student hand in a recording, and does a human ear decide it?' },
    { name: 'recite', writes: true, asks: 'Can a student hand in a recitation, and does a teacher mark it?' },
    { name: 'exams', writes: true, asks: 'Does a mark a teacher types end up on a report card a family can open?' },
    { name: 'fees', writes: true, asks: 'Does a fee become an invoice a family can see, and cash a receipt they can open?' },
    { name: 'hr', writes: true, asks: 'Does a staff member\'s month — check-in, leave, appraisal, payslip — go through?' },
    { name: 'mobile', writes: false, asks: 'Does this work on a phone?' },
];

const args = process.argv.slice(2);
const named = args.filter((arg) => !arg.startsWith('--'));
const onlyRead = args.includes('--read');
const onlyWrite = args.includes('--write');

let selected = WALKS;
if (named.length > 0) {
    selected = WALKS.filter((walk) => named.includes(walk.name));
    const unknown = named.filter((name) => !WALKS.some((walk) => walk.name === name));
    if (unknown.length > 0) {
        console.error(`Unknown walk(s): ${unknown.join(', ')}`);
        console.error(`Known: ${WALKS.map((walk) => walk.name).join(', ')}`);
        process.exit(2);
    }
} else if (onlyRead) {
    selected = WALKS.filter((walk) => !walk.writes);
} else if (onlyWrite) {
    selected = WALKS.filter((walk) => walk.writes);
}

// A hostname nobody could mistake for a school's real address. Deliberately
// generous — the point is to catch `akuru.edu.mv`, not to be clever.
const looksSynthetic = /localhost|127\.0\.0\.1|\btest\.|\bstaging\.|\bdev\./i.test(BASE);

if (selected.some((walk) => walk.writes) && !looksSynthetic && process.env.SMOKE_I_KNOW_THIS_WRITES !== 'yes') {
    console.error(`\nRefusing to run writing walks against ${BASE}.`);
    console.error('These walks submit absence notes, request that children be collected,');
    console.error('book meetings, enrol people, hand in recordings, publish exam results, issue invoices and approve leave. On a host with');
    console.error('real families on it that means real messages to real people.\n');
    console.error('If this host is synthetic, re-run with SMOKE_I_KNOW_THIS_WRITES=yes,');
    console.error('or use --read for the four walks that only look at screens.\n');
    process.exit(2);
}

/**
 * One line per walk, from walks that do not agree on what a line looks like.
 *
 * The ten scripts were written at different times and end **five different
 * ways**: six say "N/M steps passed", `sweep` says "N/M screens showed what was
 * planted for them", `create-sweep` says "N/M screens created a record through
 * their own form", and `page-errors` and `own-data` print a sentence with no
 * score in it at all ("No runtime or server errors for any role.").
 *
 * The first version of this runner matched only the first phrasing and printed
 * a bare `—` for four walks that had answered perfectly well. Rather than
 * rewrite ten working scripts to satisfy the summary, the summary reads what
 * they actually say: a count where there is one, and otherwise their own last
 * sentence, which is the line each was written to end on.
 */
function summarise(out) {
    const count = out.match(/(\d+)\/(\d+) (?:steps passed|screens )/);
    if (count) {
        return `${count[1]}/${count[2]}`;
    }

    const lines = out.split('\n').map((line) => line.trim()).filter(Boolean);
    const last = lines[lines.length - 1] ?? '';

    return last.length > 46 ? `${last.slice(0, 45)}…` : (last || '—');
}

function run(walk) {
    return new Promise((resolve) => {
        const started = Date.now();
        const child = spawn(process.execPath, [join(HERE, `${walk.name}.mjs`)], {
            env: process.env,
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        let out = '';
        child.stdout.on('data', (chunk) => { out += chunk; });
        child.stderr.on('data', (chunk) => { out += chunk; });

        child.on('close', (code) => {
            resolve({
                walk,
                code,
                out,
                score: summarise(out),
                seconds: Math.round((Date.now() - started) / 1000),
            });
        });
    });
}

console.log(`\nWalking ${BASE} — ${selected.length} of ${WALKS.length} walks.\n`);

const results = [];

// One at a time. They share a database and several of them sign in as the same
// people; running them in parallel would have them stepping on each other's
// fixtures, which is how a walk reports a failure that belongs to its neighbour.
for (const walk of selected) {
    process.stdout.write(`  ${walk.name.padEnd(13)} … `);
    const result = await run(walk);
    results.push(result);
    console.log(`${result.code === 0 ? "ok  " : "FAIL"}  ${result.score.padEnd(46)}  ${result.seconds}s`);
}

const failed = results.filter((result) => result.code !== 0);

// Full output for the failures only. A passing run should be readable at a
// glance; a failing one should not need a second command to explain itself.
for (const result of failed) {
    console.log(`\n${'─'.repeat(72)}\n${result.walk.name} — ${result.walk.asks}\n`);
    console.log(result.out.trimEnd());
}

console.log(`\n${'═'.repeat(72)}`);
console.log(`${results.length - failed.length}/${results.length} walks passed against ${BASE}.`);

if (failed.length > 0) {
    console.log(`Failed: ${failed.map((result) => result.walk.name).join(', ')}`);
}
console.log('');

process.exit(failed.length === 0 ? 0 : 1);
