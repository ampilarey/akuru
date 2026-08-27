# Status

**Verified against:** `main` after PRs **#86–#115**.  
**USABLE column** cites `docs/PILOT_REHEARSAL.md` (Rounds 1–3) and staging notes in the [archive](docs/STATUS_ARCHIVE.md). It is **not** inferred from code or tests.  
**History:** per-slice append log (including Round-2 fixes 1–7) → [`docs/STATUS_ARCHIVE.md`](docs/STATUS_ARCHIVE.md).  
**Defects:** [`docs/KNOWN_ISSUES.md`](docs/KNOWN_ISSUES.md).

## 1. Honest summary

Akuru on `main` has a large Laravel 12 + Inertia/React codebase: People unification (Deploy 1–2, Deploy 3 not run), academic years/classes/registers/attendance, exams/marks, HTML report cards, fees/invoices, HR/payroll (payroll **off**), a course engine with offerings and four activity patterns, and Arabic/Qur’an A-track catalog pieces. CI (pint + Pest + architecture) is the merge gate.

What **runs for a person** is still narrower than the code. Staging `test.akuru.edu.mv` public pages return 200; **seed logins do not authenticate** (Round 1 step 0, Round 2, Round 3). This agent cannot SSH or seed staging.

Local `migrate:fresh --seed` now includes `PilotRehearsalSeeder` (#87). Round 3 Chrome (stacked #86–#92, now on `main`) walked: teacher **Today** landing, fill grid **number + DOB**, **Parent Dashboard** + **Parent notified** column, absence-note approve, missing-weights **banner** + honest **HTML** (not PDF) labels, invoice **sent** rows on the Pilot year. SMS binds `LogSmsSender` unless `APP_ENV=production` **and** `SMS_LIVE` is an explicit true (#86).

Still blocking a real teacher: staging access, AppShell nav IA (**proposed**, `docs/APPSHELL_NAV_IA.md`, awaiting owner decision — wrap still live), parent notified still **—** on excused rows. People → Students can create a child (#95). Weights can persist a year scheme (#96). Documents are HTML by decision (ADR-012 / #97). Roster picker flags PIL-01 vs blank as one identity (#99). `/academics/gradebook` redirects to `/exams/gradebook` (#100). Class teacher can be set on an existing class; year seeders `firstOrCreate` by name. Course certificates (C1 #106), completion/performance reports (C2 #107), teacher review reports (C3 #108), composed portal home (D1 #109), parent-teacher meeting slots (D2 #110), staff overview (D3 #111), W1.1 conversion urgency (#112), W1.2 homepage trust (#113), W1.3 outcome-led course pages (#114), and W1.4 mobile CTA + leads (#115) are on `main`. W1.5 SEO + sharing is in this PR. Most catalog/HR/course-engine slices remain **UNVERIFIED**.

Hifz untouched until Phase F. Deploy 3 not executed. Track B leftovers B1–B4 are on `main` (#102–#105).

## 2. Phase / slice table

Legend — **CODE:** implementation in repo (models/migrations/actions/routes/pages). **TESTED:** Pest coverage of the slice’s behaviour, not merely that a class constructs. **USABLE:** a person can complete the task in a browser (or staging), with a citation; otherwise UNVERIFIED.

| Slice | CODE | TESTED | USABLE | Notes / known holes |
|---|---|---|---|---|
| Phase 0 foundation | Yes. Domain skeleton, contracts, CI. | Architecture suite + route-name tests. | Staging public `/up` `/en` 200 (2026-06-13). Auth/BML/portal/Hifz **not** credential-smoked then; 2026-08-23 seed login **failed**. | Staging HEAD in archive is far behind current `main`. |
| Morph-map hotfix | Yes. `config/morph-map.php`, backfill, `morph-map:verify`. | `MorphMapBackfillTest`. | Staging verify **OK** (2026-08-16, `05b8cca`). Later seed login still failed. | Mixed-era staging was the real test of collapse. |
| S1.1a schema | Yes. Additive student/guardian/document columns. | `UnifiedStudentSchemaTest`. | UNVERIFIED as a user task (schema). | Deploy 3 cleanup not run. |
| S1.1b backfill | Yes. `UnifyStudentsAction`, `students:verify-unification`. | `UnifiedStudentBackfillTest`, representative seeder test. | Staging verify **red** (collisions + orphan guardians, archive 2026-08-25). Representative gate **green** (ADR-021). | `--backfill` refused on `APP_ENV=production`. |
| S1.1c read switch | Yes. Dual-write still on. | `UnifiedStudentReadSwitchTest`. | UNVERIFIED in a browser. Staging enrollments with null `student()` noted in archive. | Posted enrollment id still legacy RS. |
| S1.2 custom fields | Yes. Admin CRUD + student profile fields. Directory create/edit added. | `CustomFieldsTest`, `StudentDirectoryCrudTest`. | Walked **create** (#95): Add student → show → class picker. | Course-only nullables supported. Status only via `ChangeStudentStatusAction`. |
| S1.3 consent | Yes. Ledger + profile tab. | `ConsentTest`. | UNVERIFIED. | |
| S1.4 staff profiles | Yes. Inertia `people.staff.*`. | `StaffProfileTest`. | UNVERIFIED. | `teachers` row ≠ Spatie role `teacher` (mitigated for seed: `EnsureTeacherRowAction` in `UserSeeder`, #87). |
| S1.5 years/terms/classes | Yes. Years/classes/roster/promotion. | `AcademicYearBackboneTest`, `YearClassUniquenessTest`. | Walked **partial** (R1 S1, R2 S1, R3 S1). Create unique year/class **validated** (#91); first R3 pass hid errors, follow-up paints `errors.name`. Year seeders `firstOrCreate` by name. Class teacher can be assigned on an existing class (show page). Picker identity_key **omits class** (#90) **and student number** (blank / PIL-01 vs PIL-99 still flag). | `ActivateAcademicYearAction` will not close the current year for you. |
| S2.0 unify-verify gate | Yes. `scripts/pull-deploy-test.sh`. | `PullDeployTestScriptTest`. | Staging evidence **not pasted**. First #15 deploy used pre-pull script (archive). | Operator-only to confirm a gated deploy log. |
| S2.1 rooms | Yes. CRUD + CSV. | `RoomCrudTest`. | UNVERIFIED. | |
| S2.2 timetable conflicts | Yes. Additive year/room/validity + checker. | `TimetableConflictSaveTest`. | UNVERIFIED as a lone task. | |
| S2.3 timetable builder | Yes. Week grid. | `TimetableBuilderTest`. | Walked **partial** (R2 S1): seeder grid shown; extra-period drag **did not persist**. | |
| S2.4 room bookings | Yes. | `RoomBookingTest`. | UNVERIFIED. | |
| S2.5 calendar days | Yes. | `CalendarDayTest`. | UNVERIFIED. | |
| S2 event/elective registration | Yes. Min/max seats, waitlist, parent confirm, second round. Reuses 1B.2 `EnforceSeatLimitAction` (no second limiter). | `EventRegistrationTest` (lock reject, waitlist, parent confirm, second-round promote, portal 403). | Walked **#103**: admin create 1-seat elective → parent register/confirm → second child waitlisted → second round promotes. | Portal `/portal/events`; admin `/academics/events`. Occupying statuses: pending, confirmed, pending_parent. |
| 2 leftover — class quiz/assignment → engine | Yes. `assessments.classroom_id` XOR `course_id`; additive `legacy_*` ids; `assessments:verify-legacy-migration`. | `LegacyAssessmentMigrationTest` (XOR attach, remaining counts, roster 403, class CSV). | Walked **#104**: Grade 5 A class show lists migrated quiz + assignment; roster student opens player. | Legacy tables kept (rule 9 / ROADMAP §3.5). Engine stays subject-ignorant. |
| S2.6 class register | Yes. Today, generate, fill, submit. | `ClassRegisterTest`. | Walked **ok** for fill+submit (R2 S2, R3 S2). Teacher login **lands on Today** (#88). Fill grid **Number + DOB** (#90). Generate flash names already-existing registers (#91). Cold `DatabaseSeeder` now includes `PilotRehearsalSeeder` (#87). | Unfilled still hides today’s remaining periods until they are late. |
| S2.7 class attendance | Yes. Writer + daily grid. | `ClassAttendanceTest`. | Walked **partial** with S2.6 (R2 S2). School in **per-lesson** mode; daily store rejects. | `excused` still on the teacher grid. |
| S2.8 absence notes | Yes. Portal submit + teacher approve → excused. | `AbsenceNoteTest`. | Walked **ok** (R2 S4, R3 S4). Date not defaulted. Attachment/period not in the form. | |
| S2.9 behavior | Yes. | `BehaviorRecordTest`. | UNVERIFIED. | |
| S2.10 requests / leave | Yes. | `SchoolRequestTest`. | UNVERIFIED. | |
| S3.1 grading foundations | Yes. Scales, types, weights UI. | `GradingFoundationsTest` including HTTP store. | Weights form now saves a year scheme (#96): numeric defaults summing to 100. | Previously walked **fail** (R2/R3 JSON zeros). |
| S3.2 exams | Yes. Status machine, schedule. | `ExamSchedulingTest`. | Walked **ok** (R2 S5) schedule → published. Easy to schedule the wrong class (form defaults). | |
| S3.3 marks | Yes. Grid + CSV. | `ExamMarksTest`. | Walked **ok** (R2 S5) 15/15. PIL numbers **on this grid**. | |
| S3.4 term grades | Yes. `ComputeTermGradesAction`, gradebook. | `TermGradesTest` happy path **and** missing-weights (#89); `WeightSchemePersistTest`. | Walked **explained fail** until weights persist (#96): scheme from Weights then Recompute fills Term % / Grade / Rank. `/academics/gradebook` redirects to `/exams/gradebook` (#100). | |
| 2 leftover — unified gradebook | Yes. `GradeItemContract` + exam/assessment providers; `grade_items` on `/exams/gradebook`. | `UnifiedGradebookTest`; `GradeItemContractTest`. | Walked **#105**: Grade 5 A gradebook shows exam marks and engine quiz/assignment scores. | Engine stays subject-ignorant. Term % still exams-only. |
| 3 C1 course certificates | Yes. `certificate_templates` + `issued_certificates`; admin builder; issue; public QR verify. | `CourseCertificateTest`. | Walked **#106**: template → issued AKU-2026-C9CAKP to Fatima Yoosuf → guest `/verify/certificates/{ulid}` face only; CSV. | Unlocalized verify URL. Morph aliases. HTML, not PDF. |
| 3 C2 completion + performance reports | Yes. Staff `/catalog/reports/completions`; portal `/portal/performance`. | `CourseCompletionReportTest`. | Walked **#107**: admin roster 12 rows (Unification representative course); parent Hassan sees Fatima Yoosuf performance card; CSV. | Course-only enrollments included; offering summaries empty when `course_offering_id` is null. |
| 3 C3 teacher review reports | Yes. `/catalog/reviews` pending + weakness + revision; CSV. | `TeacherReviewReportTest`; existing `TeacherReviewTest`. | Walked **#108**: pending Mariyam Ali “Write a sentence” scored 8/10; weakness/revision for “Choose meaning” 0/10 + retry. Live walk found `passing_score` 50 on a 2-point quiz (legacy percent) falsely marking 2/2 as weak — treated as percent when passing > max. | No `course_type` branch. Threshold default 50% when no passing score. |
| D1 composed parent/student home | Yes. `/portal/home` + CSV; parent/student `/dashboard` redirect. Reads Academics/ExamsGrades/Finance/Courses Actions and `StudentHifzSummaryReader`. | `PortalHomeTest`; `RoleLandingTest`. | Walked **#109**: parent Hassan `/en/dashboard` → `/en/portal/home` for Fatima Yoosuf (attendance 0% / 2 absent / 1 excused, Term 1 Arabic Final 70/100, three sent invoices, empty course + Hifz on seed). Student Mariyam Ali same page with course row. CSV `portal-home.csv`. | Portal new files import no other-domain Models and no `App\Domains\Hifz\`. AppShell Home link. Seed has no Hifz rows; Pest composes Hifz via a program created in the test. |
| D2 parent-teacher meeting slots | Yes. `meeting_slots` + `meeting_bookings` (year-scoped); admin generate/publish `/academics/meetings`; portal `/portal/meetings` book/cancel + CSV. | `MeetingSlotTest`. | Walked **#110**: admin published Term 1 PTM 2026-09-03 18:00/18:30 for Grade 5 B (Fatima’s roster class) / Fatimat Ali. Parent Hassan booked 18:00 for Fatima Yoosuf; portal CSV; admin CSV shows 1/1 Fatima. | Morph aliases `meeting_slot` / `meeting_booking`. Permission `meetings.manage`. Portal files import no Models / no Hifz. AppShell Meetings (wrap 82). |
| D3 staff overview | Yes. `/portal/overview` + CSV. Reads Academics `ListUnfilledRegistersAction` (unfilled, fill rates, plan adherence) and ExamsGrades `ListExamsAction::ungraded`. Admin/headmaster `/dashboard` redirect. | `StaffOverviewTest`; `RoleLandingTest`. | Walked **#111**: admin `/en/dashboard` → `/en/portal/overview` (2026-2027 Pilot). Unfilled Grade 5 A Quran Recitation Period 2 expected 2026-08-26; ungraded **D3 Ungraded Walk** marks_entry; fill 66.7% (Ustadha Aishath Shifa 0/1); plan Grade 5 Arabic Term 1 1/1 100%. CSV `staff-overview.csv`. Registers + Exams Open links. Parent `/portal/home`; parent `/portal/overview` **403**. | No new tables. Portal new files import Actions only, no Models / no Hifz. AppShell Overview (wrap 83). Super_admin landing stays Blade. Walk exam `D3 Ungraded Walk` was created locally (seed had none). |
| W1.1 conversion urgency | Yes. Public course cards/detail: seats thresholds, deadline badge, early-bird from `courses.meta`, waitlist → `contact_inquiries`. Expired open courses hidden from Open Courses. | `CourseConversionTest`; `PublicRouteNamesTest`. | Walked **#112**: homepage Open Courses shows Arabic Beginners **Limited seats** + 5 days left + struck 300 / early-bird 180; Advanced Arabic Grammar **7 seats left**; Cursor Test Course (null seats) has **no** seats badge; **W1 Expired Open Course** absent from listing, 200 on direct URL. Tajweed for Beginners full waitlist form; submitted Walk Waitlist → inquiry id 1 `waiting_list` course_id 7. | Occupying = pending+active. Enroll CTA still prints `registration_fee_amount` (300) beside early-bird display of `fee`. Homepage Open Courses is `take(6)` so a later full course may only appear on `/courses`. |
| W1.2 trust above the fold | Yes. Settings group `trust_settings`; homepage hero accreditation / years / students / partner logos. Years from founded year or override; students manual or unified `students` count. Logos via Media public files. | `HomepageTrustTest`; `PublicMediaTest`. | Walked **#113**: `/en` hero shows **Registered with the Ministry of Education… Reg. MOE/EDU/2019/042**, **6** Years operating (founded 2020), **31** Students taught (computed from unified students), MOE partner logo. `/dv` shows the Dhivehi accreditation line + same 6 / 31. Homepage HTML has **no** `5+`, **no** “Years of service”, **no** “Students enrolled”. | Empty settings omit the signal. No admin form yet. About page still hardcodes Est. 2020 and `years=5`. Walk accreditation + logo are **dev-DB only**, not in seeders. |
| W1.3 outcome-led course pages | Yes. `courses.learning_outcomes` JSON; admin one-line-per-locale fields; `testimonials.course_id`; public show outcomes above description, course testimonials with general fallback, prominent instructor qualifications. | `CourseOutcomesTest`. | Walked **#114**: Arabic Language for Beginners shows **What you'll be able to do** (alphabet / makhraj / haraka) above description, **Qualifications** Ijazah in Quran for Ustadha Aishath Shifa, course testimonial “letters finally click”. Cursor Test Course has **no** outcomes section and shows the **general** quote. Homepage does **not** show the course-specific quote. | Catalog Inertia create does not edit outcomes. No testimonial CMS (walk attach via tinker). Walk outcomes/instructor/quotes are **dev-DB only**. |
| W1.4 mobile CTA + leads | Yes. Sticky price + Register + WhatsApp; `leads` table; syllabus magnet; admin listing + CSV. | `CourseLeadCaptureTest`. | Walked **#115**: Arabic Beginners mobile bar shows **180.00 MVR** + WhatsApp icon + **Register · Limited seats** above the site tab bar. Sidebar **Ask on WhatsApp** (`wa.me/9607972434?text=Arabic Language for Beginners`) and **Get full syllabus**. Submitted Walk Parent / 7778888; admin `/admin/public-site/leads` lists Hassan W14 + Walk Parent (`syllabus`); CSV `leads.csv`. | Per-course WhatsApp, else `conversion.whatsapp_number`, else contact `viber`. Syllabus only when public `media_files` id is set. Waiting list dual-writes leads. Walk WhatsApp/syllabus file are **dev-DB only**. |
| W1.5 SEO + sharing | Yes. schema.org `Course` + `CourseInstance` + sitewide `Organization` + `FAQPage`; OG/Twitter title/cover/price; `hreflang` en/dv/ar/x-default; XML sitemap with xhtml alternates for courses/articles/news/events. | `CourseSeoTest`. | Walk pending this PR (view-source on a real course + `/sitemap.xml`). | No new tables. Website sitemap uses Courses `ListPublicCourseSitemapEntriesAction` (baseline shrunk). Price tags omitted when `fee` is null. News sitemap loc is slug (matches the route), articles included. Lighthouse ≥ 85 not measured in this VM. |
| S3.5 standards | Yes. | `StandardsTest`. | UNVERIFIED. | |
| S3.6 report cards | Yes. Templates, queued HTML via `HtmlDocumentRenderer`. | `ReportCardsTest` Content-Type HTML; ADR-012 HTML decision. | Walked **honest HTML** (R3 S5) plus ADR-012 citation (#97). Queue worker required. | HTML is the supported output (ADR-012 amended). |
| S3.7 awards / docs | Yes. HTML certificates/ID cards. | `AwardsDocumentsTest`. | UNVERIFIED. | Also HTML, not PDF (`AwardController`). |
| S4.1 finance schema | Yes. Year/term on invoices, receipts. | `FinanceSchemaTest`. | UNVERIFIED as a user task. | |
| S4.2 fee structures | Yes. | `FeeStructureTest`. | UNVERIFIED (structure was **seeded** for the walk). | Default seed now includes pilot fees via `PilotRehearsalSeeder` (#87). |
| S4.3 invoice generation | Yes. Generate/issue/arrears. | `InvoiceGenerationTest`. | Walked **ok** on Pilot year (R3 S6): admin lists **all statuses** (`draftsOnly=false`, #91); sent rows visible. Period defaults from year’s term (`ResolveDefaultTermPeriodAction`). Issue SMS is **log** outside production (#86). | Extra year tab can still look empty if that year has no invoices. |
| S4.4 payment plans | Yes. | `PaymentPlanTest`. | UNVERIFIED. | |
| S4.5 adjustments | Yes. | `FeeAdjustmentTest`. | UNVERIFIED. | |
| S4.6 payment + portal | Yes. Webhook + parent Fees. | `PaymentPortalTest`. | Walked **partial** (R2 S6): parent saw 3 invoices + Pay now. BML **not** exercised. | |
| S5.1 staff attendance | Yes. | `StaffAttendanceTest`. | UNVERIFIED. | |
| S5.2 leave | Yes. | `LeaveManagementTest`. | UNVERIFIED. | |
| S5.3 contracts | Yes. | `ContractsComplianceTest`. | UNVERIFIED. | |
| S5.4 recruitment | Yes. Public `/careers`. | `RecruitmentTest`. | UNVERIFIED. | |
| S5.5 performance/CPD | Yes. | `PerformanceTest`. | UNVERIFIED. | |
| S5.6 payroll | Yes. **Flagged off** (`PAYROLL_ENABLED` + `payroll.enabled`). | `PayrollTest` (turns the flag on). | UNVERIFIED; default **off** is by design. | |
| 1A.1 auth/roles | Yes (Phase 0 + S1). | Auth tests, `RoleLandingTest`. | Walked login **ok locally** (R2/R3). Teacher `/dashboard` → Today (#88). Parent/student `/dashboard` → composed `/portal/home` (D1). Admin/headmaster `/dashboard` → `/portal/overview` (D3 #111). Staging login **fail**. | |
| 1A.2–1A.7 course engine | Yes. Catalog, outline, text/media blocks, glossary term bank + lesson attach, `/learn`, portal learning. | Matching `tests/Feature/Courses/*` including `GlossaryTest`. | Glossary walked (#102). Rest of 1A still UNVERIFIED. | `glossary_items` / `lesson_glossary_items` (SPEC §22). |
| 1B.1–1B.6 offerings/PWA | Yes. Offerings, pin/seats, sessions, extra blocks, unlock/completion, PWA/i18n. | Matching Offerings/Progress/Pwa tests. | UNVERIFIED. | 1B.5 tests the 2/3 = 66 formula. |
| 2.1–2.5 activities | Yes. Four patterns, bank, assessment player, review, session polish. Class quizzes/assignments migrate onto the same engine. Unified gradebook via `GradeItemContract`. | Matching Courses/Progress tests + `LegacyAssessmentMigrationTest` + `UnifiedGradebookTest`. | Quiz/assignment migration walked **#104**. Unified gradebook walked this PR. Rest of 2.x still UNVERIFIED. | |
| Arabic A.1–A.3 | Yes. Letters/harakas, skill tag, reports. | `ArabicReferenceTest`, `ArabicSkillActivityTest`, `ArabicSkillReportTest`. | UNVERIFIED. | No AI (rule 8). |
| Qur’an A.1–A.4 | Yes. Read actions, recitation metadata, mapping, dual-write **off**. | Matching Courses/Offerings tests. | UNVERIFIED. | No Hifz dashboard change. `QURAN_HALAQA_DUAL_WRITE` default false. |
| Hifz (frozen) | Legacy Blade exists. | `HifzAuthorizationTest` etc. | UNVERIFIED this week. Out of scope to change. | Rule 7. |
| Pilot blockers #79–#84 | On `main`: picker, AppShell logout, seed contacts, class-teacher field, periods CRUD, teacher generate-today. | Matching Pest files. | Walked in R2/R3. | |
| Round-2 fixes #86–#92 | On `main`: SMS log-bind (#86), seeder school (#87), role landings (#88), term-grades banner + HTML label (#89), fill-grid identity (#90), generate/uniqueness/invoices (#91), DoD browser walk (#92). | Matching Pest files (SMS, seed, landings, term grades, register, uniqueness, invoices). | Walked in **Round 3**. | Records: archive Round-2 fix 1–7. |
| Round 3 notes #93 | Docs only. `docs/PILOT_REHEARSAL.md` Rounds 1–3. | n/a | The walk itself. | `cursor/pilot-rewalk-063c` was **not** merged (stale product overlap of #79–#84). |

## 3. Current blockers

### Agent-doable (remaining after #86–#93)

1. **AppShell nav IA** — 50+ wrapping links, duplicate labels. **Proposed, awaiting decision** in `docs/APPSHELL_NAV_IA.md` (PR #98). Do not implement until Accept / Accept with edits / Reject. The wrap is still live.
2. **Parent notified shows — on excused** — column exists (#86); SMS body is not visible in the portal; log-only outside production.

### Operator-only

1. **Staging login / seed** — no SSH from this environment; webhook deploy only (`docs/STAGING.md`). Seed passwords 302 back to login. Someone with server access must seed (or set real passwords) and paste `students:verify-unification` + `morph-map:verify` for **current** `main`. Round 3 ranked #1.
2. **GitHub branch protection** — docs exist (`docs/BRANCH_PROTECTION.md`); apply was 403 (archive A4).
3. **Deploy 3 cleanup** — proposal only (`docs/migrations/s11-deploy-3-cleanup-proposal.md`). Dual-write still on. **Do not execute.**
4. **Credential smoke / BML sandbox** — never completed on staging.
5. **`QURAN_HALAQA_DUAL_WRITE`** — leave off until an operator confirms dual-write; no read switch.
6. **Payroll** — leave `PAYROLL_ENABLED` / `payroll.enabled` off.
7. **SMS_LIVE in production** — local/staging now fail closed. Decide when (if) production should send Dhiraagu.

## 4. Decisions awaiting the owner

| Decision | Why it is blocked on a person, not an agent |
|---|---|
| **Pilot timing** | Staging cannot start the rehearsal. Local walk is not `test.akuru.edu.mv`. |
| **Track B vs finishing gaps** | ADR-021 representative gate is green (archive). Track B leftovers **B1–B4 are on main** (#102–#105). Remaining school-loop gaps: staging, AppShell nav. |
| **Deploy 3** | Confirm or reject the cleanup proposal. Do not run it as a drive-by. |
| **Branch protection** | Apply on GitHub or accept that every PR must wait for CI and not self-merge (S2 kickoff terms). |
| **SMS_LIVE / production flag** | When (if) production should send Dhiraagu. Local/staging already bind `LogSmsSender`. |
| **HTML vs PDF documents** | **Decided (#97).** ADR-012: HTML is the supported production output. PDF is a future `DocumentRendererInterface` binding swap, not a domain `if`. |
| **AppShell nav IA** | `docs/APPSHELL_NAV_IA.md` (PR #98) proposes a role-first, frequency-second map. Reply Accept / Accept with edits / Reject. **Do not implement** until that reply. The wrap is still live. |

## 5. Overstated “done” (DoD now includes a browser walk)

`CLAUDE.md` / `.cursorrules` DoD was amended in **#92**: a slice is done when a user can complete the task **in a browser**, not only when tests pass.

| Claim that was too strong | Wording that matched the evidence (post #86–#93) |
|---|---|
| STATUS “Remaining blockers: none” | Remaining: staging login, AppShell nav. Student create (#95), weights persist (#96), HTML-as-output (#97), roster number-twins (#99), gradebook URL (#100), class teacher on existing class, and year-seeder uniqueness closed. |
| S3.4 “Term grades (done)” | Computes when a weight scheme exists. Banner when missing (#89). Weights UI now persists a scheme (#96). |
| S3.6 “Report cards (done)” | Queued **HTML**; labelled HTML (#89). %/grade fill when a scheme exists (#96). Not PDF. |
| S3.1 weights implied ready | Scales/types seed; Weights UI now posts numeric percents summing to 100 (#96). |
| 1A / 1B / 2 / Arabic A / Qur’an A “done” | Code + Pest exist. **1A glossary** tables/CRUD/player added this slice. Other 1A/1B/2 still **USABLE UNVERIFIED**. |
| S5.1–S5.5 “done” | Code + Pest. **UNVERIFIED**. S5.6 is honestly “done; flagged off”. |
| S2.3 builder “done” | Page exists. R2 extra-period drag did not persist. |

Fixed enough that the old overstatement no longer applies: SMS live-bind, `DatabaseSeeder` ≠ school, Blade parent/teacher landing, fill-grid names-only, generate-0 copy, class/year 500s, invoice drafts-only list.

## 6. Out of scope (unchanged)

Hifz behaviour frozen. Deploy 3 not executed. Track B leftovers B1–B4 merged (#102–#105). Phase 3 C1–C3 merged (#106–#108). D1–D3 portal composition merged (#109–#111). W1.1–W1.4 merged (#112–#115). W1.5 SEO + sharing is this PR.

**Operator:** apply branch protection (`docs/BRANCH_PROTECTION.md`). Confirm or reject `docs/migrations/s11-deploy-3-cleanup-proposal.md`.

**Qur'an A.4b (later):** switch offering-session reads to `offering_halaqa_session_links` after operators confirm dual-write. Then Hifz cleanup (deploy 3). Keep `QURAN_HALAQA_DUAL_WRITE` off until verified.

**Arabic B / Qur'an B / later:** pronunciation AI, Capacitor, W1–W3, L-track.
