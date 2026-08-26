# Status

**Verified against:** `main` `c21630a` (2026-08-26).  
**USABLE column** cites only `docs/PILOT_REHEARSAL.md` (Round 1 + Round 2) and staging notes in the [archive](docs/STATUS_ARCHIVE.md). It is **not** inferred from code or tests.  
**History:** per-slice append log → [`docs/STATUS_ARCHIVE.md`](docs/STATUS_ARCHIVE.md).  
**Defects:** [`docs/KNOWN_ISSUES.md`](docs/KNOWN_ISSUES.md).

## 1. Honest summary

Akuru on `main` has a large Laravel 12 + Inertia/React codebase: People unification (Deploy 1–2, Deploy 3 not run), academic years/classes/registers/attendance, exams/marks, HTML report cards, fees/invoices, HR/payroll (payroll **off**), a course engine with offerings and four activity patterns, and Arabic/Qur’an A-track catalog pieces. CI (pint + Pest + architecture) is the merge gate.

What **runs for a person** is narrower. Staging `test.akuru.edu.mv` public pages return 200; **seed logins do not authenticate** (Round 1 step 0, Round 2). This agent cannot SSH or seed staging. The only school loop that has been walked is **local** `akuru_institute` after `PilotRehearsalSeeder`, not `DatabaseSeeder` alone (`0` students, `0` `teachers` rows, `0` years — Round 2). After that operator seed, Chrome could do year/class (with holes), teacher register submit, parent absence-note approve, exam marks, and parent invoices. It could **not** confirm absence SMS (live Dhiraagu/HTTP binding, no portal signal), term %/grade/rank (blank without a saved weight scheme), or a PDF report card (HTML with empty grade cells). Most slices have never been walked: **UNVERIFIED**.

`STATUS.md` previously ended “Remaining blockers: none.” That was false. Open PRs for SMS fake, seeder school, role landings, etc. are **not** on `main` and are not claimed here.

## 2. Phase / slice table

Legend — **CODE:** implementation in repo (models/migrations/actions/routes/pages). **TESTED:** Pest coverage of the slice’s behaviour, not merely that a class constructs. **USABLE:** a person can complete the task in a browser (or staging), with a citation; otherwise UNVERIFIED.

