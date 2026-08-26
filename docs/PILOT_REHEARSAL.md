# Pilot rehearsal findings

**Date:** 2026-08-26  
**Slice:** end-to-end walk of one class. No product fixes in this slice.  
**Code:** `cursor/pilot-rehearsal-063c` (HEAD of this PR)  
**Intended host:** `https://test.akuru.edu.mv`  
**Host actually walked:** local MySQL `akuru_institute` at `http://127.0.0.1:8000` (same codebase). Staging was probed and **could not be seeded or logged into**.

This is a findings document. A step that cannot be completed is recorded as failed, not worked around in the product.

Operator seed (after `DatabaseSeeder`):

```bash
php artisan db:seed --class=PilotRehearsalSeeder
```

---

## How this walk was done

1. Staging HTTP: `/en` and `/up` return 200. Seed login `admin@akuru.edu.mv` / `password` POSTs to `/en/login` and **302s back to `/en/login`**. No SSH from this environment (`docs/STAGING.md`: webhook pull only). The seeder was **not** run on staging.
2. Local: `PilotRehearsalSeeder` on `akuru_institute`. Browser walk of login + academic years. Domain actions for register → attendance → absence note → exam → report card → invoices against the seeded Grade 5 A class.
3. ADR-021 identity mess is in the seeder (duplicate NID `A999001`, blank/`N/A`/`0`/`-`, two Mariyam Ali, two Ahmed Naseem 2009-04-04, Hussain Shareef with no guardian).

Logins after the seeder (password `password`): `admin@akuru.edu.mv`, `teacher@akuru.edu.mv`, `parent@akuru.edu.mv` (Fatima Yoosuf / PIL-01).

---

## Staging (step 0) — cannot start the rehearsal there

| | |
|---|---|
| Worked? | **No** |
| Screens | 1 (login), then bounce |
| What broke | Seed credentials do not authenticate. This matches the 2026-08-23 credential smoke in `STATUS.md`. |
| Confusing | Public site looks healthy, so it is easy to assume staff logins work. |
| Twice | n/a |

Until an operator seeds `PilotRehearsalSeeder` on the server **and** creates verified `user_contacts` (or uses real staging passwords), a teacher cannot use `test.akuru.edu.mv` next week.

---

## Seeded scenario (local)

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

## Step 1 — Admin creates year / term / class and assigns class teacher

**Worked?** Partial for year/term/class. **Class teacher assignment cannot be completed in the UI.**

**Clicks / screens (browser):** login → Blade dashboard → somehow reach `/en/academics/years` (not in the Blade top nav) → create year → add term → Activate (blocked) → `/en/academics/classes` (not walked to completion in the browser; form inspected in code).

After password login the admin lands on the **Blade** dashboard (`/en/dashboard`), not Inertia `AppShell`. Blade nav is Dashboard / Enrollments / Students / Teachers / Hifz / Quran / More. The new Years / Classes / Today / Exams / Finance links live on Inertia `AppShell` only. A teacher next week who follows “what I see after login” never reaches the class-register loop.

Blade dashboard on first paint (before the institute seed finished) showed **13 students / 1 teacher** — the legacy Blade counters, not Grade 5 A.

<img src="/opt/cursor/artifacts/pilot-rehearsal/00-login-page.png" alt="Blade login: email/phone/ID, Admin Login with OTP, copyright 2025" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/01-admin-dashboard.png" alt="Blade admin dashboard after login; Inertia academic screens are not in this nav" />

**Years UI** (`resources/js/Pages/Academics/Years/Index.jsx` + `AppShell.jsx`):

- Header is a wrapping wall of 50+ links (Learn, Teach, Years, Today, Exams, Payroll, …). Duplicate labels (“Report cards”, “Awards”).
- Create-year form has name + two unlabelled date inputs. `useForm` includes `description` but there is **no description field**.
- Add-term form on every card defaults to **“Term 1”** even when Term 1 already exists. Dates are unlabelled `mm/dd/yyyy`.
- Year names are not unique: after this walk the database has two rows named `2026-2027 Extra`.
- Activate on a second year shows: *“Another academic year is already active. Close it before activating this one.”* That is correct, but Close + Activate is two trips and easy to miss.

<img src="/opt/cursor/artifacts/pilot-rehearsal/02-years-page-nav-overflow.png" alt="Inertia Academic years: overflowing AppShell nav, closed 2026-2027 plus active Pilot" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/04-term-created-shared-state.png" alt="Success flash Term created; Add term still prefills Term 1/Term 2 across every year card" />

<img src="/opt/cursor/artifacts/pilot-rehearsal/05-activate-year-error.png" alt="Cannot activate Extra while Pilot is already active" />

**Classes UI** (`Academics/Classes/Index.jsx`): create fields are name, section, level, capacity. Backend `ClassDirectoryController@store` accepts `class_teacher_id`; the form **does not send it**. The listing table is Name / Section / Capacity — no teacher column. Show page is a raw **“Student ID”** number box, not a name picker.

