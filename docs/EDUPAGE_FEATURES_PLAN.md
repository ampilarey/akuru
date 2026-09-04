# EduPage-Inspired Features — Implementation Plan

Status: **PLAN ONLY.** Builds on `docs/EDUPAGE_PARITY.md` (revision 2 — the
vendor-documentation crawl, not just screenshots). This turns the gap list into
slices a developer can build one PR at a time under the CLAUDE.md rules: one
slice per PR, tests ship with the slice, domain boundaries, `academic_year_id`
on time-scoped tables (rule 10), morph-map registration (ADR-005), trilingual +
RTL, CSV export on every listing, Inertia+React for new UI.

Verified in this codebase (2026-08-29):
- `Notifications\Models\Message` — sender/recipient/subject/content/attachments/
  priority/read state/soft-delete-per-side. Single-recipient rows, no threads.
- `Academics\Models\LessonLog` — **already has `homework`**, plus teacher/
  subject/classroom/year/term/timetable/period/date. Teachers write homework
  today; students have no list view of it.
- `Academics\Models\Timetable` — class/subject/teacher/year/term/period/room/
  day_of_week/start/end, conflict detection. **No read Action exists for "this
  class's lessons on day X"** — the Actions directory has only Save, Copy,
  PreviewConflicts, Backfill, Sync. E1 and E3 both need that Action.
- `Portal\Actions\ComposePortalHomeAction` (D1) — already returns per-student
  attendance summary, last 8 attendance rows, 8 exam results, invoices **with
  `invoice_balance` precomputed**, course performance and hifz summaries, plus a
  `sections` list of key/label/href. Student/parent only; teachers land on
  `portal.overview`.
- Substitutions, exams, gradebook, attendance registers, payments: shipped.

Where a design choice below matches EduPage's, that is noted — the incumbent
solved these against real schools, and copying a well-worn default is cheaper
than rediscovering it.

---

## Audit against the codebase (2026-09-04) — read this before planning work

Revision 1 of this plan was written from EduPage's documentation with only a
**partial** code audit: `Message`, `LessonLog`, `Timetable` and
`ComposePortalHomeAction` were checked, everything else was inferred. That was
wrong for **8 of 22 slices**, found when E9 — advertised here as "the cheapest
win in the plan, ~3 days" — turned out to be shipped end to end.

**Already built. Do not plan these as new work:**

| Slice | Evidence in this codebase |
|---|---|
| E4 Noticeboard | `Academics\Models\Announcement`, `AnnouncementController`, 4 routes; surfaced to families via `EnhancedDashboardController::getRecentAnnouncements()` |
| E5 Requests & approvals | `requests` table (type/requester/regarding morph/payload/status/reviewed_by/at/notes), `SchoolRequest`, 5 types in `SchoolRequestType`, `ReviewSchoolRequestAction`, `RequestHandlerRegistry` with pluggable per-type handlers, `requests.submit`/`requests.review` permissions, index/store/review/export routes |
| E9 Absence → substitution | `HandleStaffLeaveApprovalAction` calls HR `ApproveStaffLeaveAction` then `RecordApprovedTeacherLeaveAction`, which creates the `TeacherAbsence` **and** `firstOrCreate`s a `SubstitutionRequest` per affected timetable period, honouring `valid_from`/`valid_until` |
| E11 Room booking | `room_bookings` table, `RoomBookingController`, 4 routes (the calendar half — see below) |
| E12 Consultation slots | `meeting_slots` + `meeting_bookings`, `MeetingSlotController`, 10 routes |
| E14 Certificates | `report_cards`, `report_card_templates`, `report_card_comments` |
| E20 Competences | `competencies` + `competency_assessments`, `CompetencyController`, 5 routes |
| E22 Notification centre | `UserNotification`, `NotificationTemplate`, `Device`, `NotificationController`, 3 routes |

