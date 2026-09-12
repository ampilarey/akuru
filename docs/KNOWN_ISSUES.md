# Known issues

Ranked by **harm > wrong data > blocked task > confusion > cosmetic**.  
Evidence is a pilot section, a `file:line` on current `main` (after #86–#93), or a merged PR.

Re-checked 2026-08-26 against merged `main` and Round 3 (`docs/PILOT_REHEARSAL.md`);
the owner-decision list below re-audited against `main` on **2026-09-12**. Round 2 items that landed in #86–#92 are in **Fixed on main** at the bottom — not dropped, not still claimed open.

---

## Decisions only the owner can make (consolidated 2026-09-10, re-audited 2026-09-12)

The agent-buildable backlog is empty. **Re-audited 2026-09-12** — item 6 was
wrong (all seven Wave 4 features ship), item 9 was materially overstated, and
the audit itself uncovered a live defect, now fixed (see "Found by the
2026-09-12 audit" below). Everything below was raised during
autonomous sessions, deliberately **not** decided, and scattered across STATUS
sections — collected here so there is one list to work from. Each is phrased as
a question with a default, so "do nothing" is always a legible choice.

**Before any deploy**

1. **Walk the app on `test.akuru.edu.mv`.** The core daily loop is now walked
   **locally** and works end to end (STATUS §5bu/§5bv) — that half is done, and
   it found a real defect. What remains is the *deployment*: nothing has been
   executed on the staging host, whose staff login is itself the standing P0
   below. A local walk cannot tell you the deploy script, the built assets, or
   the seeded database on that host are sound.
2. **Set `BML_WEBHOOK_SECRET`**, and confirm with BML that they sign HMAC-`sha256`
   over the raw body under `X-BML-Signature`. The webhook now fails closed
   (STATUS §5bp), so **with no secret and no opt-out, no payment will confirm**.
   The implementation's assumption about their scheme has never been checked
   against BML's own documentation.
3. **Verify the `permissions` table** on `test.akuru.edu.mv` holds all 34 dotted
   names, and that the six seeder-only roles exist. §5bo fixed three rows going
   forward; it cannot tell you what that database currently holds.
4. **Rotate the super-admin password.** Raised repeatedly; the seeded
   credentials are in `docs/AUTHENTICATION_GUIDE.md`.
5. **Apply branch protection** (`docs/BRANCH_PROTECTION.md`). Structurally
   impossible from an agent session.

**Product scope**

6. ~~**Wave 4 — does the Institute actually run these?**~~ **All seven are
   built** (re-verified against `main` on 2026-09-12: routes, models,
   migrations and feature tests for each). E8 pick-up, E15 lost & found,
   E16 physical lending, E17 interest groups, E18 gate arrivals, E19 sensitive
   information, E21 work showcase.

   This entry said "Verified against the code on 2026-09-10: all seven
   genuinely unbuilt" and priced them at ≈7½–8½ weeks. That was already wrong
   when written for some of them and wrong for all seven within days, and it
   is the worst kind of stale: it asked the owner to decide whether to fund
   work that already existed. Two decisions **do** survive it, and they are
   about operating the features rather than building them: **E18 needs the
   hardware decision** (what scans at the gate) and **E19 needs the privacy
   policy** that governs who may read a sensitive note.
7. **`docs/APPSHELL_NAV_IA.md`** — accept / accept with edits / reject. The nav
   wrap is still live. See top-five item 2. Now measured in a browser: ~90
   links across **eleven rows**, roughly the top quarter of a 1200px viewport,
   on every page (STATUS §5bv).
7b. **Bidi alignment on Dhivehi and Arabic screens** (STATUS §5bz). An English
   sentence on an RTL page renders its full stop at the **front**
   (`.No exams still in marks entry after the exam date`). One CSS rule fixes
   it — `unicode-bidi: plaintext` under `[dir="rtl"]`, tested live — but the
   same rule left-aligns English text, and with ~87% of the UI still English
   that means nearly every line on every RTL screen. **Correct punctuation and
   left alignment, or wrong punctuation and right alignment**, until translation
   catches up and the question disappears. Screenshots of both were captured
   during the walk; decide from those, not from this description.
8. **Confirm or reject `docs/migrations/s11-deploy-3-cleanup-proposal.md`.**

**Security and permissions**

9. **The Hifz module declares no role guard on its routes** —
   `app/Domains/Hifz/routes.php` is still `['auth', 'trackActivity']` and
   nothing else. **Narrowed twice since this was written.** F5 (ADR-029) took
   sessions, session records, mistakes and the mushaf admin out of that file
   entirely; what is left is the hub, five dashboards, programmes, enrolments,
   milestones and reports.

   And the remaining surface is **not** the open door the sentence implies.
   `tests/Feature/Hifz/HifzCrossRoleAccessTest.php` now sweeps every
   parameterless Hifz GET as two different families in the same halaqa and
   pins what actually happens: every controller either authorizes explicitly or
   scopes its query through `HifzScopeService`, the reports are gated on
   `view_hifz_reports`, and the one controller that does neither
   (`HifzHubController`) is a redirect ending in `abort(403)`. No screen showed
   one family the other's child.

   So the role matrix is still a product decision worth taking — a parent can
   load `/hifz/supervisor` and see a supervisor-shaped page scoped to their own
   children, which is confusing — but it is a **tidiness** decision, not an
   exposure. Default: leave the routes as they are and keep the test.
10. **`admin` is granted `Permission::all()`, identical to `super_admin`**,
    while the comment directly above it in `RoleSeeder` says "most permissions
    (school operations, not system-level)". The code and its comment disagree;
    which one is wrong is yours to say.
11. **Only three of the nine roles are created by a migration** (`super_admin`,
    `reviewer`, `writer`). The other six exist only if `RoleSeeder` has run, so
    every permission-granting migration no-ops its role grants on a
    migrate-only database — meaning **role changes cannot be shipped by deploy
    at all**. Moving role creation into a migration touches the role matrix.
12. **A supervisor can grant a place on a paid course.** The admissions group is
    guarded by role alone, while the money endpoints next door also require
    `can:payments.refund` / `can:payments.record`. Tightening it changes who can
    do their job during admissions.

**Data model**

13. **Repeating pupils keep a roster row on the *old* academic year.**
    `repeat()` touches the existing row rather than creating one on the target
    year. Intended, or a gap?

**Language**

14. **200 English UI keys have no Dhivehi and no Arabic**, 152 of them
    referenced from live `public.*` pages — the marketing site, admissions and
    checkout. A ratchet stops it growing (STATUS §5bm); closing it needs a
    native speaker, and deliberately was not attempted by the agent. Both
    languages are now editable from the admin screen without a deploy
    (§5bn), so this can be done by a person with no repository access.

---

## Found by the 2026-09-12 audit

### Two guardian↔student pivots, and the product wrote the wrong one — **fixed**

`guardian_student` is the live pivot: every notification listener (absence SMS,
behaviour SMS, exam results, report cards) and eleven People actions — the
collection policy, financial responsibility, guardian access — read it.
`student_parent` is a second pivot that **nothing in the application wrote**.

`User::schoolChildren()` read the dead one, through
`ParentGuardian::students()`. Its only caller,
`ParentHifzDashboardController`, turns an empty child list into `abort(403)` —
so **every parent the product itself had linked was refused their own child's
Hifz dashboard**. `HifzScopeService::parentChildIds()` had the same bug, so
`canAccessStudent()` refused them a second time.

It looked like it worked because `HifzDemoSeeder` was the single writer of
`student_parent`: the demo parent could open the screen and nobody else could.

Fixed by adding `ParentGuardian::children()` over `guardian_student` and
pointing both readers and the seeder at it. `students()` is marked deprecated
rather than deleted, and the table is left populated — rule 9 does not drop a
populated table in the deploy that stops using it. Removing it is a Deploy-3
cleanup item.

**Found by a positive control, not by the guard.** The cross-role test asserts
that no family sees another's child; that assertion passed while every parent
screen was 403ing, because a page nobody can load leaks nothing. The assertion
that caught it is the one requiring each caller to have seen *their own* child
somewhere — without it the whole sweep was vacuous for parents.

---

## Top five (remaining)

1. **Staging staff login** — seed passwords 302 back to login; no SSH from this environment. Blocks any judgement that `test.akuru.edu.mv` is a school.
1b. ~~**Nothing merged since 2026-08 has been walked in a browser**~~ — **partly closed 2026-09-10 (STATUS §5bu, §5bv).** The core daily loop is now walked **locally**, end to end, in Chromium: a teacher generates a register, fills it, marks a pupil absent and submits; the absence lands on the admin absence list; the homework and the absence both reach the family portal. That walk found a real defect (nested translation lines editable in neither language, fixed in #234). **Still open:** nothing has been walked on `test.akuru.edu.mv` itself, which is a separate question about the deployment rather than the code. See decision 1 above.
2. **AppShell nav IA** — **proposed, awaiting decision.** **105** wrapping `<Link href=` in `AppShell.jsx` — 83 when this line was written, 74 at the IA proposal, plus Glossary, admin Events, portal Event signup, Certificates, Completions, Performance, Home, Meetings, Overview. It grows with every slice that adds a screen, which is itself the argument. C3 extends `/catalog/reviews` (already linked). D1 adds Home. D2 adds Meetings. D3 adds Overview. Proposal in `docs/APPSHELL_NAV_IA.md` (PR #98): grouped by role and frequency. **Do not implement** until Accept / Accept with edits / Reject. The wrap is still live.
3. **Parent notified column shows — on excused rows** — column exists (#86); SMS body is not in the portal.
4. ~~Shared Add-term form on every year card~~ — **fixed** (#13).
5. ~~Exam schedule form defaults wander~~ — **fixed** (#19), along with the
   report-card publish default (#21).

---

## P0 — Harm (real people, real messages, real money)

### 1. Staging staff login does not work with documented seed passwords

**Severity:** harm-adjacent / blocked production judgement — the intended pilot host cannot be used; public 200 hides that.

**Evidence:** Round 1 step 0; Round 2 opening; Round 3 ranked #1; archive “Staging credential smoke (2026-08-23)”. `https://test.akuru.edu.mv/en/login` 302s back to login for `admin@akuru.edu.mv` / `password`. This environment cannot SSH (`docs/STAGING.md` webhook only).

**Still open.** (Former P0 SMS live-bind is **fixed** — see Fixed on main #86.)

---

## P1 — Wrong data (identity, grades, documents)

### 2. Term grades render blank unless a weight scheme is actually saved

**Fixed** — Weights form posts numeric type percents (seeded from `default_weight`, sum 100) and redirects with `academic_year_id` so the year scheme resolves. See **Fixed on main**.

### 3. “Report cards” are HTML with empty grades, not PDF

**Fixed as a renderer decision** — HTML is the supported production output (ADR-012 amended). Empty cells without weights is the Weights issue (P1 #2), not a PDF bug. See **Fixed on main**.

### 4. Roster picker can still show two rows for the same identity with different numbers

**Fixed** — `identity_key` is name + DOB + national ID. Student number (including blank) and class do not distinguish. Assign still requires an explicit `student_id`. See **Fixed on main**.

### 5. Unification matcher / staging collisions (historical, still a staging gate)

**Severity:** wrong data if `--backfill` is run on a messy DB. Staging verify last recorded **red** (four collisions, 12/13 guardian users missing after `users:clear-non-admin`).

**Evidence:** `STATUS_ARCHIVE.md` staging 2026-08-25 and TRACK A. Representative local gate green (ADR-021) is **not** staging green.

---

## P2 — Blocked task (cannot finish the job in the product)

### 6. People → Students cannot create a child

**Fixed** — create/edit on `people.students.*` via `SaveStudentAction` + `ChangeStudentStatusAction`; guardian pivot; course-only nullables. See **Fixed on main**.

### 7. Class teacher assignment did not stick on Grade 5 B

**Fixed** — existing classes can PUT `class_teacher_id` from the class show page. Create-without-teacher then assign is covered. See **Fixed on main**.

### 8. Report card generate needs a template + queue worker

**Half fixed, half operator.** Generating with no applicable template now raises
a named validation error ("No active report card template applies to this
class.") rather than blowing up — `GenerateReportCardsAction::resolveTemplate`.
The queue half is unchanged and is **operator config**: `QUEUE_CONNECTION=database`
still leaves cards draft without a `queue:listen`/`queue:work` worker running.

### 9. Staging unify-verify / Deploy 3 / Track B

**Severity:** blocked **operator** tasks, not missing code. Do **not** execute Deploy 3 or start Track B from this list. See `STATUS.md` §3–4.

### 10. `/academics/gradebook` is 404

**Fixed** — `GET academics/gradebook` redirects to `exams.gradebook.index` (query string kept). Inertia static hrefs are asserted against registered GET routes. See **Fixed on main**.

---

## P3 — Confusion (easy to think it worked or failed)

### 11. AppShell nav is unusable as navigation

**Severity:** confusion / blocked. **105** wrapping links as of 2026-09-12 (“50+” when this was written), duplicate “Report cards” / “Awards”. Logout **is** present (POST next to the name). `GET /logout` remains 405. Round 3 ranked #2. **Proposed, awaiting decision** — `docs/APPSHELL_NAV_IA.md` (PR #98). Shell unchanged until the owner confirms.

### 12. Seeder still inserts duplicate Extra year names

**Fixed** — year-creating seeders `firstOrCreate` by `name` (same uniqueness the UI already validates). Re-seed cannot insert a second Extra / Pilot / 2024-2025 row. See **Fixed on main**.

### 13. Shared Add-term form on every year card

**Fixed** — each year card owns its own `useForm` via a `YearCard` component, so
typing on one year no longer fills the others and "Add term" is unambiguous
about which year it hits. See **Fixed on main**.

### 14. Weights UI is a JSON blob of type ids → 0

**Fixed** — numeric inputs per exam type, live sum, seeded from `default_weight`. See **Fixed on main**.

### 15. Teacher grid offers `excused` / `left_early`

**Severity:** confusion. Excuse is supposed to come from an approved note (Round 1 step 2).

### 16. Taught summary vs plan topic

**Severity:** confusion / twice. Picking “Sun and moon letters” still invites typing the same title (R1/R2 step 2).

### 17. Parent notified column shows — on excused rows

**Severity:** confusion. Column exists (#86). Round 3 step 3: visible row was **—** (excused / no SMS receipt). SMS body is not in the portal; local/staging sends are log + `sms_receipts` only.

### 18. Vite HMR blank Inertia pages on this VM

**Severity:** confusion for agents. Round 2/3 used `npm run build` + no `public/hot`. Not an app defect for production `public/build`.

### 19. Exam schedule form defaults wander

**Fixed** — the term and class dropdowns are scoped to the form's own selected
year, and the year defaults to the one being viewed, else the active one. The
lists spanned every year, which is why the defaults could land on another
year's term. Changing the year resets a term or class that no longer belongs to
it. See **Fixed on main**.

---

## P4 — Cosmetic / later

### 20. Create-year description in `useForm` but no field; unlabelled date inputs; copyright 2025 on login

**Fixed** — the description field exists (the controller had validated it since
the screen shipped), every date input on the years screen is labelled, and no
`2025` copyright remains in any view. See **Fixed on main**.

### 21. Report-card publish control defaulted to Term 2 while the table is Term 1

**Fixed** — generate and publish default to the term being viewed, else the
**active** term, rather than whichever term sorts first across all years. Same
defect family as #19. See **Fixed on main**.

### 22. Blade counters (28 students / 3 teachers) are not “Grade 5 A”

**Evidence:** Round 2 step 1. Admin still lands on Blade dashboard (allowed, Round 3).

---

## Explicitly not defects

- **Payroll off** — `PAYROLL_ENABLED=false` and settings `payroll.enabled` — by design (S5.6).
- ~~**Hifz frozen**~~ — the rule 7 freeze **ended with F5** (ADR-029, 2026-09-12): §2b is complete, the Qur'an dataset belongs to `Courses\Components\Quran`, and the four Blade controllers that read it are deleted. What remains of the Blade app (hub, five dashboards, programmes, enrolments, milestones, reports) touches no dataset model and is ordinary code again.
- **Qur’an dual-write off** — `QURAN_HALAQA_DUAL_WRITE` default false; A.4 is dual-write only.
- **BML Pay now untested** — sandbox, not a product lie by itself; Rule 12 still requires webhook confirmation.
- **UNVERIFIED slices** (S2.1 rooms, S2.4–S2.5, S2.9–S2.10, S3.5, S3.7, S4.4–S4.5, S5.*, 1A.2–2.5, Arabic A, Qur’an A) — absence of a walk is not a recorded functional bug.
- **SMS log-bind outside production** — intended (#86). Live HTTP only if `APP_ENV=production` **and** `SMS_LIVE` is explicit true.
- **`cursor/pilot-rewalk-063c`** — not merged; superseded by #79–#84 on `main` plus Round 3 notes **#93**. No open PR. Product overlap of picker / logout / seed contacts / class-teacher / periods / generate-today.

---

## Fixed on main (2026-08-26, PRs #86–#92, plus later)

These were open at the status audit (`c21630a`) and in Round 1/2 ranked lists. Round 3 re-walked them on a local merge; they are now on `main`. Kept here so the audit does not silently drop the record.

| Was | Fix | Evidence |
|---|---|---|
| People → Students search/CSV only; no create | **#95** `SaveStudentAction` + status via `ChangeStudentStatusAction`; guardian pivot; course-only nullables | `StudentDirectoryCrudTest`; browser: add child → roster picker |
| Term % blank; Weights JSON blob of zeros did not persist a scheme | **#96** numeric type percents from `default_weight`; redirect keeps `academic_year_id` | `GradingFoundationsTest` HTTP store; `WeightSchemePersistTest`; browser: Fatima 28.00 / E / rank 15 on HTML report card |
| Report cards HTML not PDF; ADR-012 still named Browsershot | **#97** ADR-012: HTML is the supported production output; PDF is a future binding swap | `ReportCardsTest`; report-cards page cites ADR-012 |
| SMS live Dhiraagu/HTTP in every environment | **#86** `LogSmsSender` unless `LiveSms::allowed()` | `NotificationsServiceProvider`; `SmsSafetyTest`; Round 3 Parent notified column |
| `DatabaseSeeder` not a school (0 students / 0 `teachers` / 0 years) | **#87** `PilotRehearsalSeeder` + `EnsureTeacherRowAction` | `SeededSchoolTest`; Round 3 `migrate:fresh --seed` |
| Teacher/parent Blade landing hid the loop (parent **Admin Dashboard**) | **#88** teacher → Today; parent **Parent Dashboard** | `RoleLandingTest`; Round 3 steps 2–3 |
| Term grades silent blank / UI called HTML a PDF | **#89** missing-weights banner; Download HTML | `TermGradesTest`; Round 3 step 5 |
| Fill grid names only (wrong-child) | **#90** Number + DOB; picker `identity_key` omits class | `ClassRegisterTest`, `ClassRosterPickerTest`; Round 3 step 2 |
| Roster picker two rows when numbers differ (PIL-01 vs blank) | **#99** `identity_key` omits student number; blank is not distinguishing; admin must choose explicitly | `ClassRosterPickerTest`; browser: Grade 5 A search Fatima, amber banner |
| Generate “Created 0”; duplicate year/class 500; invoice drafts-only + hardcoded dates | **#91** flash copy; unique year/class + form `errors.name`; list all statuses; term period action | `YearClassUniquenessTest`, `InvoiceGenerationTest`; Round 3 steps 1 and 6 |
| DoD = tests only | **#92** “walked in a browser” in `CLAUDE.md` / `.cursorrules` | docs |
| `/academics/gradebook` 404 | **#100** redirect to `exams.gradebook.index`; Inertia static GET href scan | `HardcodedInertiaPathsTest`; browser: `/en/academics/gradebook` lands on Gradebook |
| Grade 5 B class teacher stayed None; no update path | **#101** `AssignClassTeacherAction` + PUT on class show | `ClassTeacherAssignmentTest`; browser: Grade 5 B save teacher, reload |
| Seeder duplicate Extra year names | **#101** `firstOrCreate` by year name in seeders | `AcademicYearSeederIdempotencyTest` |
| 1A glossary tables missing | **#102** term bank + lesson attach + player definitions | `GlossaryTest`; browser: fatha on catalog, outline attach, player click |
| Event/elective registration unused | **#103** `EnforceSeatLimitAction` shared with 1B.2; waitlist / parent confirm / second round | `EventRegistrationTest`; browser walk on `/academics/events` and `/portal/events` |
| Class quizzes/assignments unused by engine | **#104** `assessments:verify-legacy-migration`; attach to class XOR course | `LegacyAssessmentMigrationTest`; class show Engine assessments + CSV |
| School exams and engine assessments in separate views | **#105** `GradeItemContract` + tagged providers; gradebook CSV includes item scores | `UnifiedGradebookTest`; browser: Grade 5 A exam + Letters Quiz + Recitation homework |
| Course certificates missing | **#106** templates, issue, public ULID verify (face only, no auth) | `CourseCertificateTest`; browser: AKU-2026-C9CAKP Fatima Yoosuf |
| Completion/performance reports missing | **#107** staff completions + portal performance CSV | `CourseCompletionReportTest`; browser: Unification representative course roster |

Round 1 vs Round 2 “still on the list after Round 2” for SMS, seeder, landings, fill-grid, generate/year/class/invoices is **obsolete** for those items. Round 3 remaining ranked list (`docs/PILOT_REHEARSAL.md`) is the current teacher-blocking set, minus uniqueness-as-500 (fixed) plus the form-visibility/seeder-dupe nuance.
