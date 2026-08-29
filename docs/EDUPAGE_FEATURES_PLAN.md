# EduPage-Inspired Features — Implementation Plan

Status: **PLAN ONLY.** Builds on `docs/EDUPAGE_PARITY.md` (the remembered-expectations
benchmark from the Institute's former EduPage system). This document turns the gap
list into implementable slices a developer can build one PR at a time, following the
CLAUDE.md rules: one slice per PR, tests ship with the slice, domain boundaries,
`academic_year_id` on time-scoped tables (rule 10), morph-map registration (ADR-005),
trilingual strings + RTL, CSV export on every listing, Inertia+React for new UI.

Verified foundations this plan builds on (checked in code 2026-08-29):
- `Notifications\Models\Message` — sender/recipient/subject/content/attachments/
  priority/read state/soft-delete-per-side. Single-recipient rows, no threads.
- `Academics\Models\LessonLog` — **already has a `homework` field**, plus
  teacher/subject/classroom/year/term/timetable/period/date. Teachers write homework
  today; students have no list view of it.
- `Academics\Models\Timetable` — class/subject/teacher/year/term/period/room/
  day_of_week/start/end. Conflict detection exists.
- `Portal\Actions\ComposePortalHomeAction` (D1) — already composes the student/parent
  home payload.
- Substitutions, exams, gradebook, attendance registers, payments: shipped.

---

## Wave 1 — the daily-habit core (do these before/with the app shell)

### F1. Status-tile portal home ("the EduPage home screen") — ~1–2 weeks
**Reference:** EduPage home = tomorrow-timetable strip + tile grid where every tile
shows live status + role identity + quick-action FAB.
**Build:**
- Extend `ComposePortalHomeAction` to return a `tiles` array; each tile = key, route,
  badge count, status line (e.g. `messages: "2 new"`, `homework: "3 due"`,
  `payments: "1 unpaid invoice"`, `attendance`, `grades: "new result"`, plus
  Akuru-unique `prayer: next prayer + time` reusing the prayer provider).
- New `tomorrow` payload: next school day's periods for the student's class from
  `Timetable` (+ substitution overlays), rendered as the top strip.
- One Inertia page (`Portal/Home`) with the tile grid; tiles render only when the
  underlying module applies to the role (teacher sees register/substitution tiles;
  parent sees per-child tiles).
- Teacher FAB: new message / new homework (deep links into F2/F3 forms).
**Acceptance:** a parent opening `/portal` sees tomorrow's periods for their child
and ≥6 tiles with correct live counts; every count matches its module's own page;
Dhivehi UI; CSV n/a. Arch: Portal composes via other domains' contracts/actions only.

### F2. Messaging inbox — ~2 weeks
**Reference:** EduPage Messages/All inboxes: the #1 parent habit.
**Build (Notifications domain owns it):**
- Additive migration: `message_threads` (subject, created_by, context type/id
  nullable — morph aliases registered) and `message_participants` (thread, person,
  role, last_read_at); existing `messages` gains nullable `thread_id` (additive,
  old rows keep working).
- Actions: `StartThreadAction` (to a teacher, to a class's guardians, to office),
  `ReplyAction`, `MarkThreadReadAction`, `ListInboxAction` (unread-first, badge
  count feeds F1).
- Teacher → whole-class compose fans out one thread per family (EduPage behavior)
  or one broadcast thread with per-participant read state — pick per-family threads
  (simpler privacy story: parents never see each other).
- Attachments via Media domain, size-capped; SMS/email nudge for unread > 24 h
  through existing `SmsSenderInterface`/mail (respecting existing notification
  consents).
- Inertia pages: inbox list, thread view, compose. Unread badge in AppShell header.
**Acceptance:** parent messages a teacher and gets the reply in-thread; teacher
composes to a class and each family sees its own thread; unread count on F1 tile
matches; read/unread survives refresh; CSV export of a teacher's sent log.

### F3. Student/parent homework list — ~1 week
**Reference:** EduPage HW/exams.
**Build (Academics owns data; Portal renders):**
- No new writing UI for teachers — the register/lesson-log flow they already use
  stays the single source (rule: don't duplicate entry). Additive columns on
  `lesson_logs`: `homework_due_date` (nullable date), `homework_attachments`
  (nullable json via Media ids).
- `ListHomeworkForStudentAction`: lesson_logs with non-empty homework for the
  student's class, windowed by date, joined with subject/teacher; due-soon first.
- Optional per-student done-state: `homework_ticks` (lesson_log_id, student_id,
  ticked_at) — student self-tracking only, invisible to grading.
- Portal page + F1 tile ("3 due this week"); exam dates from the existing exams
  module merge into the same list view (EduPage shows HW and exams together).
**Acceptance:** teacher fills homework in the register as today (plus optional due
date); the student sees it listed under the right subject with due date; ticking
"done" persists; parent sees the same list read-only; tile count correct.

### F4. Noticeboard feed — ~1 week
**Reference:** EduPage Photos/Noticeboard.
**Build (Portal domain, `notice_posts`):**
- `notice_posts`: author_id, title, body, photos (Media ids json), audience
  (`all|class:<id>|role:<name>`), `academic_year_id`, published_at, pinned.
- Staff compose page (permission `notices.manage`); portal feed newest-first,
  pinned on top, photos in a lightbox; F1 tile shows "1 new notice".
- Reuse the public news system? No — that is the public website's voice; the
  noticeboard is for enrolled families (private, class-targeted). Keep separate
  but link from admin nav.
**Acceptance:** admin posts a class-targeted notice with two photos; only that
class's families see it; all families see an `all` notice; feed is Dhivehi-ready
and RTL-safe; CSV export of posts list for admin.

---

## Wave 2 — office and convenience parity

### F5. Forms & surveys builder — ~2 weeks
**Reference:** EduPage Registration/Surveys + Applications.
**Build (new `Domains/Forms`):**
- `forms` (title, description, fields json schema, audience, opens/closes at,
  `academic_year_id`, anonymous flag), `form_responses` (form, person nullable if
  anonymous, answers json, submitted_at).
- Field types v1: text, textarea, select, multi-select, yes/no, date, file
  (Media). No conditional logic in v1.
- Builder UI for staff (permission `forms.manage`), fill UI in portal, results
  table + **CSV export**, response-count tile feed into F1.
- Admissions keeps its dedicated flow; this is for everything else (trip consent,
  event RSVP, feedback surveys).
**Acceptance:** staff builds a 4-field survey targeted at one class, parents
submit, results export to CSV, closed forms reject submissions; anonymous survey
stores no person id.

### F6. Student pick-up notice — ~3 days
**Reference:** EduPage "Student pick up from school".
**Build (Academics):** `pickup_notices` (student_id, guardian_id, expected_at,
note, status sent/seen, `academic_year_id`). Parent taps "Picking up now/at time"
→ appears on a teacher/gate live list (auto-expires end of day). SMS fallback
optional via existing sender.
**Acceptance:** parent sends notice, staff list shows it within one refresh,
notice expires same day; only the student's own guardians can send.

### F7. Account switcher — ~1 week
**Reference:** EduPage multi-account sidebar (parent + own student + staff
accounts in one app).
**Build (Identity):** `linked_accounts` (user_id, linked_user_id, verified_at)
created by logging into the second account once ("add account"); switcher swaps
the authenticated session between *verified-linked* accounts without re-entering
passwords (re-auth on sensitive actions still applies). Guard rails: linking
requires both credentials; unlink anytime; audit log.
**Acceptance:** a teacher-parent links both accounts, switches in two taps, the
portal re-renders in the other identity, sensitive admin routes still demand the
right role; unlinking kills the shortcut.

---

## Wave 3 — physical-school features (decide need first)

### F8. Student arrivals/departures — ~1 week + owner decision
Gate check-in/out with parent visibility. Only worth building if the Institute
actually wants gate tracking; card/QR hardware is a separate purchase decision.
Data: `student_movements` (student, direction, at, recorded_by,
`academic_year_id`).

### F9. Competences / values tracking — ~2 weeks, defer
EduPage's competences map to a generic "skill/value awarded to student" record.
Defer until a curriculum need names the competence list (Quran milestones already
cover the flagship subject).

### F10. Canteen menu, lost & found, work showcase — small, defer
Build only if the Institute runs these services; each is a half-week slice on the
noticeboard/forms patterns.

---

## Sequencing and dependencies

```
F1 home tiles ──▶ ships first; every later feature adds a tile
F2 inbox      ──▶ independent; feeds F1 badge
F3 homework   ──▶ independent (reads existing lesson_logs); feeds F1
F4 noticeboard──▶ independent; feeds F1
F5 forms      ──▶ after Wave 1; F4 links to forms for RSVPs
F6 pickup     ──▶ anytime after F1
F7 switcher   ──▶ anytime; app-shell benefits most
F8–F10        ──▶ owner-gated
```
The app-shell slice (bottom tabs, app chrome — discussed separately) can land
before or after Wave 1; F1 is designed to be its home tab either way.

## Owner decisions before building

1. F2: per-family threads for class messages (recommended) vs broadcast thread?
2. F3: is a due-date column enough, or do teachers want homework separate from
   the register? (Plan says: register stays the single entry point.)
3. F4: who may post notices (office only, or every teacher to their class)?
4. F6/F8: does the Institute's physical setup (island, building, gate) make
   pick-up notices and arrivals tracking meaningful?
5. F7: is account linking wanted before the app shell, or with it?