**Verified still missing** (checked in both directions — synonyms searched, and
three false "found" results discarded as grep artefacts where the words appeared
incidentally in `documents`, `emergency_contacts` and `terms`):
E1, E2, E3, E6, E7, E8, E10, E13, E15, E16, E17, E18, E21.

**Two findings that change design, not just status:**

1. **`assignments` + `assignment_submissions` tables exist** — the legacy module
   whose unreachable dashboard code was deleted in #184. E3 below assumes
   `LessonLog.homework` is the only homework concept in the system. It is not.
   Which of the two E3 should extend is an open design question.
2. **E11 is half-built**: `room_bookings` ships, but `CalendarDay` covers only
   holiday/exam day types (used by `GenerateExpectedRegistersAction`). The
   staff-facing calendar with custom event types and school holidays is the
   remaining part.

Slice sections below are kept for their design detail — much of it still applies
to finishing a UI over data that already exists — but **the effort figures on
the eight built slices are void**.

---

## Wave 1 — the daily-habit core

### E1. Status-tile portal home — ~1–2 weeks
**Reference:** EduPage home = tomorrow-timetable strip + tile grid with live
status + role identity + quick-action FAB. Note that EduPage's homework digest
independently converges on the same idea ("what is due tomorrow"), so *tomorrow*
is the organising concept, not decoration.

**Build:**
- `sections` becomes `tiles`: each gains `badge` (count) and `status` (line of
  text). Most counts come from data the action **already loads** — unpaid
  invoices from the existing `invoices`/`invoice_balance`, attendance flags from
  `attendance_summary`, new results from `exams`. No new queries for those.
- New `ListDayTimetableForStudentAction` in Academics (class → periods for a
  given date, with substitution overlays). This is the genuinely new read.
  E3 reuses it for homework due-dating.
- `tomorrow` payload → the top strip.
- Prayer tile reuses the existing provider — the one tile EduPage has no answer
  to, so it belongs above the fold.
- Tiles render only when the module applies to the viewer.
- Teacher FAB: new message / new homework (deep links into E2/E3).

**Open decision (must answer before starting):** `ComposePortalHomeAction` is
student/parent only; teachers redirect to `portal.overview`, a separate composer.
Does E1 cover teachers too (extend the action, one grid for everyone), or ship
student/parent-only now and fold teachers in with the app shell? Student/parent-
only is the smaller slice and matches where the data already is.

**Acceptance:** a parent opening `/portal` sees tomorrow's periods for their
child and ≥6 tiles with correct live counts; every count matches its module's
own page; Dhivehi UI; RTL-safe. Arch: Portal composes via other domains'
contracts/actions only.

### E2. Messaging — ~2–3 weeks (was 2; the reply/permission model is real work)
**Reference:** EduPage Messages — the #1 parent habit.
**Build (Notifications domain owns it):**
- Additive migration: `message_threads` (subject, created_by, context type/id
  nullable — morph aliases registered) and `message_participants` (thread,
  person, role, last_read_at); existing `messages` gains nullable `thread_id`.
- Actions: `StartThreadAction`, `ReplyAction`, `MarkThreadReadAction`,
  `ListInboxAction` (unread-first, badge feeds E1).
- Class compose fans out **one thread per family** — parents never see each
  other. (EduPage reaches the same privacy outcome via reply policy; per-family
  threads get there more simply.)
- **Reply policy per thread**, copying EduPage's defaults because they are
  battle-tested: `all` / `author_only` / `none`, and **reply-all is off by
  default for school-wide or all-parents sends**. Without this, one broadcast
  to 300 families becomes 300 reply-alls.
- **Saved recipient groups** (user-defined) alongside derived ones (class,
  course, year). EduPage users lean on these constantly.
- **Poll on a message** — options + results, shown inline. Cheap here, and it
  removes the most common reason to reach for the forms builder (E5).
- "Important" flag; mark handled/hide; delete own sent message; block a sender.
- Attachments via Media, size-capped. Unread nudge after 24 h through the
  existing `SmsSenderInterface`/mail, respecting current consents.
