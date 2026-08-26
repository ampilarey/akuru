# Known issues

Ranked by **harm > wrong data > blocked task > confusion > cosmetic**.  
Evidence is a pilot section, a `file:line` on `main` `c21630a`, or a PR. Open fix PRs are **not** merged; items stay open until they are on `main`.

This list merges Round 1 + Round 2 (`docs/PILOT_REHEARSAL.md`), holes called out in [`STATUS_ARCHIVE.md`](STATUS_ARCHIVE.md), and verification in the 2026-08-26 status audit.

---

## P0 — Harm (real people, real messages, real money)

### 1. SMS is the live Dhiraagu/HTTP gateway in every environment

**Severity:** harm — a teacher marking absent (or issuing invoices) on local or `test.akuru.edu.mv` attempts a **real** send. Seeded numbers `7820288` and `7972434` are named in STATUS as real OTP test phones.

**Evidence:**

- Binding: `app/Domains/Notifications/Providers/NotificationsServiceProvider.php:25` → `SmsSenderInterface` = `SmsGatewayService`. Line 26 already uses `NullPushSender` for push. Line 31: `StudentMarkedAbsent` → `SendAbsenceSms`.
- Round 1 step 3; Round 2 step 3 and ranked item 8 (`docs/PILOT_REHEARSAL.md`).
- `SmsGatewayService::useDhiraagu()` with empty credentials does **not** enter demo log mode; it falls through to HTTP (`https://akuru.edu.mv/api/v2/sms/send` by default). Pilot § Round 1 step 3.
- Invoice issue also notifies guardians (`STATUS_ARCHIVE.md` S4.3).

**Unfixed on `main`.** A logging-fake PR may exist off-branch; until it merges, treat every absence-mark as a possible live SMS.

### 2. Staging staff login does not work with documented seed passwords

**Severity:** harm-adjacent / blocked production judgement — the intended pilot host cannot be used; public 200 hides that.

**Evidence:** Round 1 step 0; Round 2 opening; archive “Staging credential smoke (2026-08-23)”. `https://test.akuru.edu.mv/en/login` 302s back to login for `admin@akuru.edu.mv` / `password`. This environment cannot SSH (`docs/STAGING.md` webhook only).

---

## P1 — Wrong data (identity, grades, documents)

### 3. Term grades render blank unless a weight scheme is actually saved

**Severity:** wrong data — published exams and marks are visible; Term % / Grade / Rank are `—`; report cards then print empty cells.

**Evidence:** Round 1 step 5; Round 2 step 5 (gradebook screenshot notes; `assessment_weight_schemes` count 0). `tests/Feature/ExamsGrades/TermGradesTest.php` always calls `SaveWeightSchemeAction` first — the walked failure is untested.

### 4. “Report cards” are HTML with empty grades, not PDF

**Severity:** wrong data / mislabelled document. Parents/admin download a `.html` file. Fatima’s card: Arabic %/grade/GPA/rank empty; attendance 0% on one excused day (Round 1).

**Evidence:** `ReportCardController::download` `filename="report-card-'.$reportCard->id.'.html"` (`app/Domains/ExamsGrades/Http/Controllers/ReportCardController.php:108`). Production binding `HtmlDocumentRenderer`. Awards/ID cards same pattern (`AwardController`). Round 1 step 5; Round 2 step 5.

### 5. Wrong child is easy on the register fill grid

**Severity:** wrong data — two Mariyam Ali / two Ahmed Naseem, name only, no PIL/DOB/NID.

**Evidence:** Round 1 step 2; Round 2 step 2. `resources/js/Pages/Academics/Registers/Show.jsx` has no `date_of_birth` / student number columns. Marks grid **does** show PIL numbers (Round 2 step 5) — inconsistent.

### 6. Roster picker still allows indistinguishable twins

**Severity:** wrong data. Round 2 item 9 is partly fixed (search is not raw `students.id`), but two Fatima Yoosuf share name+NID+DOB; identity key includes class so they are not flagged; admin can add the blank-number leftover unification row.

**Evidence:** Round 2 step 1; `docs/PILOT_REHEARSAL.md` ranked item 6 after #79–#84.

### 7. Unification matcher / staging collisions (historical, still a staging gate)

**Severity:** wrong data if `--backfill` is run on a messy DB. Staging verify last recorded **red** (four collisions, 12/13 guardian users missing after `users:clear-non-admin`).

**Evidence:** `STATUS_ARCHIVE.md` staging 2026-08-25 and TRACK A. Representative local gate green (ADR-021) is **not** staging green.

---

## P2 — Blocked task (cannot finish the job in the product)

### 8. `DatabaseSeeder` is not a usable school