| Slice | CODE | TESTED | USABLE | Notes / known holes |
|---|---|---|---|---|
| Phase 0 foundation | Yes. Domain skeleton, contracts, CI. | Architecture suite + route-name tests. | Staging public `/up` `/en` 200 (2026-06-13). Auth/BML/portal/Hifz **not** credential-smoked then; 2026-08-23 seed login **failed**. | Staging HEAD in archive is far behind `c21630a`. |
| Morph-map hotfix | Yes. `config/morph-map.php`, backfill, `morph-map:verify`. | `MorphMapBackfillTest`. | Staging verify **OK** (2026-08-16, `05b8cca`). Later seed login still failed. | Mixed-era staging was the real test of collapse. |
| S1.1a schema | Yes. Additive student/guardian/document columns. | `UnifiedStudentSchemaTest`. | UNVERIFIED as a user task (schema). | Deploy 3 cleanup not run. |
| S1.1b backfill | Yes. `UnifyStudentsAction`, `students:verify-unification`. | `UnifiedStudentBackfillTest`, representative seeder test. | Staging verify **red** (collisions + orphan guardians, archive 2026-08-25). Representative gate **green** (ADR-021). | `--backfill` refused on `APP_ENV=production`. |
| S1.1c read switch | Yes. Dual-write still on. | `UnifiedStudentReadSwitchTest`. | UNVERIFIED in a browser. Staging enrollments with null `student()` noted in archive. | Posted enrollment id still legacy RS. |
| S1.2 custom fields | Yes. Admin CRUD + student profile fields. | `CustomFieldsTest` (HTTP). | UNVERIFIED. | People → Students is **search only** — no create (`People/Students/Index.jsx`). Round 2. |
| S1.3 consent | Yes. Ledger + profile tab. | `ConsentTest`. | UNVERIFIED. | |
| S1.4 staff profiles | Yes. Inertia `people.staff.*`. | `StaffProfileTest`. | UNVERIFIED. | `teachers` row ≠ Spatie role `teacher`. |
| S1.5 years/terms/classes | Yes. Years/classes/roster/promotion. | `AcademicYearBackboneTest` (activate/close/promote + screens load). | Walked **partial** (R1 S1, R2 S1). Year names **not unique**. Class duplicate → **Ignition 500**. Class teacher field exists; new Grade 5 B stayed **None** this walk. Roster picker works; twins not flagged across classes. | `ActivateAcademicYearAction` will not close the current year for you. |
| S2.0 unify-verify gate | Yes. `scripts/pull-deploy-test.sh`. | `PullDeployTestScriptTest`. | Staging evidence **not pasted**. First #15 deploy used pre-pull script (archive). | Operator-only to confirm a gated deploy log. |
| S2.1 rooms | Yes. CRUD + CSV. | `RoomCrudTest`. | UNVERIFIED. | |
| S2.2 timetable conflicts | Yes. Additive year/room/validity + checker. | `TimetableConflictSaveTest`. | UNVERIFIED as a lone task. | |
| S2.3 timetable builder | Yes. Week grid. | `TimetableBuilderTest`. | Walked **partial** (R2 S1): seeder grid shown; extra-period drag **did not persist**. | |
| S2.4 room bookings | Yes. | `RoomBookingTest`. | UNVERIFIED. | |
| S2.5 calendar days | Yes. | `CalendarDayTest`. | UNVERIFIED. | |
| S2.6 class register | Yes. Today, generate, fill, submit. | `ClassRegisterTest`. | Walked **partial** (R2 S2): fill+submit on an **already generated** Arabic P1. Cold Today empty without seed/timetable/generate. Admin generate showed **Created 0**. Fill grid **name only**. | Teacher generate-today exists (`#84`). Still easy to read generate-0 as failure. |
| S2.7 class attendance | Yes. Writer + daily grid. | `ClassAttendanceTest`. | Walked **partial** with S2.6 (R2 S2). School in **per-lesson** mode; daily store rejects. | `excused` still on the teacher grid. |
| S2.8 absence notes | Yes. Portal submit + teacher approve → excused. | `AbsenceNoteTest`. | Walked **ok** (R2 S4). Date not defaulted. Attachment/period not in the form. | |
| S2.9 behavior | Yes. | `BehaviorRecordTest`. | UNVERIFIED. | |
| S2.10 requests / leave | Yes. | `SchoolRequestTest`. | UNVERIFIED. | |
| S3.1 grading foundations | Yes. Scales, types, weights UI. | `GradingFoundationsTest`. | Weights UI walked **fail** for producing a year scheme (R2 S5: `assessment_weight_schemes` count **0**, JSON blob of zeros). | Tests create schemes via Action, not the walked UI. |
| S3.2 exams | Yes. Status machine, schedule. | `ExamSchedulingTest`. | Walked **ok** (R2 S5) schedule → published. Easy to schedule the wrong class (form defaults). | |
| S3.3 marks | Yes. Grid + CSV. | `ExamMarksTest`. | Walked **ok** (R2 S5) 15/15. PIL numbers **on this grid**. | |
| S3.4 term grades | Yes. `ComputeTermGradesAction`, gradebook. | `TermGradesTest` **happy path** (saves weights first). No test for missing weights → blank columns. | Walked **fail** (R1 S5, R2 S5): Term % / Grade / Rank all **—**. | Calling this “done” overstated usability. |
| S3.5 standards | Yes. | `StandardsTest`. | UNVERIFIED. | |
| S3.6 report cards | Yes. Templates, queued HTML via `HtmlDocumentRenderer`. | `ReportCardsTest` binds HTML renderer; seeds a filled `TermGrade`. | Walked **fail** as PDF / as a filled card (R1 S5, R2 S5). Download filename `report-card-{id}.html`. Fatima %/grade empty. Queue worker required. | Not PDF. |
| S3.7 awards / docs | Yes. HTML certificates/ID cards. | `AwardsDocumentsTest`. | UNVERIFIED. | Also HTML, not PDF (`AwardController`). |
| S4.1 finance schema | Yes. Year/term on invoices, receipts. | `FinanceSchemaTest`. | UNVERIFIED as a user task. | |
| S4.2 fee structures | Yes. | `FeeStructureTest`. | UNVERIFIED (structure was **seeded** for the walk). | Default `DatabaseSeeder` has no fee items (R1 S6). |
| S4.3 invoice generation | Yes. Generate/issue/arrears. | `InvoiceGenerationTest`. | Walked **partial** (R2 S6): generate+issue worked; admin list is **drafts only** (`ListDraftInvoicesAction`); Extra year tab looks empty while Pilot has 45 sent. Dates hardcoded `2026-01-01`–`2026-03-31`. Issue sends SMS through the **live** binding. | |
| S4.4 payment plans | Yes. | `PaymentPlanTest`. | UNVERIFIED. | |
| S4.5 adjustments | Yes. | `FeeAdjustmentTest`. | UNVERIFIED. | |
| S4.6 payment + portal | Yes. Webhook + parent Fees. | `PaymentPortalTest`. | Walked **partial** (R2 S6): parent saw 3 invoices + Pay now. BML **not** exercised. | |
| S5.1 staff attendance | Yes. | `StaffAttendanceTest`. | UNVERIFIED. | |
| S5.2 leave | Yes. | `LeaveManagementTest`. | UNVERIFIED. | |
| S5.3 contracts | Yes. | `ContractsComplianceTest`. | UNVERIFIED. | |
| S5.4 recruitment | Yes. Public `/careers`. | `RecruitmentTest`. | UNVERIFIED. | |
| S5.5 performance/CPD | Yes. | `PerformanceTest`. | UNVERIFIED. | |
| S5.6 payroll | Yes. **Flagged off** (`PAYROLL_ENABLED` + `payroll.enabled`). | `PayrollTest` (turns the flag on). | UNVERIFIED; default **off** is by design. | |
| 1A.1 auth/roles | Yes (Phase 0 + S1). | Auth tests. | Walked login **ok locally** after verified `user_contacts` (R2). Staging login **fail**. | Blade dashboard after login, not AppShell. |
| 1A.2–1A.7 course engine | Yes. Catalog, outline, text/media blocks, `/learn`, portal learning. | Matching `tests/Feature/Courses/*`, `LessonProgressTest`, `GuardianAccessPolicyTest`. | UNVERIFIED. | |
| 1B.1–1B.6 offerings/PWA | Yes. Offerings, pin/seats, sessions, extra blocks, unlock/completion, PWA/i18n. | Matching Offerings/Progress/Pwa tests. | UNVERIFIED. | 1B.5 tests the 2/3 = 66 formula. |
| 2.1–2.5 activities | Yes. Four patterns, bank, assessment player, review, session polish. | Matching Courses/Progress tests. | UNVERIFIED. | |
| Arabic A.1–A.3 | Yes. Letters/harakas, skill tag, reports. | `ArabicReferenceTest`, `ArabicSkillActivityTest`, `ArabicSkillReportTest`. | UNVERIFIED. | No AI (rule 8). |
| Qur’an A.1–A.4 | Yes. Read actions, recitation metadata, mapping, dual-write **off**. | Matching Courses/Offerings tests. | UNVERIFIED. | No Hifz dashboard change. `QURAN_HALAQA_DUAL_WRITE` default false. |
| Hifz (frozen) | Legacy Blade exists. | `HifzAuthorizationTest` etc. | UNVERIFIED this week. Out of scope to change. | Rule 7. |
| Pilot blockers #79–#84 | On `main`: picker, AppShell logout, seed contacts, class-teacher field, periods CRUD, teacher generate-today. | Matching Pest files. | Walked in R2: logout **works**; picker **works** with twin hole; periods **work**; class teacher **field** present but B stayed None; generate-today not the path that filled Today (seeder already had logs). | Does not include SMS fake (still live on `main`). |