- Inertia pages: inbox, thread, compose. Unread badge in the shell header.

**Explicitly out of scope:** real-time Chat. EduPage ships it as a separate
product surface; treat it the same way.

**Acceptance:** parent messages a teacher and gets the reply in-thread; teacher
composes to a class and each family sees only its own thread; a school-wide send
defaults to no reply-all; a poll records one response per participant and shows
results; unread count on the E1 tile matches; CSV export of a teacher's sent log.

### E3. Homework for students and parents — ~1–2 weeks
**Reference:** EduPage HW/exams — second most-opened screen.
**Build (Academics owns data; Portal renders):**
- **No new teacher entry UI** — the register/lesson-log flow they already use
  stays the single source. EduPage agrees: homework is assigned from the lesson
  in the class register.
- Additive columns on `lesson_logs`: `homework_due_date` (nullable date),
  `homework_attachments` (nullable json of Media ids).
- **Due date defaults to the class's next lesson**, not tomorrow — reuse E1's
  `ListDayTimetableForStudentAction`. EduPage's picker highlights days that
  class actually meets; copy that, it prevents due dates on days with no lesson.
- `ListHomeworkForStudentAction`: lesson logs with non-empty homework for the
  student's class, windowed by date, joined with subject/teacher, due-soon first.
- `homework_ticks` (lesson_log_id, student_id, ticked_at) — pupil self-marks
  done. EduPage does exactly this for text homework (question-homework infers it
  from answers); self-marking is invisible to grading.
- Portal page + E1 tile; exam dates merge into the same list (EduPage shows HW
  and exams together).
- **Defer to a later slice:** follow-up questions, per-question points, file
  return with annotation. Those are a materials/assessment engine, not this.

**Acceptance:** teacher fills homework in the register as today (plus optional
due date defaulting to the next lesson); the pupil sees it under the right
subject with the due date; ticking done persists; parent sees the same list
read-only; tile count correct.

### E4. Noticeboard feed — ~1 week
**⚠ ALREADY BUILT** — `AnnouncementController` + 4 routes, surfaced to families in the portal dashboard. Remaining gap is at most a dedicated feed page; verify before planning.
**Reference:** EduPage Noticeboard.
**Build (Portal domain, `notice_posts`):** author_id, title, body, photos (Media
ids json), audience (`all|class:<id>|role:<name>`), `academic_year_id`,
published_at, pinned.
- **Owner decision 3 is answered by the reference:** in EduPage *any teacher*
  may post to their own class (or to colleagues); office posts school-wide.
  Recommend the same — permission `notices.manage` for school-wide, own-class
  posting for teachers.
- Portal feed newest-first, pinned on top, photos in a lightbox; E1 tile.
- Keep separate from public news — that is the website's voice; this is private
  to enrolled families. Link from admin nav.

**Acceptance:** a teacher posts to their own class and only that class's families
see it; an office `all` post reaches everyone; Dhivehi + RTL; CSV export.

---

## Wave 2 — office workflow

### E5. Requests & approvals — ~2 weeks *(promoted; was buried inside "forms")*
**⚠ ALREADY BUILT** — the generic requests engine ships with 5 types, review action, pluggable handlers, permissions and routes. Remaining gap is at most extra request types and a parent-facing UI.
**Reference:** EduPage Requests/Applications — an approval engine, not a form
builder. This was mis-scoped in revision 1.
**Build (new `Domains/Requests`):**
- `request_types` (key, label, target audience, **approver rule** — class
  teacher / headmaster / named permission, form schema json),
  `requests` (type, requester person, subject person nullable, answers json,
  status, `academic_year_id`), `request_events` (append-only audit of
  submit/approve/reject/comment).
- Starter types: pupil leave of absence, lesson exemption, staff leave, sickness
  notification.