**Severity:** blocked task — after `migrate:fresh --seed`: 0 students, 0 `teachers` rows, 0 academic years (Round 2). Class-teacher dropdown empty; picker empty; Today “No teacher profile”. Spatie role `teacher` is not a `teachers` row. `PeriodSeeder` **is** in `DatabaseSeeder` (post-#83); years/students/teachers are not.

**Evidence:** `database/seeders/DatabaseSeeder.php`; Round 2 “Cold DatabaseSeeder only”; Round 2 ranked item 3.

### 9. Blade dashboard after login hides Years / Today / Exams / Invoices

**Severity:** blocked task — a teacher who follows the first screen never reaches the loop.

**Evidence:** Round 1 step 1; Round 2 step 1 ranked item 2. Blade nav: Dashboard / Enrollments / Students / Teachers / Hifz / Quran / More. Inertia AppShell is a separate URL paste.

### 10. Parent landing titled Admin Dashboard

**Severity:** blocked task / confusion. `DashboardController::parentDashboard()` (`app/Domains/Portal/Http/Controllers/DashboardController.php:241`): if `parentGuardian` is missing **or** any exception, it returns `adminDashboard()`. Stats use `$children->whereHas(...)` on a **Collection** (~line 255), which throws and hits that fallback.

**Evidence:** Round 2 step 3 screenshot “Parent Hassan Ahmed lands on Blade Admin Dashboard”.

### 11. People → Students cannot create a child

**Severity:** blocked task. Index is search + CSV only (`resources/js/Pages/People/Students/Index.jsx`). Round 2.

### 12. Duplicate class create is a 500, not a form error

**Severity:** blocked task. Unique `(name, section, academic_year_id)` → `UniqueConstraintViolationException` Ignition page (Round 2 step 1, Grade 5 B).

### 13. Class teacher assignment did not stick on the class created in the walk

**Severity:** blocked task for “admin assigns class teacher in the UI.” Field exists and Grade 5 A shows Fatimat Ali (seeder). Grade 5 B = **—** / Class teacher: None after the duplicate-500 path.

**Evidence:** Round 2 step 1. Backend accepts `class_teacher_id`; R1 the form omitted it (#82 added the field).

### 14. No periods UI was a blocker; periods CRUD now exists — cold timetable still needs periods **and** a year

**Severity:** was P2; mitigated on `main` by `PeriodSeeder` + `/academics/periods`. Remaining: without `PilotRehearsalSeeder` there is still **no year**, so builder/generate still produce nothing useful.

### 15. Report card generate needs a template + queue worker

**Severity:** blocked task if either is missing. Round 1 step 5: generate without template errors; `QUEUE_CONNECTION=database` leaves cards draft without `queue:listen`.

### 16. Staging unify-verify / Deploy 3 / Track B

**Severity:** blocked **operator** tasks, not missing code. See STATUS §3–4.

---

## P3 — Confusion (easy to think it worked or failed)

### 17. AppShell nav is unusable as navigation

**Severity:** confusion. 50+ wrapping links, duplicate “Report cards” / “Awards”. Logout **is** present (POST next to the name, Round 2). `GET /logout` remains 405 (`STATUS_ARCHIVE.md` pilot blocker 2).

### 18. “Created 0 expected registers”

**Severity:** confusion — rows already existed from the seeder; Unfilled hides today’s remaining periods until they are late; a class teacher sees only **their** subject cards.

**Evidence:** Round 2 step 1–2, ranked item 7.

### 19. Duplicate academic year names

**Severity:** confusion. Two `2026-2027 Extra` tabs (R1 and R2). No unique on `academic_years.name`.

### 20. Shared Add-term form on every year card

**Severity:** confusion. One `termForm` instance; typing on Extra fills other cards (Round 1 step 1; still in R2).

### 21. Invoice admin list looks empty after issue / on the wrong year tab

**Severity:** confusion. `ListDraftInvoicesAction` filters `status = draft` (`app/Domains/Finance/Actions/ListDraftInvoicesAction.php:17`). After Issue, switching to Extra shows “No draft invoices” while Pilot has 45 **sent**. Parent portal still shows them (Round 2 step 6).

### 22. Invoice generate dates hardcoded Jan–Mar 2026

**Severity:** confusion. `resources/js/Pages/Finance/Invoices/Index.jsx` `period_start: '2026-01-01'`, `period_end: '2026-03-31'` — not “this term”.

### 23. Exam schedule form defaults wander

**Severity:** confusion. Extra / Term 2 / Arabic Beginners vs Pilot Grade 5 A (Round 2 step 5).

### 24. Weights UI is a JSON blob of type ids → 0

**Severity:** confusion. Walk did not persist a scheme (Round 2 step 5). Connects to issue 3.

### 25. Teacher grid offers `excused` / `left_early`

**Severity:** confusion. Excuse is supposed to come from an approved note (Round 1 step 2).

### 26. Taught summary vs plan topic

**Severity:** confusion / twice. Picking “Sun and moon letters” still invites typing the same title (R1/R2 step 2).

### 27. Vite HMR blank Inertia pages on this VM

**Severity:** confusion for agents. `@vitejs/plugin-react can't detect preamble`. Round 2 used `npm run build` + no `public/hot`. Not an app defect for production `public/build`.

---

## P4 — Cosmetic / later

### 28. Create-year description in `useForm` but no field; unlabelled date inputs; copyright 2025 on login

**Evidence:** Round 1 step 1.

### 29. Report-card publish control defaulted to Term 2 while the table is Term 1

**Evidence:** Round 2 step 5.

### 30. Blade counters (28 students / 3 teachers) are not “Grade 5 A”

**Evidence:** Round 2 step 1.

---

## Explicitly not defects

- **Payroll off** — `PAYROLL_ENABLED=false` and settings `payroll.enabled` — by design (S5.6).
- **Hifz frozen** — rule 7; no behaviour change this cycle.
- **Qur’an dual-write off** — `QURAN_HALAQA_DUAL_WRITE` default false; A.4 is dual-write only.
- **BML Pay now untested** — sandbox, not a product lie by itself; Rule 12 still requires webhook confirmation.
- **UNVERIFIED slices** (S2.1 rooms, S2.4–S2.5, S2.9–S2.10, S3.5, S3.7, S4.4–S4.5, S5.*, 1A.2–2.5, Arabic A, Qur’an A) — absence of a walk is not a recorded functional bug.

---

## Round-1 vs Round-2 (what actually moved)

Fixed enough to drop from the “cannot start” list **on a seeded local DB**: verified `user_contacts` in `UserSeeder`, AppShell POST logout, roster **search** (not raw id), class-teacher **field**, periods seed+CRUD, teacher can generate today, picker shows number/DOB/NID.

Still on the list after Round 2: items **1–6, 8–13, 17–22** above. Item 1 (SMS) is still the highest severity and still unfixed on `main`.
