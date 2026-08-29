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
  PreviewConflicts, Backfill, Sync. F1 and F3 both need that Action.
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

## Wave 1 — the daily-habit core

### F1. Status-tile portal home — ~1–2 weeks
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
  F3 reuses it for homework due-dating.
- `tomorrow` payload → the top strip.
- Prayer tile reuses the existing provider — the one tile EduPage has no answer
  to, so it belongs above the fold.
- Tiles render only when the module applies to the viewer.
- Teacher FAB: new message / new homework (deep links into F2/F3).

**Open decision (must answer before starting):** `ComposePortalHomeAction` is
student/parent only; teachers redirect to `portal.overview`, a separate composer.
Does F1 cover teachers too (extend the action, one grid for everyone), or ship
student/parent-only now and fold teachers in with the app shell? Student/parent-
only is the smaller slice and matches where the data already is.

**Acceptance:** a parent opening `/portal` sees tomorrow's periods for their
child and ≥6 tiles with correct live counts; every count matches its module's
own page; Dhivehi UI; RTL-safe. Arch: Portal composes via other domains'
contracts/actions only.

### F2. Messaging — ~2–3 weeks (was 2; the reply/permission model is real work)
**Reference:** EduPage Messages — the #1 parent habit.
**Build (Notifications domain owns it):**
- Additive migration: `message_threads` (subject, created_by, context type/id
  nullable — morph aliases registered) and `message_participants` (thread,
  person, role, last_read_at); existing `messages` gains nullable `thread_id`.
- Actions: `StartThreadAction`, `ReplyAction`, `MarkThreadReadAction`,
  `ListInboxAction` (unread-first, badge feeds F1).
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
  removes the most common reason to reach for the forms builder (F5).
- "Important" flag; mark handled/hide; delete own sent message; block a sender.
- Attachments via Media, size-capped. Unread nudge after 24 h through the
  existing `SmsSenderInterface`/mail, respecting current consents.
- Inertia pages: inbox, thread, compose. Unread badge in the shell header.

**Explicitly out of scope:** real-time Chat. EduPage ships it as a separate
product surface; treat it the same way.

**Acceptance:** parent messages a teacher and gets the reply in-thread; teacher
composes to a class and each family sees only its own thread; a school-wide send
defaults to no reply-all; a poll records one response per participant and shows
results; unread count on the F1 tile matches; CSV export of a teacher's sent log.

### F3. Homework for students and parents — ~1–2 weeks
**Reference:** EduPage HW/exams — second most-opened screen.
**Build (Academics owns data; Portal renders):**
- **No new teacher entry UI** — the register/lesson-log flow they already use
  stays the single source. EduPage agrees: homework is assigned from the lesson
  in the class register.
- Additive columns on `lesson_logs`: `homework_due_date` (nullable date),
  `homework_attachments` (nullable json of Media ids).
- **Due date defaults to the class's next lesson**, not tomorrow — reuse F1's
  `ListDayTimetableForStudentAction`. EduPage's picker highlights days that
  class actually meets; copy that, it prevents due dates on days with no lesson.
- `ListHomeworkForStudentAction`: lesson logs with non-empty homework for the
  student's class, windowed by date, joined with subject/teacher, due-soon first.
- `homework_ticks` (lesson_log_id, student_id, ticked_at) — pupil self-marks
  done. EduPage does exactly this for text homework (question-homework infers it
  from answers); self-marking is invisible to grading.
- Portal page + F1 tile; exam dates merge into the same list (EduPage shows HW
  and exams together).
- **Defer to a later slice:** follow-up questions, per-question points, file
  return with annotation. Those are a materials/assessment engine, not this.

**Acceptance:** teacher fills homework in the register as today (plus optional
due date defaulting to the next lesson); the pupil sees it under the right
subject with the due date; ticking done persists; parent sees the same list
read-only; tile count correct.

### F4. Noticeboard feed — ~1 week
**Reference:** EduPage Noticeboard.
**Build (Portal domain, `notice_posts`):** author_id, title, body, photos (Media
ids json), audience (`all|class:<id>|role:<name>`), `academic_year_id`,
published_at, pinned.
- **Owner decision 3 is answered by the reference:** in EduPage *any teacher*
  may post to their own class (or to colleagues); office posts school-wide.
  Recommend the same — permission `notices.manage` for school-wide, own-class
  posting for teachers.
- Portal feed newest-first, pinned on top, photos in a lightbox; F1 tile.
- Keep separate from public news — that is the website's voice; this is private
  to enrolled families. Link from admin nav.