## 3. Current blockers

### Agent-doable (code on a slice PR; not started here)

1. **SMS live-bound in every environment** — `NotificationsServiceProvider.php` binds `SmsSenderInterface` → `SmsGatewayService`. `StudentMarkedAbsent` → `SendAbsenceSms`. Seed phones `7820288` / `7972434` are real. Highest severity. Unfixed on `main`.
2. **`DatabaseSeeder` is not a school** — no students, no `teachers` rows, no academic years. Role `teacher` ≠ `teachers` row.
3. **Blade landing hides the loop** — admin/teacher/parent hit Blade `/en/dashboard` (parent often **Admin Dashboard** via `parentDashboard()` catch: `Collection::whereHas` at `DashboardController.php` ~255). Years/Today/Exams/Invoices live only on overflowing AppShell.
4. **Term grades blank / report cards HTML** — no saved weight scheme in the walk; download is `.html`; UI/STATUS called the document path “done”.
5. **Wrong-child risk** — register fill grid has names only; picker twins share name+NID+DOB across classes without an indistinguishable banner.
6. **Generate 0 / duplicate year / class 500 / invoice drafts-only list** — validation and copy holes.
7. **People Students has no create** — cannot type a child into existence on that screen.
8. **AppShell nav IA** — 50+ wrapping links, duplicate labels. Logout exists; finding Today still fails if you follow the first screen.