- **The valuable part is the chaining:** an approved staff-leave request emits
  an event that offers to create the matching `TeacherAbsence`, which the
  existing substitution engine already consumes. Cross-domain via Events and
  Actions only — Requests must not import Academics models.
- Notify requester and approver at each transition through existing channels.

**Acceptance:** a parent files a leave request, the class teacher approves it,
both are notified, the audit trail is complete; an approved staff-leave request
offers a `TeacherAbsence` and the substitution flow picks it up; rejected
requests state a reason; CSV export.

### E6. Sign-up actions & surveys — ~2 weeks
**Reference:** EduPage Sign-up module — richer than "a form builder".
**Build (new `Domains/Forms`):**
- `forms` (title, description, fields json, audience, opens/closes at,
  `academic_year_id`, anonymous flag, **requires_parent_confirmation**),
  `form_responses` (form, person nullable if anonymous, answers json,
  submitted_at, **confirmed_by_guardian_at**).
- Field types v1: text, textarea, select, multi-select, yes/no, date, file. No
  conditional logic in v1.
- **Parent confirmation** — a pupil's choice can require a guardian to confirm
  from their own account; results show confirmed/unconfirmed distinctly.
  (Note EduPage explicitly blocks confirming while signed in as the pupil; E7's
  switcher must preserve that distinction.)
- **Payment handoff** — a sign-up with a fee creates invoices through the
  existing Commerce/Finance actions rather than growing its own money code
  (rule 11: one wallet/invoice system). Money rule 12 still applies: access
  depends on BML webhook confirmation.
- Builder UI (`forms.manage`), fill UI in portal, results table + CSV, count
  tile on E1. Simple polls belong in E2's message poll, not here.

**Acceptance:** staff build a 4-field trip sign-up targeted at one class with a
fee; parents submit; a pupil submission stays unconfirmed until the guardian
confirms from a parent account; invoices are raised through Finance; closed
forms reject submissions; anonymous surveys store no person id.

### E7. Account switcher + role-routing fix — ~1 week
**Reference:** EduPage multi-account sidebar.
**Build (Identity):**
- **Fix the ordering bug first, separately:** `DashboardController::index()`
  checks `isTeacher()` before `isParent()`, so a teacher-parent can never reach
  the parent home. That is a live defect and should not wait for switching.
- `linked_accounts` (user_id, linked_user_id, verified_at) created by signing
  into the second account once; switching swaps the session between
  *verified-linked* accounts. Re-auth still required for sensitive actions.
  Linking needs both sets of credentials; unlink any time; audit logged.
- Preserve the E6 rule: acting as a pupil must not grant guardian confirmation.

**Acceptance:** a teacher-parent switches in two taps and the portal re-renders
in the other identity; admin routes still demand the right role; unlinking kills
the shortcut; the routing fix has its own test.

### E8. Student pick-up — ~1 week *(was ~3 days; it is a protocol, not a button)*
**Reference:** EduPage's module is a two-way state machine with its own auth
factor. Revision 1 badly under-scoped this.
**Build (Academics):** `pickup_notices` (student, guardian, requested_at,
status, `academic_year_id`) with the documented flow:
1. staff activate pick-up for the day;
2. guardian sends a request **gated by a security pattern/PIN they set** — a
   second factor, because this releases a child;
3. request means "arriving in ~10 minutes"; staff are notified and move the
   child to reception;
4. guardian is notified when the child is sent;
5. guardian confirms "I have the child", closing the loop.
Staff console lists *Waiting for departure* and *Left*, auto-expiring daily.

**Owner decision:** does the Institute's physical setup (island, building, gate)
make this meaningful at all? If yes, the security pattern is not optional — do
not ship steps 1–5 without step 2.

---

## Wave 3 — depth on things Akuru already has

These are cheap because the hard part exists; they close the gap between "we
have a gradebook" and "we have what they were used to".