**Periods:** no Inertia/Blade admin screen. Timetable builder options come from `periods`. Empty unless `PeriodSeeder` (or SQL) has run. Teacher cannot create periods.

**Double entry:** year name `2026-2027` is typed on the year and again mentally against the seeded `2026-2027 Pilot`. Term dates are re-entered on every card because one shared `termForm` instance is reused for all years.

---

## Step 2 — Teacher: today’s register, plan topic, attendance

**Worked?** **Not as a teacher-only user, from a cold start.** The write path works if an admin generates registers first and the login has a `teachers` row.

**Clicks / screens if unblocked:** Today → Fill register → pick topic → grid → Submit (~4 screens). Cold start is more.

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

## Step 3 — Parent notification (`SmsSenderInterface`)

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

## Step 4 — Parent absence note → teacher approve → excused

**Worked?** **Yes, at the action layer.** Portal and review screens exist; parent needs a `parent_guardians.user_id` link (`ListGuardianChildrenAction`). Default `parent@akuru.edu.mv` has no such row until the seeder.

**Clicks / screens:** portal Absence notes (submit) → logout → teacher Review notes → Approve (~4–5 screens). No deep link from the SMS (there is no SMS UI).

**UI** (`Portal/AbsenceNotes.jsx`): child, date, type, reason. Backend accepts `attachment` and `period_id`; the form has **neither**. `affects_attendance` is hardcoded true in the form payload.

**Review** (`Academics/AbsenceNotes/Index.jsx`): teacher **does** have `manage_attendance`, so approve is allowed. After approve, Fatima’s row flipped `absent` → `excused` with `absence_note_id` set.

**Confusing:** parent must type today’s date; it is not defaulted. Two Mariyam Ali / Ahmed Naseem would be indistinguishable in a sibling dropdown (Fatima is unique here). Teacher must find Review notes in the overflowing Inertia nav; it is not on the Blade dashboard.

**Twice:** reason is typed by the parent; teacher can type review notes. Attendance date must match the note date or excuse matching misses (see `ApproveAbsenceNoteAction`).

---

## Step 5 — Admin exam, marks, term grade, report card PDF

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

## Step 6 — Admin invoice from fee structure, parent portal

**Worked?** **Yes**, on the seeded structure.

**Clicks / screens:** Fee items (catalog) → Fee structures (already seeded here) → Invoices generate drafts → Issue drafts → parent Fees. ~4 screens if the structure exists; more if you must create fee items first. Default seed has **no** fee items.

**What happened (actions, 2026-08-26):** `GenerateInvoicesAction` for 2026-01-01..2026-03-31, `per_month` → **45** drafts (15 students × 3 months), `INV-2-14-2026-01`, total `1500.00`. Issue → status `sent`. Parent portal for Fatima: **3** invoices.

**Confusing:** invoice generate dates default in the React form to `2026-01-01` / `2026-03-31` (hardcoded in `Finance/Invoices/Index.jsx`), not “this term”. Drafts are listed as drafts until Issue; portal `ListPortalInvoicesAction` shows all statuses. Pay now goes to BML — not exercised (sandbox, no real payment).

**Twice:** year picked on fee structures and again on invoices. Amount 1500 is on the catalog item and again on the structure line.

---

## Ranked: what would block a real teacher next week

1. **Cannot log in on staging** with documented seed passwords; this agent cannot seed staging. Operator credentials are required.
2. **Cannot log in locally/staging with `DatabaseSeeder` users** until each has a **verified `user_contacts` email (or mobile)**. `users.email` is not enough.
3. **No `teachers` row** → Today is empty with “No teacher profile is linked to this login.” Creating the user with role `teacher` is not enough.
4. **Registers for today do not exist** until someone with `registers.manage` generates them. The teacher cannot do that. Today does not explain why the list is empty.
5. **No periods in default seed, and no periods UI** → timetable cannot be built → generate creates nothing.
6. **After login, Blade dashboard hides the loop.** Years / Today / Plans / Review notes are Inertia `AppShell` only, and that nav is unusable on a laptop screen.
7. **Class teacher cannot be assigned in the class UI.** Backend field exists; form omits it. Seeder/tinker only.
8. **Roster add is a numeric student id.** Fifteen children cannot be enrolled from the class screen without looking up ids elsewhere (Blade student create is another app).
9. **Duplicate names on the register** (two Mariyam Ali, two Ahmed Naseem) with no number/DOB — wrong child is a real risk.
10. **Absence SMS is not a fake** on staging/local; demo-mode log is unreachable with current Dhiraagu config; no in-app “parent notified” signal.
11. **Term grades stay blank** unless Weights are set for the year. Report cards then print empty %/grade/rank.
12. **“PDF” is HTML**, queued. Without a worker and a template, generate fails or never becomes downloadable.

Items 1–6 stop the teacher before attendance is saved. Items 7–9 make the first week error-prone even after an operator seeds. Items 10–12 break parent comms and reports.

---

## Out of scope (not done)

No product fixes. No Hifz behavior change. Deploy 3 not executed. Track B not started.