**Acceptance:** a teacher posts to their own class and only that class's families
see it; an office `all` post reaches everyone; Dhivehi + RTL; CSV export.

---

## Wave 2 — office workflow

### F5. Requests & approvals — ~2 weeks *(promoted; was buried inside "forms")*
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

### F6. Sign-up actions & surveys — ~2 weeks
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
  (Note EduPage explicitly blocks confirming while signed in as the pupil; F7's
  switcher must preserve that distinction.)
- **Payment handoff** — a sign-up with a fee creates invoices through the
  existing Commerce/Finance actions rather than growing its own money code
  (rule 11: one wallet/invoice system). Money rule 12 still applies: access
  depends on BML webhook confirmation.
- Builder UI (`forms.manage`), fill UI in portal, results table + CSV, count
  tile on F1. Simple polls belong in F2's message poll, not here.

**Acceptance:** staff build a 4-field trip sign-up targeted at one class with a
fee; parents submit; a pupil submission stays unconfirmed until the guardian
confirms from a parent account; invoices are raised through Finance; closed
forms reject submissions; anonymous surveys store no person id.

### F7. Account switcher + role-routing fix — ~1 week
**Reference:** EduPage multi-account sidebar.
**Build (Identity):**
- **Fix the ordering bug first, separately:** `DashboardController::index()`
  checks `isTeacher()` before `isParent()`, so a teacher-parent can never reach
  the parent home. That is a live defect and should not wait for switching.
- `linked_accounts` (user_id, linked_user_id, verified_at) created by signing
  into the second account once; switching swaps the session between
  *verified-linked* accounts. Re-auth still required for sensitive actions.
  Linking needs both sets of credentials; unlink any time; audit logged.
- Preserve the F6 rule: acting as a pupil must not grant guardian confirmation.

**Acceptance:** a teacher-parent switches in two taps and the portal re-renders
in the other identity; admin routes still demand the right role; unlinking kills
the shortcut; the routing fix has its own test.

### F8. Student pick-up — ~1 week *(was ~3 days; it is a protocol, not a button)*
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

## Wave 3 — decide need first

- **F9. Attendance policy depth** (~1 wk) — custom absence types, tardy→absence
  conversion (EduPage: e.g. 3 tardies = 1 lesson), rounding policy, and an
  integrity story for parent-submitted absence notes.
- **F10. Internal calendar + room booking** (~1–2 wks) — staff calendar, custom
  event types, school holidays, classroom changes, message event participants.
- **F11. Consultation slots** (~1 wk) — parent-teacher meeting booking with
  configurable gaps, on top of the existing `/portal/meetings`.
- **F12. Staff-absence → substitution automation** (~3 days) — Akuru has both
  halves already; EduPage wires them together. Cheap, high leverage. Overlaps F5.
- **F13. Physical library / textbook lending** (~2 wks) — titles + copies, QR
  labels, lend/return. **Note this is not Akuru's L-track library**, which is a
  digital reader/bookstore. Build only if the Institute lends physical books.
- **F14. Lost and found** (~3 days), **canteen**, **competences**, **student
  work showcase**, **gate arrivals/departures** — owner-gated; the last needs a
  hardware decision.

---

## Sequencing

```
F1 home tiles ──▶ ships first; every later feature adds a tile
                  (also builds the timetable read Action F3 needs)
F2 messaging  ──▶ independent; feeds F1 badge; absorbs simple polls
F3 homework   ──▶ needs F1's timetable Action for next-lesson due dates
F4 noticeboard──▶ independent; feeds F1
F5 requests   ──▶ after Wave 1; chains into existing substitutions
F6 sign-ups   ──▶ after F5; uses Finance for fees
F7 switcher   ──▶ routing-bug fix is urgent and separable; switching anytime
F8 pick-up    ──▶ owner-gated
F9–F14        ──▶ owner-gated
```
The app-shell slice can land before or after Wave 1; F1 is its home tab either
way.

## Owner decisions

1. **F1: does the tile grid cover teachers, or student/parent only for now?**
   (Blocks F1 — nothing else does.)
2. F8: does the physical setup make pick-up meaningful? If yes, security pattern
   is mandatory.
3. F13: does the Institute lend physical books or textbooks?
4. F14: canteen, lost & found, gate tracking — does the Institute run these?
5. Is the aSc timetable desktop tool still in use? If so, build import/export
   rather than a drag-drop builder.

*(Revision 1's decisions on class-thread shape and homework entry point are now
answered above — per-family threads, and the register stays the single entry
point, both matching EduPage.)*