### E9. Staff absence → substitution automation — ~3 days
**⚠ ALREADY BUILT, END TO END.** Approving a staff-leave request already creates the `TeacherAbsence` and generates `SubstitutionRequest` rows per affected period, idempotently. Nothing to do.
Akuru has staff attendance (HR) **and** the substitution engine; EduPage's value
is that they are wired together. An approved absence emits an event that offers
to create the `TeacherAbsence` the substitution flow already consumes. Highest
leverage-to-effort ratio in the whole plan. Overlaps E5 — build with it if E5
lands first, standalone otherwise.

### E10. Attendance policy depth — ~1 week
Custom absence types; **tardy→absence conversion** (EduPage's example: 3 tardies
= 1 lesson) as a configurable rule; rounding policy for part-lessons; tardy and
early-departure summaries; a "who is absent today" staff view. Plus an integrity
story for parent-submitted absence notes — EduPage documents this explicitly
("can't they be falsified?"), and Akuru's `/portal/absence-notes` should answer
the same question before parents lean on it.

### E11. Internal calendar + room booking — ~1–2 weeks
**⚠ HALF BUILT** — `room_bookings` + `RoomBookingController` ship. Missing: the staff calendar with custom event types and school holidays (`CalendarDay` covers only holiday/exam types today).
Staff-facing calendar distinct from the public events site: custom event types,
whole-day events, school holidays for the year, **classroom change / room
booking**, teachers' meetings, message all participants of an event, day/week
views. `academic_year_id` per rule 10.

### E12. Consultation slots — ~1 week
**⚠ ALREADY BUILT** — `meeting_slots` + `meeting_bookings`, `MeetingSlotController`, 10 routes.
Parent-teacher meeting booking on top of the existing `/portal/meetings`:
teacher publishes slots, configurable gap between consultations, parent books
one, both notified. EduPage also lets teachers book each other — include it,
it is the same table.

### E13. Class register + materials depth — ~2 weeks
EduPage's "Plans, preparations, standards" (333 doc pages) is a reusable
materials library that homework and tests draw from. Akuru has lesson logs and
teacher plan views (D3) but no library. Scope v1 narrowly: a teacher's reusable
material (title, body, attachments, subject, tags) that can be attached to a
lesson log or a homework assignment. Do **not** build an assessment engine here.

### E14. Certificates / report printing — ~1 week
**⚠ ALREADY BUILT** — `report_cards`, `report_card_templates`, `report_card_comments` all ship.
Report cards exist (C2); EduPage's depth is in printing — templates, grade
categories, subject areas, verbal evaluation alongside marks. Scope: a printable
report-card template with the Institute's own layout, not EduPage's per-country
template set.

---

## Wave 4 — new capability, each owner-gated

None of these should start without a "yes, we run this" from the Institute.

### E15. Lost and found — ~3 days
`found_items` (title, description, photo, found_at, location, holder staff id,
status `listed|returned`). Staff log an item; families browse; notify on
important items. Trivial, and genuinely liked in EduPage.

### E16. Physical library + textbook lending — ~2 weeks
**Not Akuru's L-track library**, which is a digital reader/bookstore — this is
physical circulation: `book_titles` + `book_copies` (accession number, QR or
barcode label), `loans` (copy, borrower person, out/due/returned dates),
printable label sheets, a borrower QR card, availability lookup, and bulk
borrow/return for textbook issue at term start. Build only if the Institute
lends physical books. Name it distinctly (Circulation) so it never gets confused
with the L-track Library in code or nav.

### E17. Interest groups / school clubs — ~1 week
Clubs as first-class offerings: club course, roster, plan, registration
(reuses E6), printable attendance sheet, and support for members from outside
the enrolled roll. The course engine can model these already — this is mostly
configuration plus a roster screen, so keep it thin and resist a parallel
enrolment system (rule 11).

### E18. Student arrivals / departures — ~1 week + hardware decision
Gate check-in/out with parent visibility: `student_movements` (student,
direction, at, recorded_by, `academic_year_id`). EduPage integrates access
control hardware; card/QR readers are a separate purchase. Do not build the
software until the hardware question is answered, or it will be a manual log
nobody fills.