### Operator-only

1. **Staging login / seed** — no SSH from this environment; webhook deploy only (`docs/STAGING.md`). Seed passwords 302 back to login. Someone with server access must seed (or set real passwords) and paste `students:verify-unification` + `morph-map:verify` for **current** `main`.
2. **GitHub branch protection** — docs exist (`docs/BRANCH_PROTECTION.md`); apply was 403 (archive A4).
3. **Deploy 3 cleanup** — proposal only (`docs/migrations/s11-deploy-3-cleanup-proposal.md`). Dual-write still on.
4. **Credential smoke / BML sandbox** — never completed on staging.
5. **`QURAN_HALAQA_DUAL_WRITE`** — leave off until an operator confirms dual-write; no read switch.
6. **Payroll** — leave `PAYROLL_ENABLED` / `payroll.enabled` off.

## 4. Decisions awaiting the owner

| Decision | Why it is blocked on a person, not an agent |
|---|---|
| **Pilot timing** | Staging cannot start the rehearsal. Local walk is not `test.akuru.edu.mv`. |
| **Track B vs finishing gaps** | ADR-021 representative gate is green (archive). Track B is unblocked **and not started**. The school loop on `main` still has SMS-live, hollow term grades, and a seeder that is not a school. Starting Track B now repeats the “marked done / cannot use” pattern. |
| **Deploy 3** | Confirm or reject the cleanup proposal. Do not run it as a drive-by. |
| **Branch protection** | Apply on GitHub or accept that every PR must wait for CI and not self-merge (S2 kickoff terms). |
| **SMS_LIVE / production flag** | When (if) production should send Dhiraagu. Until then local/staging must not. |
| **HTML vs PDF documents** | `HtmlDocumentRenderer` is the bound implementation (ADR-012). Calling downloads “PDF” is the lie; replacing the renderer is a later slice. |

## 5. Overstated “done” (input to a later DoD amendment)

Do **not** edit `CLAUDE.md` in this slice. Accurate wording that should have been used:

| Claim that was too strong | Wording that matched the evidence |
|---|---|
| STATUS “Remaining blockers: none” | Remaining: staging login, SMS live binding, seeder ≠ school, Blade landing, term grades/HTML cards, identity on fill grid, AppShell nav. |
| S3.4 “Term grades (done)” | Code computes term grades **when a weight scheme exists**. Walked gradebook columns blank (`assessment_weight_schemes` = 0). Tests never cover the missing-scheme case. |
| S3.6 “Report cards (done)” | Queued **HTML** via `HtmlDocumentRenderer`; filename `.html`. Walked cards had empty %/grade. Not PDF. |
| S3.1 weights implied ready | Scales/types seed; the Weights screen as walked did not persist a usable scheme. |
| S4.3/S4.6 invoices “done” as an admin loop | Generate/issue and parent Fees worked on the **pilot year**. Admin index lists **drafts only**; other year tabs look empty; dates hardcoded Jan–Mar 2026. |
| S2.6 register loop “done” as teacher-usable | Submit works if `lesson_logs` already exist and the login has a `teachers` row. Cold `DatabaseSeeder` Today is empty. Fill grid omits number/DOB. |
| S1.5 years/classes “done” as admin-usable | Screens exist. Duplicate year names allowed; duplicate class is a 500; class teacher on a newly created class did not stick in R2. |
| S1.2 student directory as CRUD | Index is search/CSV. **No create.** |
| Phase 0 “staging deploy done” as staff-ready | Public smoke 200. Seed auth on staging has **not** worked in recorded walks (2026-08-23, R1 step 0). |
| S1.1c / S2 student-keyed writes “ready for staging” | Staging `students:verify-unification` last recorded **red**. Representative local gate green ≠ staging green. |
| 1A / 1B / 2 / Arabic A / Qur’an A “done” | Code + Pest exist. **USABLE UNVERIFIED** — no browser walk of `/learn`, catalog, offerings, or skill reports. |
| S5.1–S5.5 “done” | Code + Pest. **UNVERIFIED** in a browser. S5.6 is honestly “done; flagged off”. |
| S2.3 builder “done” | Page exists. R2 extra-period drag did not persist. |

## 6. Out of scope (unchanged)

Hifz behaviour frozen. Deploy 3 not executed. Track B not started. No application code in this slice.
