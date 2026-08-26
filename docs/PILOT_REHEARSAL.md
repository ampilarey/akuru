# Pilot rehearsal findings

**Date:** 2026-08-26  
**Slice:** end-to-end walk of one class. No product fixes in this slice.  
**Intended host:** `https://test.akuru.edu.mv`  
**Host actually walked:** local MySQL `akuru_institute` at `http://127.0.0.1:8000`. Staging was probed and **could not be seeded or logged into**.

This is a findings document. A step that cannot be completed is recorded as failed, not worked around in the product.

Two passes are recorded, then a third after Round-2 product PRs:

- **Round 1** (`cursor/pilot-rehearsal-063c`, merged as #78): Chrome for Step 1 only; Steps 2–6 were domain actions / tinker. Original ranked list is unchanged.
- **Round 2** (this file, after blocker PRs #79–#84 on `main`): Chrome as admin → teacher → parent → teacher → admin → parent. Fresh `migrate:fresh --seed` then `PilotRehearsalSeeder`. Screenshots: `/opt/cursor/artifacts/pilot-round2/`.
- **Round 3** (this file, after Round-2 fix PRs **#86–#92**, walked on a local merge of those branches; **#86–#93 are now on `main`**): Chrome steps 1–6. Screenshots: `/opt/cursor/artifacts/pilot-round3/`. Round 1 and Round 2 text is unchanged. Re-checked remaining items in `docs/KNOWN_ISSUES.md` after merge.

Operator seed (after `DatabaseSeeder`):

```bash
php artisan db:seed --class=PilotRehearsalSeeder
```

---

## Round 1 — how that walk was done

1. Staging HTTP: `/en` and `/up` return 200. Seed login `admin@akuru.edu.mv` / `password` POSTs to `/en/login` and **302s back to `/en/login`**. No SSH from this environment (`docs/STAGING.md`: webhook pull only). The seeder was **not** run on staging.
2. Local: `PilotRehearsalSeeder` on `akuru_institute`. **Browser (Chrome):** Step 1 only, ~33 clicks, `http://127.0.0.1:8000`. Click-by-click notes: `/opt/cursor/artifacts/pilot-rehearsal/walk-notes.md`. **Domain actions** (tinker) for register → attendance → absence note → exam → report card → invoices against the seeded Grade 5 A class, because the browser could not switch users.
3. ADR-021 identity mess is in the seeder (duplicate NID `A999001`, blank/`N/A`/`0`/`-`, two Mariyam Ali, two Ahmed Naseem 2009-04-04, Hussain Shareef with no guardian). This local DB also already had `UnificationRepresentativeSeeder` Fatima/Hussain rows (same names and NID, **no** `student_id` number).

Logins after the seeder (password `password`): `admin@akuru.edu.mv`, `teacher@akuru.edu.mv`, `parent@akuru.edu.mv` (Fatima Yoosuf / **PIL-01**, `students.id` **14** — not `1`).

---

## Round 1 — Staging (step 0) — cannot start the rehearsal there

| | |
|---|---|
| Worked? | **No** |
| Screens | 1 (login), then bounce |
| What broke | Seed credentials do not authenticate. This matches the 2026-08-23 credential smoke in `STATUS.md`. |
| Confusing | Public site looks healthy, so it is easy to assume staff logins work. |
| Twice | n/a |

Until an operator seeds `PilotRehearsalSeeder` on the server **and** creates verified `user_contacts` (or uses real staging passwords), a teacher cannot use `test.akuru.edu.mv` next week.

---

## Round 1 — Seeded scenario (local)

| Item | Result |
|---|---|
| Year | `2026-2027 Pilot` (active), Term 1 active |
| Class | Grade 5 A, `class_teacher_id` set **in the seeder** (not in UI) |
| People | 15 `PIL-*` students, 3 `teachers` rows, guardians on 14/15 |
| Timetable | Mon–Fri, three periods, three subjects |
| Plan | Grade 5 Arabic Term 1 → topic “Sun and moon letters” |
| Fees | Active structure `Pilot Grade 5 fees` (MVR 1500 / month) |
| Periods | `PeriodSeeder` (not in `DatabaseSeeder`) |

`DatabaseSeeder` still does not create years, periods, `teachers` rows, guardians, or `user_contacts`.

Password login (`LoginRequest`) looks up **verified `user_contacts`**, not `users.email`. Default seed users therefore cannot sign in. On this VM’s `akuru_institute` database, only `admin@akuru.edu.mv` already had a leftover verified email contact (dated 2026-08-07). Teacher and parent logins failed until `PilotRehearsalSeeder` inserted verified email contacts. That is rehearsal data, not a login-product fix.

Shared `users.phone` `+960 782 0288` on admin, teacher, and parent would also make **mobile** login collide if anyone used the phone identifier.

---

## Round 1 — Step 1 — Admin creates year / term / class and assigns class teacher

**Worked?** Partial for year/term/class. **Class teacher assignment cannot be completed in the UI.** Browser walk stopped here: **cannot switch to teacher/parent from AppShell.**

**Clicks / screens (browser, ~33):** login → Blade dashboard → paste `/en/academics/years` → create `2026-2027 Extra` → Add term “Term 2” → Activate (blocked) → `/en/academics/classes` → create Grade 5 B → add Student ID `1` → open Grade 5 A → type `GET /logout` (405).

After password login the admin lands on the **Blade** dashboard (`/en/dashboard`), not Inertia `AppShell`. Blade nav is Dashboard / Enrollments / Students / Teachers / Hifz / Quran / More. The new Years / Classes / Today / Exams / Finance links live on Inertia `AppShell` only. A teacher next week who follows “what I see after login” never reaches the class-register loop.

Blade dashboard on first paint (before the institute seed finished) showed **13 students / 1 teacher** — the legacy Blade counters, not Grade 5 A.

<img src="/opt/cursor/artifacts/pilot-rehearsal/00-login-page.png" alt="Blade login: email/phone/ID, Admin Login with OTP, copyright 2025" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/01-admin-dashboard.png" alt="Blade admin dashboard after login; Inertia academic screens are not in this nav" />

**Years UI** (`resources/js/Pages/Academics/Years/Index.jsx` + `AppShell.jsx`):

- Header is a wrapping wall of 50+ links (Learn, Teach, Years, Today, Exams, Payroll, …). Duplicate labels (“Report cards”, “Awards”).
- Create-year form has name + two unlabelled date inputs. `useForm` includes `description` but there is **no description field**.
- One shared `termForm` is rendered on **every** year card. Typing “Term 2” + dates on Extra filled the same inputs on closed `2026-2027` and on Pilot. Submit posts to whichever card’s button you click, with that shared state.
- Add-term form defaults to **“Term 1”** even when Term 1 already exists. Dates are unlabelled `mm/dd/yyyy`. Term **status** is not in the form (backend default `upcoming`).
- Year names are not unique: this walk created **two** rows named `2026-2027 Extra` (ids 3 and 4). Classes later showed **two Extra tabs**.
- Activate on Extra shows: *“Another academic year is already active. Close it before activating this one.”* That is correct, but Close + Activate is two trips and easy to miss. Extra stayed `upcoming`, so Grade 5 B was created on the still-active Pilot year.

<img src="/opt/cursor/artifacts/pilot-rehearsal/02-years-page-nav-overflow.png" alt="Inertia Academic years: overflowing AppShell nav, closed 2026-2027 plus active Pilot" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/03-year-created-extra.png" alt="2026-2027 Extra created upcoming; closed 2026-2027 and active Pilot still on the page" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/04-term-created-shared-state.png" alt="Term created flash; Add term still shows Term 2 dates on every year card" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/05-activate-year-error.png" alt="Cannot activate Extra while Pilot is already active" />

**Classes UI** (`Academics/Classes/Index.jsx`): create fields are name, section, level, capacity. Backend `ClassDirectoryController@store` accepts `class_teacher_id`; the form **does not send it**. Listing is Name / Section / Capacity — no teacher column. Grade 5 B was created with `class_teacher_id` null.

<img src="/opt/cursor/artifacts/pilot-rehearsal/06-classes-no-teacher-field.png" alt="Create class: name, section, level, capacity only; two Extra year tabs; no class teacher field" />

**Roster add** is a raw **“Student ID”** box (`Classes/Show.jsx`). Entering `1` flashed “Student assigned.” The row is **Fatima Yoosuf with a blank Number**. That is `students.id` **1** (leftover `UnificationRepresentativeSeeder` Fatima, NID `A999001`, DOB 2010-03-12, `student_id` null) — **not** rehearsal PIL-01 (`students.id` **14**, still only on Grade 5 A). Same legal name, same NID, same DOB; the admin cannot tell them apart in the picker because there is no picker.

<img src="/opt/cursor/artifacts/pilot-rehearsal/07-class-show-raw-id-field.png" alt="Grade 5 B empty roster: Student ID text box and Add to roster" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/08-student-added-by-id.png" alt="Grade 5 B after adding id 1: Fatima Yoosuf, Number blank, not PIL-01" />

Grade 5 A show **does** list PIL-01…PIL-15 in the Number column, including two Mariyam Ali (PIL-07/08) and two Ahmed Naseem (PIL-09/10). **No class teacher name anywhere.** Distinguishing numbers exist here; they are **absent on the teacher register fill grid** (Step 2).

<img src="/opt/cursor/artifacts/pilot-rehearsal/09-class-a-no-teacher-shown.png" alt="Grade 5 A roster of 15 with PIL numbers; duplicate names; no class teacher" />

**Logout (browser cutoff).** `AppShell` prints `auth.user.name` as text (`Admin User`) and has **no logout control**. Blade `super-admin` Quick Actions **does** POST to `logout` — only if you go back to `/en/dashboard`. Typing `/logout` in the address bar is GET → Ignition `MethodNotAllowedHttpException`: *“The GET method is not supported for route logout. Supported methods: POST.”* Steps 2–6 were not walked in the browser.

<img src="/opt/cursor/artifacts/pilot-rehearsal/10-logout-method-error.png" alt="GET /logout MethodNotAllowedHttpException; logout is POST only" />

**Periods:** no Inertia/Blade admin screen. Timetable builder options come from `periods`. Empty unless `PeriodSeeder` (or SQL) has run. Teacher cannot create periods.

**Double entry:** year name `2026-2027 Extra` is still sitting in the create-year form after success, so a second Extra is one extra click. Term dates are re-entered on every card because one shared `termForm` instance is reused for all years. Student “id 1” is not the same as PIL-01.

---

## Round 1 — Step 2 — Teacher: today’s register, plan topic, attendance

**Worked?** **Not as a teacher-only user, from a cold start.** The write path works if an admin generates registers first and the login has a `teachers` row.

**Clicks / screens if unblocked:** logout/switch user (blocked in AppShell) → Today → Fill register → pick topic → grid → Submit (~4 screens). Cold start is more. **Not executed in the browser** after Step 1 logout failure.

**What broke**

1. **Login.** Without verified `user_contacts`, teacher 302s back to login. Default `UserSeeder` does not create those rows.
2. **No `teachers` row.** `Today.jsx` shows “No teacher profile is linked to this login.” Blade `/en/teachers/create` is a separate, older flow. `UserSeeder` creates the user only.
3. **Empty Today.** Expected `lesson_logs` are created by admin `POST /academics/registers/generate` (`registers.manage`) or `php artisan registers:generate-expected`. Teachers have `registers.fill` but **not** `registers.manage`. Today has no generate button. Unfilled registers (`/academics/registers`) is 403 for a teacher.
4. **Periods / timetable.** No periods in default seed → builder cannot place slots → generate creates nothing.

**On this seeded DB (admin generated today’s three slots, then the class teacher submitted):**

- Roster names: `Fatima Yoosuf, Hussain Shareef, … Mariyam Ali, Mariyam Ali, Ahmed Naseem, Ahmed Naseem, …` — **no student number, NID, or DOB** on `Registers/Show.jsx`. The messy identity data is invisible in the grid.
- Topic dropdown included “Sun and moon letters”. Submit with that topic + taught summary succeeded.
- Fatima → absent, Aminath Rishfa → late 10, rest present: 15 `class_attendance` rows. Fatima `2026-08-26` / `absent`.

**Confusing:** attendance statuses include `excused` / `left_early` on the teacher grid even though excuse is supposed to come from an approved note. Plan field “Teacher id” on Plans is a raw id for admins.

**Twice:** taught summary is still required-or-topic; picking the topic still invites typing the same title into “What was taught”.

---

## Round 1 — Step 3 — Parent notification (`SmsSenderInterface`)

**Worked?** **Event is wired. There is no fake in staging/local, no UI confirmation, and “demo mode” is not what actually runs.**

`RecordClassAttendanceAction` dispatches `StudentMarkedAbsent` when status is absent. `SendAbsenceSms` is bound in `NotificationsServiceProvider`. Payload would be:

- phone: from `guardian_student` → `parent_guardians.phone` (Fatima: `7820288`)
- message: `Akuru Institute: {name} was marked {status} on {date}.`
- options: `type=attendance`, `reference=attendance_{date}_{studentId}`
- throttle: Cache key `attendance-sms:{studentId}:{date}` until end of day

**What broke / is unsafe**

- Binding is `SmsGatewayService`, not a fake. Staging has no test double.
- `SmsGatewayService::useDhiraagu()` is `enabled && (username || password)`. With `SMS_USE_DHIRAAGU=true` and **empty** Dhiraagu user/pass, demo mode inside `sendViaDhiraagu()` is **never entered**. Execution falls through to `sendViaHttpGateway` (`https://akuru.edu.mv/api/v2/sms/send` by default). That path does not log “SMS Demo Mode - Would send”.
- After marking Fatima absent, `storage/logs/laravel.log` had **no** SMS lines. Cache key was not inspectable across processes (`CACHE_STORE=array` in this agent shell). The UI also shows nothing.
- Hussain Shareef (PIL-02, duplicate NID, **no guardian**) would never notify — correct, but silent.

**Do not treat empty Dhiraagu creds as a fake.** Confirm payload in logs or a fake binding before a real teacher marks absents on staging.

---

## Round 1 — Step 4 — Parent absence note → teacher approve → excused

**Worked?** **Yes, at the action layer.** Portal and review screens exist; parent needs a `parent_guardians.user_id` link (`ListGuardianChildrenAction`). Default `parent@akuru.edu.mv` has no such row until the seeder.

**Clicks / screens:** portal Absence notes (submit) → logout → teacher Review notes → Approve (~4–5 screens). No deep link from the SMS (there is no SMS UI). **Not executed in the browser** (logout).

**UI** (`Portal/AbsenceNotes.jsx`): child, date, type, reason. Backend accepts `attachment` and `period_id`; the form has **neither**. `affects_attendance` is hardcoded true in the form payload.

**Review** (`Academics/AbsenceNotes/Index.jsx`): teacher **does** have `manage_attendance`, so approve is allowed. After approve, Fatima’s row flipped `absent` → `excused` with `absence_note_id` set.

**Confusing:** parent must type today’s date; it is not defaulted. Two Mariyam Ali / Ahmed Naseem would be indistinguishable in a sibling dropdown (Fatima is unique here). Teacher must find Review notes in the overflowing Inertia nav; it is not on the Blade dashboard.

**Twice:** reason is typed by the parent; teacher can type review notes. Attendance date must match the note date or excuse matching misses (see `ApproveAbsenceNoteAction`).

---

## Round 1 — Step 5 — Admin exam, marks, term grade, report card PDF

**Worked?** Exam + marks + HTML document **yes**. Term % / grade / rank **blank**. File is **HTML via `HtmlDocumentRenderer`**, not a PDF.

**Clicks / screens if you know the map:** Exam types (seeded by migration) → Schedule → Move `scheduled`→`marks_entry` → Marks (15 individual saves or CSV) → Move `marks_entry`→`review` → Move `review`→`published` → Weights (easy to skip) → Gradebook Load + Recompute → Report templates → Report cards Generate → wait for queue → Download. **~10+ screens.** `QUEUE_CONNECTION=database` requires `queue:listen` or cards stay draft.

**What broke**

- Status machine is `scheduled → marks_entry → review → published → locked`. Marks are blocked until `marks_entry`. Skipping Review is not allowed. Easy to think “Move” publishes.
- `ComputeTermGradesAction` share for the Final was **0** because there is **no `assessment_weight_schemes` row** for the year. All 15 term grades stored `weighted_percent=null`, `grade=null`, `rank=null`. Gradebook Recompute without Weights produces empty term columns.
- Generate without a template errors: *“No active report card template applies to this class.”* Templates are not seeded.
- Download uses `Content-Disposition: inline; filename="report-card-{id}.html"` and `HtmlDocumentRenderer`. Not PDF.
- Fatima’s generated HTML: Arabic Language %/grade/GPA/rank **empty**; attendance “Percent: 0% (present 0, late 0, absent 0, excused 1, total 1)” — one excused day reads as 0% attendance.

<TextReference path="/opt/cursor/artifacts/pilot-rehearsal/05-report-card-fatima-yoosuf.html" start={16} end={51} alt="Fatima report card HTML: empty grade cells, 0% attendance with one excused day"></TextReference>

**Confusing:** two Ahmed Naseems on the marks grid with the same display name. Bulk “one exam per subject” still needs confirm checkboxes.

**Twice:** class / term / subject re-selected on schedule, marks, gradebook, and report cards.

---

## Round 1 — Step 6 — Admin invoice from fee structure, parent portal

**Worked?** **Yes**, on the seeded structure.

**Clicks / screens:** Fee items (catalog) → Fee structures (already seeded here) → Invoices generate drafts → Issue drafts → parent Fees. ~4 screens if the structure exists; more if you must create fee items first. Default seed has **no** fee items.

**What happened (actions, 2026-08-26):** `GenerateInvoicesAction` for 2026-01-01..2026-03-31, `per_month` → **45** drafts (15 students × 3 months), `INV-2-14-2026-01`, total `1500.00`. Issue → status `sent`. Parent portal for Fatima: **3** invoices.

**Confusing:** invoice generate dates default in the React form to `2026-01-01` / `2026-03-31` (hardcoded in `Finance/Invoices/Index.jsx`), not “this term”. Drafts are listed as drafts until Issue; portal `ListPortalInvoicesAction` shows all statuses. Pay now goes to BML — not exercised (sandbox, no real payment).

**Twice:** year picked on fee structures and again on invoices. Amount 1500 is on the catalog item and again on the structure line.

---

## Round 1 — Ranked: what would block a real teacher next week

1. **Cannot log in on staging** with documented seed passwords; this agent cannot seed staging. Operator credentials are required.
2. **Cannot log in locally/staging with `DatabaseSeeder` users** until each has a **verified `user_contacts` email (or mobile)**. `users.email` is not enough.
3. **No `teachers` row** → Today is empty with “No teacher profile is linked to this login.” Creating the user with role `teacher` is not enough.
4. **Registers for today do not exist** until someone with `registers.manage` generates them. The teacher cannot do that. Today does not explain why the list is empty.
5. **No periods in default seed, and no periods UI** → timetable cannot be built → generate creates nothing.
6. **After login, Blade dashboard hides the loop.** Years / Today / Plans / Review notes are Inertia `AppShell` only, and that nav is unusable on a laptop screen.
7. **AppShell has no logout.** Name is plain text. `GET /logout` 405s. Blade dashboard Quick Actions can POST logout if you know to leave Inertia. A shared staff PC cannot switch admin → teacher → parent from the academic screens.
8. **Class teacher cannot be assigned in the class UI.** Backend field exists; form omits it. Seeder/tinker only.
9. **Roster add is a numeric primary key.** Typing `1` enrolled leftover unification Fatima (blank number), not PIL-01 (`students.id` 14). Year names are not unique (two `2026-2027 Extra` tabs).
10. **Duplicate names on the register** (two Mariyam Ali, two Ahmed Naseem) with no number/DOB — wrong child is a real risk. Class show *does* show PIL numbers; the fill grid does not.
11. **Absence SMS is not a fake** on staging/local; demo-mode log is unreachable with current Dhiraagu config; no in-app “parent notified” signal.
12. **Term grades stay blank** unless Weights are set for the year. Report cards then print empty %/grade/rank.
13. **“PDF” is HTML**, queued. Without a worker and a template, generate fails or never becomes downloadable.

Items 1–6 stop the teacher before attendance is saved. Item 7 stops a multi-role rehearsal on one browser. Items 8–10 make the first week error-prone even after an operator seeds. Items 11–13 break parent comms and reports.

---

## Round 2 — browser walk after blocker PRs #79–#84 (2026-08-26)

**Worked as a three-user Chrome loop:** yes, locally, after operator seed. Staging still cannot start.

**Code:** `main` including #79 roster picker, #80 AppShell logout, #81 seed login contacts, #82 class teacher field, #83 periods, #84 teacher generate-today.

**Setup (not product):** `migrate:fresh --seed` then `PilotRehearsalSeeder`; `npm run build`; Laravel `:8000`. First Chrome attempt against Vite HMR (`public/hot`) rendered every Inertia page blank (`@vitejs/plugin-react can't detect preamble`). That is this VM’s Vite client, not an app bug. Walk continued on production `public/build`. Screenshots `10+` are the product walk; `01–07` are the HMR dead end.

**Cold `DatabaseSeeder` only (before PilotRehearsalSeeder):** 0 students, 0 `teachers` rows, 0 academic years, 12 periods, 6 verified `user_contacts`. Class-teacher dropdown would be empty; the roster picker would have nobody to find; Today would have no teacher profile. Operator seed is still required. People → Students is a **search list with no create form**, so an admin cannot type a new child into existence on that screen.

Logins (`password`): `admin@akuru.edu.mv`, `teacher@akuru.edu.mv`, `parent@akuru.edu.mv`. AppShell **Log out** is next to the name on Inertia pages (POST). Blade dashboard uses the user dropdown **Log Out**. `GET /logout` was not used as the happy path.

---

### Round 2 — Step 1 — Admin: year / term / class / teacher / picker / periods / timetable / generate

**Worked?** **Partial.** Year + term + periods + picker search work. Assigning a class teacher **on a new class in this walk** did not stick. Generating “today” from Registers created **0** new rows (slots already existed from the seeder). Extra timetable cell did not persist.

**Clicks / screens (~25):** login → Blade `/en/dashboard` → paste `/en/academics/years` → create Extra → add term → Activate (blocked) → Classes → Create Grade 5 B (500) → open Grade 5 B → search `1` / `Fatima` / `PIL-01` → Add PIL-01 → Periods → create Pilot extra → Timetable (seeder grid already filled) → Registers generate 2026-08-26 → AppShell Log out.

**What broke**

- After login the **Blade** dashboard still has Dashboard / Enrollments / Students / Teachers / Hifz / Quran / More. Years / Classes / Today / Periods live only on overflowing Inertia `AppShell`. A teacher who follows “what I see after login” still never reaches the loop.
- Year names are still not unique. Create form stays filled (`2026-2027`). This walk produced **multiple `2026-2027 Extra` tabs**. Activate Extra still: *“Another academic year is already active. Close it before activating this one.”*
- Shared Add-term fields still sit on every year card.
- **Create class** posts `class_teacher_id` (users.id). Duplicate `Grade 5` / `B` on the same year is an **Ignition 500** (`UniqueConstraintViolationException` `Grade 5-B-2`), not an inline error.
- Classes listing **does** show a Class teacher column. Grade 5 A = Fatimat Ali (seeder). Grade 5 B = **—**. Show line: **Class teacher: None**. The duplicate 500 meant B was never created in this click — the existing B stayed teacher-less.
- Roster is a search picker (name / number / NID). `q=1` → *No students match* (does not bind `students.id`). `q=Fatima` → two Fatima Yoosuf, same DOB `2010-03-12`, same NID `A999001`: blank number on Grade 5 B vs **PIL-01** on Grade 5 A. Add stayed disabled until a radio. No amber “indistinguishable” banner (identity key includes current class, so they are not the same key). Admin can still pick the blank-number twin.
- `q=PIL-01` finds the rehearsal child. Add → “Student assigned.” Grade 5 B then had both twins.
- Periods screen lists the 12 seeded periods. Creating **Pilot extra** 16:00–16:45 succeeded.
- Timetable already had Mon–Fri Period 1 Arabic / Period 2 Quran Recitation from the seeder (instruction text: “Drag a subject onto a period cell”). A drag onto Wednesday Extra did not persist a new cell.
- Registers **Generate expected** for 2026-08-26: green *Created 0 expected registers.* Fill-rate cards already showed Fatimat Ali 1/1 (100%). Unfilled table: *No unfilled registers past their time* — today’s remaining slots are not listed there.

**Confusing:** AppShell is ~50 wrapping links, duplicate labels (Report cards, Awards). Blade counters (28 students / 3 teachers) are not “Grade 5 A”. Class teacher options are people from `teachers.user_id`; `UserSeeder` teacher role alone is not in that list.

**Twice:** year name left in the create form; term dates on every card; class/year chosen again on timetable and registers.

<img src="/opt/cursor/artifacts/pilot-round2/10-admin-blade-dashboard.webp" alt="Blade admin dashboard after login; Inertia Years/Today not in this nav" />

<img src="/opt/cursor/artifacts/pilot-round2/11-years-page.webp" alt="Inertia years: overflowing AppShell including Log out; closed 2026-2027 and active Pilot" />

<img src="/opt/cursor/artifacts/pilot-round2/12-year-activate-blocked.webp" alt="Cannot activate Extra while Pilot is already active" />

<img src="/opt/cursor/artifacts/pilot-round2/13-classes-page.webp" alt="Create class includes class teacher select; Grade 5 A has Fatimat Ali; Grade 5 B has none" />

<img src="/opt/cursor/artifacts/pilot-round2/14-class-duplicate-error.webp" alt="Duplicate Grade 5 B is an Ignition unique constraint 500" />

<img src="/opt/cursor/artifacts/pilot-round2/17-search-fatima-results.webp" alt="Fatima search: two rows, radio required, PIL-01 vs blank number" />

<img src="/opt/cursor/artifacts/pilot-round2/21-period-created.webp" alt="Periods CRUD with Pilot extra created" />

<img src="/opt/cursor/artifacts/pilot-round2/23-timetable-grade5a.webp" alt="Timetable Grade 5 A already filled Mon–Fri from seeder" />

<img src="/opt/cursor/artifacts/pilot-round2/25-registers-generated.webp" alt="Generate expected created 0 registers for 2026-08-26" />

---

### Round 2 — Step 2 — Teacher: Today, topic, attendance, submit

**Worked?** **Yes**, on the seeded Grade 5 A Period 1 Arabic register (already generated). Not a cold empty-Today: admin generate created 0; the seeder timetable + an earlier generate in this same browser session had already produced `lesson_logs`.

**Clicks / screens (~6):** AppShell Log out → `teacher@akuru.edu.mv` → Blade dashboard → `/en/academics/registers/today` → View/Fill register → topic + statuses + Submit.

**What the browser showed**

- Today listed **one** card: Arabic Language · Grade 5 A · Period 1 · 07:45 · SUBMITTED. Period 2 Quran Recitation for the same Wednesday was **not** on Today for this teacher (different `teachers` row).
- Fill grid: **name only**. Two Mariyam Ali, two Ahmed Naseem, no PIL / DOB / NID.
- Topic dropdown included “Sun and moon letters”. Taught summary still a separate box (same title typed again).
- Fatima Yoosuf **excused** after the note (see Step 4). Aminath Rishfa **late** / 10. Others present. Submit succeeded.
- AppShell shows **Ustadh Mohamed Ali** and **Log out**.

**Confusing:** attendance still offers `excused` on the teacher grid. Nav overflow.

**Twice:** topic title vs “What was taught”.

<img src="/opt/cursor/artifacts/pilot-round2/30-teacher-registers-today.webp" alt="Teacher Today: one submitted Arabic Period 1 card and AppShell Log out" />

<img src="/opt/cursor/artifacts/pilot-round2/31-teacher-register-1-submitted.webp" alt="Register grid: Fatima excused, Aminath late 10, duplicate names, no numbers" />

---

### Round 2 — Step 3 — Parent notification

**Worked?** **No UI.** Absence SMS is still not a fake and not visible in the portal.

**Clicks / screens (~3):** parent login → Blade page titled **Admin Dashboard** (Hassan Ahmed) → portal children / absence notes. There is no “parent notified” / SMS log / in-app attendance alert.

Parent landing on a screen labelled **Admin Dashboard** (Quick Actions: View Quran Progress; nav Dashboard / Hifz / Website) is the same Blade hide-the-loop problem as staff, with a worse label.

Opening a teacher register URL as parent is **403 Forbidden** (`/en/academics/registers/1`). That is authorization, not a missing page.

<img src="/opt/cursor/artifacts/pilot-round2/33-parent-landing-admin-dashboard.webp" alt="Parent Hassan Ahmed lands on Blade Admin Dashboard" />

<img src="/opt/cursor/artifacts/pilot-round2/37-parent-no-sms-notification-ui.webp" alt="No SMS or absence notification surface in the parent portal" />

---

### Round 2 — Step 4 — Parent note → teacher approve → excused

**Worked?** **Yes in the browser.**

**Clicks / screens (~8):** parent `/en/portal/absence-notes` (child Fatima, date **empty by default**, type illness, reason Fever) → Submit → AppShell Log out → teacher `/en/academics/absence-notes` → Approve → open register → Fatima **excused**.

**Confusing:** date not defaulted to today; parent AppShell is the same 50-link staff nav. Review notes vs Absence notes vs portal Absence notes.

**Twice:** parent reason; optional teacher review notes.

DB after the walk: `absence_notes` id 1 approved; `class_attendance` Fatima `excused`, Aminath `late`.

<img src="/opt/cursor/artifacts/pilot-round2/34-parent-absence-notes.webp" alt="Parent absence notes: Fatima, empty date, approved Fever row" />

<img src="/opt/cursor/artifacts/pilot-round2/32-teacher-absence-notes-approved.webp" alt="Teacher review: Fatima 2026-08-26 illness Fever APPROVED" />

---

### Round 2 — Step 5 — Admin exam, marks, term grade, report card

**Worked?** Exam schedule → marks_entry → 15/15 marks → review → published **yes**. Term % / grade / rank **blank**. Download is **HTML**, not PDF. Weights screen did not produce a usable year scheme in this walk (`assessment_weight_schemes` count 0).

**Clicks / screens (~12):** `/en/exams/schedule` (year/term/class/subject/type/name/date/times + three confirm checkboxes) → Move through statuses → Marks (per-row Save / blur) → Gradebook Load + Recompute → Report templates (needed for generate) → Report cards Generate → Download.

**What broke**

- Schedule form defaults wander (Extra / Term 2 / Arabic Beginners) while the real exam is Pilot / Grade 5 A. Easy to schedule the wrong class.
- Marks grid **does** show PIL numbers (`Ahmed Naseem PIL-09` / `PIL-10`). The attendance grid still does not.
- Gradebook Term 1 / Grade 5 A / Arabic: exam column filled (Fatima 70, duplicates 76/77 and 78/79); **Term % / Grade / Rank all —**.
- Report cards: 15 rows status `ready`, Download links. Publish control defaulted to **Term 2** while the table is Term 1. Files under `storage/app/private/documents/report_card/` are `.html`. Fatima’s card: Arabic Language %/grade/GPA/rank **empty**.
- Weights UI is a JSON blob of type ids → 0. No scheme saved in this walk.

**Confusing / twice:** class/term/subject re-selected on schedule, marks, gradebook, templates, report cards.

<img src="/opt/cursor/artifacts/pilot-round2/38-admin-exam-schedule-published.webp" alt="Exams: Term 1 Arabic Final Grade 5 A published" />

<img src="/opt/cursor/artifacts/pilot-round2/39-admin-exam-marks-two-ahmed-naseem.webp" alt="Marks: 15/15 entered, two Ahmed Naseem with PIL numbers" />

<img src="/opt/cursor/artifacts/pilot-round2/40-admin-gradebook-term-percent-columns.webp" alt="Gradebook exam marks filled; Term percent grade rank blank" />

<img src="/opt/cursor/artifacts/pilot-round2/41-admin-report-cards-list-download.webp" alt="Report cards ready with Download; Term 2 publish control vs Term 1 table" />

<TextReference path="/opt/cursor/artifacts/pilot-round2/fatima-yoosuf-report-card.html" start={16} end={40} alt="Fatima report card HTML: empty grade cells"></TextReference>

---

### Round 2 — Step 6 — Admin invoice, parent portal

**Worked?** **Yes for the parent.** Admin invoice **list UI is easy to miss.**

**Clicks / screens (~5):** `/en/finance/invoices` (year tabs, hardcoded 2026-01-01..2026-03-31, Generate drafts, Issue drafts) → parent `/en/portal/invoices`. Pay now not used (BML).

**What broke / confused**

- After issue, switching year tab to Extra (`academic_year_id=5`) shows **No draft invoices** and an empty structure select — looks like generate failed. Pilot year actually has **45 sent** invoices (15 × 3 months). Duplicate Extra tabs again.
- Parent portal for Fatima: `INV-2-14-2026-01` … `-03`, due 2026-01-05 / 02-05 / 03-05, balance 1500.00, **Pay now**. Dates are the form defaults, not “this term”.

**Twice:** year on fee structures and invoices; amount on catalog and structure.

<img src="/opt/cursor/artifacts/pilot-round2/43-admin-finance-invoices-draft.webp" alt="Admin invoices on Extra year: no draft invoices, default Jan–Mar 2026 dates" />

<img src="/opt/cursor/artifacts/pilot-round2/35-parent-invoices.webp" alt="Parent Fees: three Fatima invoices 1500.00 with Pay now" />

---

### Round 2 — Ranked: what still blocks a real teacher

Round 1 ranked list above is **not** rewritten. This is the list **after** #79–#84, from the Chrome walk.

1. **Cannot log in on staging** with seed passwords; this agent still cannot SSH or seed `test.akuru.edu.mv`.
2. **Blade dashboard after login still hides the academic loop.** Years / Today / Registers / Exams / Invoices are Inertia AppShell only. Parent login is titled **Admin Dashboard**. A teacher next week who follows the first screen never reaches Today.
3. **`DatabaseSeeder` alone is not a school.** 0 students, 0 `teachers` rows, 0 years. Class teacher select is empty; picker has no one to search; People → Students cannot create a child. `PilotRehearsalSeeder` (or equivalent) is still an operator step. Role `teacher` on `users` is not a `teachers` row.
4. **AppShell nav is still unusable as navigation** (50+ wrapping links, duplicate labels). Logout **is** there (POST next to the name) — Round 1 item 7 is fixed as a control, not as information architecture. `GET /logout` remains 405.
5. **Duplicate year names and duplicate class 500.** Extra can be created twice. Creating Grade 5 B again dumps Ignition instead of “already exists.” Grade 5 B in this walk never got a class teacher in the UI.
6. **Wrong child is still easy.** Picker shows number/DOB/NID (Round 1 item 9 is fixed) but two Fatimas share name+NID+DOB and are **not** flagged indistinguishable because class differs. Register **fill grid still has no number/DOB** (two Mariyam Ali, two Ahmed Naseem). Marks grid *does* show PIL numbers.
7. **Today / generate is still easy to read as broken.** Admin “Created 0 expected registers” when rows already exist; Unfilled hides today’s remaining periods until they are late; a class teacher only sees **their** subject cards (one Wednesday slot here, not the whole day).
8. **No parent notification** of absence. SMS binding is still live Dhiraagu/HTTP, not a fake; portal has no “notified” signal.
9. **Term grades stay blank** unless Weights is actually saved. Report cards generate HTML with empty %/grade/rank. Queue worker required. “PDF” is not PDF.
10. **Invoice admin UI can show an empty table** on the wrong year tab while the parent already has three 1500.00 invoices. Generate dates are hardcoded Jan–Mar 2026.

Items 1–3 still stop a cold staff user before attendance. Item 4 no longer blocks *switching users* on Inertia screens (logout works) but still blocks *finding* Today. Items 5–6 are first-week identity risk. Items 7–10 break generate confidence, parent comms, reports, and fees.

Hifz untouched. Deploy 3 not executed. Track B not started. No product fixes in this slice.

---

## Round 3 — how this walk was done (2026-08-26)

Local `akuru_institute`, `migrate:fresh --seed` (DatabaseSeeder now includes `PilotRehearsalSeeder` on #87). Stacked PRs **#86–#92** (not all merged to `main` at walk time). Vite production build (`public/hot` removed). Chrome as admin → teacher → parent → teacher → admin.

PRs in this round (one per item):

| Item | PR |
|------|-----|
| 1 SMS safety | [#86](https://github.com/ampilarey/akuru/pull/86) |
| 2 Seeder school | [#87](https://github.com/ampilarey/akuru/pull/87) |
| 3 Role landings | [#88](https://github.com/ampilarey/akuru/pull/88) |
| 4 Term grades / HTML label | [#89](https://github.com/ampilarey/akuru/pull/89) |
| 5 Fill-grid identity | [#90](https://github.com/ampilarey/akuru/pull/90) |
| 6 Generate / duplicates / invoices | [#91](https://github.com/ampilarey/akuru/pull/91) |
| 7 DoD browser walk | [#92](https://github.com/ampilarey/akuru/pull/92) |

Not in this round: AppShell nav IA (Round 2 item 4), staging access (Round 2 item 1).

---

### Round 3 — Step 1 — Admin year / class / roster

**Worked?** Class teacher names **yes**. Duplicate Extra / Grade 5 B **no 500**. First Chrome pass: uniqueness errors were **session-only** (forms spun and cleared with no red text). #91 follow-up commit shows `yearForm.errors.name` / `form.errors.name` on the create forms.

**Clicks / screens:** login → Blade **Admin Dashboard** (admin is allowed this) → `/en/academics/years` duplicate Extra → `/en/academics/classes` (Grade 5 A **Fatimat Ali**) → Grade 5 A search Fatima.

**What the browser showed**

- Years: duplicate Extra did not Ignition; also did not show a message until the form-error follow-up.
- Classes: Grade 5 A has a class teacher; Grade 5 B still **—**.
- Fatima search: two Fatima Yoosuf rows, same NID `A999001` and DOB `2010-03-12`; one **PIL-01**, one **no number**; current class Grade 5 B on the listing. Number differs, so they are not the “class-only twins” case.

<img src="/opt/cursor/artifacts/pilot-round3/01-admin-dashboard-landing.webp" alt="Admin Blade dashboard after login" />

<img src="/opt/cursor/artifacts/pilot-round3/02-years-duplicate-silent-fail.webp" alt="Duplicate year submit with no visible error on first pass" />

<img src="/opt/cursor/artifacts/pilot-round3/03-classes-teacher-names-populated.webp" alt="Classes: Grade 5 A class teacher Fatimat Ali" />

<img src="/opt/cursor/artifacts/pilot-round3/04-grade5a-fatima-picker-duplicate-identity.webp" alt="Fatima picker: two rows, number vs blank, same NID and DOB" />

<img src="/opt/cursor/artifacts/pilot-round3/05-duplicate-class-silent-fail.webp" alt="Duplicate Grade 5 B submit with no visible error on first pass" />

---

### Round 3 — Step 2 — Teacher Today and fill grid

**Worked?** **Yes.** Teacher login lands on **Today’s registers**. Fill grid shows **Number** and **Date of birth**.

**Clicks / screens:** logout → `teacher@akuru.edu.mv` → Today (no Blade teacher dashboard) → open register.

**Confusing:** generate “already exist” flash was not re-clicked; Wednesday already had a generated card from the seed/session.

<img src="/opt/cursor/artifacts/pilot-round3/step2-teacher-today-landing.webp" alt="Teacher lands on Today registers" />

<img src="/opt/cursor/artifacts/pilot-round3/step2-teacher-register-grid-number-dob.webp" alt="Register grid with Number PIL-* and Date of birth columns" />

---

### Round 3 — Step 3 — Parent landing and notified

**Worked?** **Yes** for landing title. Portal attendance has a **Parent notified** column.

**Clicks / screens:** `parent@akuru.edu.mv` → Parent Dashboard → `/en/portal/attendance`.

**What the browser showed:** heading **Parent Dashboard** (not Admin Dashboard). Attendance table includes Parent notified; this walk’s visible row was **—** (excused / no SMS receipt for that row).

<img src="/opt/cursor/artifacts/pilot-round3/step3-parent-dashboard-title.webp" alt="Parent Dashboard title after login" />

<img src="/opt/cursor/artifacts/pilot-round3/step3-parent-attendance-notified.webp" alt="Portal attendance Parent notified column" />

---

### Round 3 — Step 4 — Absence note

**Worked?** **Yes.** Parent submitted illness note 2026-08-27; teacher approved.

<img src="/opt/cursor/artifacts/pilot-round3/step4-parent-submit-absence-note.webp" alt="Parent absence note submitted" />

<img src="/opt/cursor/artifacts/pilot-round3/step4-teacher-approve-absence-note.webp" alt="Teacher approved absence note" />

---

### Round 3 — Step 5 — Term grades and report cards

**Worked?** Message and honest HTML label **yes**. Term % / grade / rank still **—** until Weights is saved (now explained). `/en/academics/gradebook` is 404; the real path is `/en/exams/gradebook`.

<img src="/opt/cursor/artifacts/pilot-round3/step5-gradebook-missing-weights.webp" alt="Gradebook amber missing-weights message; Term percent blank" />

<img src="/opt/cursor/artifacts/pilot-round3/step5-weights-page.webp" alt="Weights page Resolved scheme none" />

<img src="/opt/cursor/artifacts/pilot-round3/step5-report-cards-html-message.webp" alt="Report cards are HTML documents not PDF" />

<img src="/opt/cursor/artifacts/pilot-round3/step5-report-cards-download-html-links.webp" alt="Download HTML links on report cards" />

---

### Round 3 — Step 6 — Invoices

**Worked?** **Yes** on the Pilot year tab: sent invoices listed (INV-2-14-2026-01 …), status **sent**, not an empty drafts-only table. Periods 2026-01..03 match Term 1 dates from the seed (not a leftover hardcoded form default in this walk).

<img src="/opt/cursor/artifacts/pilot-round3/step6-invoices-pilot-year-data.webp" alt="Invoice admin Pilot year with sent rows and status" />

---

### Round 3 — Ranked: what still blocks a real teacher

Round 1 and Round 2 ranked lists above are **not** rewritten.

1. **Staging login** still operator-only (unchanged).
2. **AppShell nav IA** still 50+ links (explicitly out of this round).
3. **Uniqueness errors must be visible on the form.** First Chrome pass hid them; #91 follow-up paints the message. Seed still inserts duplicate Extra year names (seeder bypasses validation).
4. **Weights still have to be saved** or term % stays blank — now with a banner, not silence.
5. **Parent notified is a column**, but an excused row shows **—**; absence SMS is log-only outside production (#86). A teacher still cannot *see* the SMS body in the portal.
6. **Wrong-URL 404:** `/academics/gradebook` vs `/exams/gradebook`. Nav still the only map.
7. **Class teacher on Grade 5 B** still **—** in this seed/UI.

Teacher Today, parent title, fill-grid number/DOB, invoice list, HTML-not-PDF, and default seed school are **fixed in the stacked PRs** and were used in Chrome.

Hifz untouched. Deploy 3 not executed. Track B not started.

---

## Out of scope (not done)

AppShell nav redesign and staging credentials were not this round. No Hifz behavior change. Deploy 3 not executed. Track B not started.