### E19. Student sensitive information — ~1 week + privacy decision
Health and welfare notes on a pupil, visible only to authorised staff. Needs a
policy decision before a schema: who may read, who may write, retention, and
whether it is exportable. This is the one module where building first and
deciding later is actively wrong.

### E20. Competences / values tracking — ~2 weeks
**⚠ ALREADY BUILT** — `competencies` + `competency_assessments`, `CompetencyController`, 5 routes. The "defer until a curriculum names the list" advice below is void.
A generic "skill or value awarded to a pupil" record. Defer until a curriculum
need names the competence list — Quran milestones already cover the flagship
subject, and an unnamed framework becomes an empty screen.

### E21. Student work showcase — ~1–2 weeks
Photograph paper work, route it to the right parent. EduPage uses AI to read the
pupil's handwritten name; a v1 without that is just "photo + pick the pupil",
which is most of the value at a fraction of the cost. Note the failure mode
their docs document — work sent to the wrong parent — and make reassignment easy.

### E22. Notification centre — ~1 week
**⚠ ALREADY BUILT** — `UserNotification`, `NotificationTemplate`, `Device`, `NotificationController`. The daily-digest idea may still be a genuine addition; verify first.
A per-user notification list with scope control: which categories reach me, on
which channel, and a "mark done" state. EduPage additionally offers a **daily
digest instead of per-event pings, which also lists what is due tomorrow** —
copy that; it is the single most parent-friendly idea in their notification
design, and it pairs with E1's tomorrow strip and E3's homework list.

---

## Not planned (with reasons)

- **Chat** — real-time messaging is a separate product surface; E2's threads
  cover the need. Revisit only if families ask for it specifically.
- **Canteen** (51 doc pages) — only if the Institute runs one.
- **Kindergarten** (174) — not applicable.
- **AI material generation, essay correction, paper-test grading by camera** —
  EduPage's AI bets. Akuru's AI investment is the recitation module, which is
  differentiation EduPage cannot match; spreading thin across generic AI
  features would trade a defensible advantage for a commodity one.
- **Reports / Pedagogical documentation / Final exam printing / New school year
  / Podporné opatrenia** (165 pages combined) — statutory outputs shaped by the
  Slovak curriculum. The Maldivian equivalents differ; build what the Ministry
  actually asks for, not these.
- **aSc timetable builder** — keep an import/export path instead of rebuilding a
  drag-drop editor with decades of work behind it.

---

## Coverage — all 41 EduPage modules accounted for

So nothing is silently dropped. ✅ = Akuru already at parity or better.

| EduPage module | Akuru disposition |
|---|---|
| Messages and communication | E2 |
| Chat | Not planned (see above) |
| Noticeboard | E4 |
| Notifications | E22 |
| Sign up module / Surveys | E6 |
| Requests | E5 |
| Electronic Applications | ✅ Admissions |
| Student pick up from school | E8 |
| Lost and found | E15 |
| Payments | ✅ better (BML, wallet, gift cards, refunds) |
| Canteen | Not planned |
| Home work and exams | E3 (+ E13 for materials) |
| Class register | ✅ registers; depth in E13 |
| Plans, preparations, standards | E13 |
| TimeTables | ✅ data model; import/export path, not a builder |
| Substitutions / cover | ✅ |
| Interest groups | E17 |
| Library (physical lending) | E16 |
| Textbook storage | E16 |
| EduPage AI | Not planned — recitation module instead |
| Grades | ✅ gradebook + weights + report cards |
| Attendance of students | ✅ registers; depth in E10 |
| Attendance of teachers | ✅ HR; wiring in E9 |
| Arrivals / departures | E18 |
| Certificates | E14 |
| Competences | E20 |
| Calendar and events | E11 |
| Parent-teacher meetings | E12 |
| Student work showcase | E21 |
| Student sensitive information | E19 |
| User accounts / User rights | ✅ Spatie roles + permissions |
| Multi-account switcher | E7 (plus the routing bug it exposed) |
| Webpage | ✅ better — full CMS, news, courses, L-track library |
| Mobile application | Capacitor scaffold (Phase 5); app shell slice |
| Overview about your school | ✅ `portal.overview` + admin dashboards |
| Basic school data / Create EduPage | ✅ settings + seeders |
| Reports / Pedagogical documentation | Not planned — statutory mismatch |
| New school year | ✅ academic year backbone (rule 10) |
| Final exam printing | Not planned — statutory mismatch |
| Podporné opatrenia | Not applicable (Slovak special-education measures) |
| Kindergarten | Not applicable |

**Akuru-only, no EduPage equivalent:** recitation practice module, digital
reader + bookstore + writer payouts, prayer times, Dhivehi/Arabic RTL UI,
Hifz/Quran engine.

---

## Sequencing

Post-audit, only 13 slices remain. Built ones are struck from the order.

```
E1 home tiles ──▶ ships first; every later feature adds a tile
                  (also builds the timetable read Action E3 needs)
E2 messaging  ──▶ independent; feeds E1 badge; absorbs simple polls
E3 homework   ──▶ needs E1's timetable Action for next-lesson due dates;
                  FIRST settle whether it extends lesson_logs.homework or
                  the existing assignments table (audit finding 1)
E6 sign-ups   ──▶ uses Finance for fees; E5's request engine already exists
                  and may cover part of it — check before building
E7 switcher   ──▶ the role-routing bug is separable and still open
E10 attendance depth, E13 materials ──▶ any order, no cross-dependencies
E8, E15–E19, E21 ──▶ owner-gated (see below)
E11 (calendar half only) ──▶ room booking already ships
```
The app-shell slice can land before or after E1; E1 is its home tab either way.

## Owner decisions

**Blocking now:**

1. **E1: does the tile grid cover teachers, or student/parent only?** Teachers
   currently land on `portal.overview`, a separate composer. Student/parent-only
   is the smaller slice and matches where the data already is. *Nothing else in
   the plan is blocked.*

**Needed before their own slice starts:**

2. E8 pick-up: does the physical setup (island, building, gate) make this
   meaningful? If yes, the parent security pattern is mandatory, not optional.
3. E16: does the Institute lend physical books or issue textbooks?
4. E17: does it run clubs / interest groups?
5. E18 gate tracking: is card/QR hardware wanted? Do not build the software
   first.
6. E19 sensitive information: who may read and write pupil health/welfare notes,
   and what is the retention rule? Policy before schema on this one. (Note
   `emergency_contacts` and the custom-field tables already carry some of this.)
7. E15 lost & found / canteen: does the Institute run these services?
8. Is the aSc timetable desktop tool still in use? If so, build import/export
   rather than a drag-drop builder.
9. **E3: extend `lesson_logs.homework`, or the existing `assignments` /
   `assignment_submissions` module?** Raised by the audit; the plan below was
   written unaware the second existed.

*(E20's "is there a named framework?" question is withdrawn — competences are
built. Revision 1's questions on class-thread shape and homework entry point
were answered from the reference: per-family threads, register as the single
entry point.)*

## Rough totals — post-audit

The pre-audit figure of ~24–29 weeks counted eight slices that were already
shipped. Remaining, on verified ground:

- **Genuinely missing:** E1, E2, E3, E6, E7, E8, E10, E13, E15, E16, E17, E18,
  E21 — 13 slices, most owner-gated.
- **The family-facing core is now just E1 + E2 + E3** (tiles, message threads,
  homework list) ≈ **4–6 weeks**. E4, E9 and E22 were in that line and are done.
- Everything else is either built, half-built (E11's calendar), or gated on a
  decision the Institute has not made.

Do not quote a total from this document without re-checking the slice against
the codebase first. That is exactly how the first version went wrong.
