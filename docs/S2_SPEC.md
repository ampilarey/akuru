# S2 Spec — Attendance, Timetable + Class Register

**Phase:** S2 (after S1; requires class_student pivot, terms, staff_profiles)
**Domains:** Academics (primary), Notifications, Portal, Website (calendar read)
**Repo head start:** `timetables`, `periods`, `lesson_logs` (with plan_topic_id, taught_summary, homework, counts), `course_plans`/`plan_topics`, `absence_notes` (full approval flow + attachment), `teacher_absences` + substitutions already exist as tables/stubs. S2 wires them into the EduPage-style loop: **timetable slot → register entry → topic from teaching plan → per-lesson attendance → parent notified.**

---

## Slice S2.1 — Rooms + Timetable v2

**New `rooms`:** `id, name (+_arabic/_dhivehi), building nullable, capacity nullable, type enum(classroom, lab, hall, online, other), bookable bool default true, active bool, timestamps`

**`timetables` (alter):**
| Change | Detail |
|---|---|
| Add `academic_year_id` FK | backfill all existing → active year |
| Add `term_id` FK nullable | null = whole year |
| Add `period_id` FK nullable | period-based entries; keep `start_time/end_time` for time-based — exactly one of (period_id) or (start_time+end_time) required (validation rule) |
| Add `room_id` FK nullable | migrate matching `room` strings → rooms rows; keep legacy string columns until cleanup |
| Add `valid_from`/`valid_until` dates nullable | mid-term timetable changes without deleting history |

**Conflict engine — `TimetableConflictChecker` service (pure, unit-tested):** given a proposed entry, returns conflicts: (a) teacher double-booked, (b) room double-booked, (c) class double-booked — same day + overlapping time/period within validity window and year. Used by builder UI (live warning) and by `SaveTimetableEntryAction` (hard block unless `allow_conflict=false` permission override with reason, logged).

**Builder UI (React):** week grid per class; drag-drop subject/teacher into slots; live conflict badges; teacher-view and room-view tabs; copy-week / copy-from-class; print/export. Substitution overlay: approved teacher absences show affected slots with assigned substitute.

## Slice S2.2 — School Calendar

**New `calendar_days`:** `id, academic_year_id FK, date, type enum(holiday, event, exam_day, closure, special_schedule), title, affects_timetable bool default true, event_id FK nullable (links existing Website events), notes, timestamps, unique(date, academic_year_id)`

Rules: register generation (S2.3) skips dates where `affects_timetable=true`; exam scheduling (S3) checks clashes here. Admin CRUD + year-at-a-glance React screen. Portal + public website read holidays.

## Slice S2.3 — Class Register (the core loop)

**`course_plans` (alter):** replace string `academic_year` with `academic_year_id` FK (backfill by name match); add `term_id` nullable; add `CopyPlanAction` (to parallel class / next year — resets is_completed).

**`lesson_logs` (alter):**
| Change | Detail |
|---|---|
| Add `academic_year_id`, `term_id` FKs | backbone rule |
| Add `timetable_id` FK nullable | which slot generated it |
| Add `status` enum(`expected, draft, submitted, locked`) default expected | lifecycle |
| Add `submitted_at`, `locked_at` | locking after N days (setting) prevents silent edits; admin can unlock with audit |

**Generation:** nightly + on-demand `GenerateExpectedRegistersAction`: for each timetable entry on a school day (calendar-aware), create an `expected` lesson_log if absent. Teacher's "Today" screen lists their expected registers.

**Register entry screen (React, mobile-first — teachers use phones):** one screen does the whole loop: pick plan topic (or free-text taught_summary), homework, materials, **inline attendance grid** for the class roster (S2.4), submit. Present/late/absent counts auto-computed. Marking topic taught sets `plan_topics.is_completed`.

**Admin oversight:** unfilled-register report (expected past their time, not submitted), per-teacher fill rate, plan-adherence (topics completed vs planned) — feeds the Portal admin dashboard.

## Slice S2.4 — Class Attendance

**New `class_attendance`:** `id, student_id FK, class_id FK, academic_year_id FK, term_id FK nullable, date, period_id FK nullable, lesson_log_id FK nullable, status enum(present, absent, late, excused, left_early), minutes_late nullable, source enum(register, daily, external, import) default register, marked_by FK users, absence_note_id FK nullable, remarks nullable, timestamps`
Unique: `(student_id, date, period_id)` with null-period = daily mode (one row/day). Two modes per school setting: **per-lesson** (rows created from register grid) or **daily** (homeroom marks once).

**External-source contract (HR S5 + future devices reuse):** `AttendanceWriterInterface::record(StudentAttendanceDTO)` — the register grid, a CSV import, and a future biometric device are all just writers. Arch test: nothing writes `class_attendance` except via the contract's implementing action.

**Absence notes integration:** approving an `absence_note` (existing flow) with `affects_attendance=true` flips matching absent rows → excused and links `absence_note_id`. Parent submits notes from Portal with attachment (existing schema supports it).

**Parent notification:** `StudentMarkedAbsent` event → Notifications listener → SMS via `SmsSenderInterface` (template trilingual; throttle: one message per student per day; setting: notify on absent only / absent+late; quiet for excused). Daily digest option for admins.

**Reports + exports (CSV standing rule):** per-student (term %, by status), per-class daily sheet, chronic-absence list (threshold setting), unexcused list.

## Slice S2.5 — Behavior Records

**New `behavior_records`:** `id, student_id FK, academic_year_id FK, term_id FK nullable, type enum(compliment, notice, warning, incident), category string (configurable list in Settings), description, points smallint nullable (+/−), date, recorded_by FK users, parent_visible bool default true, requires_followup bool, followup_notes nullable, timestamps`

Visible on student profile (new tab), Portal (parent_visible only), and summarized on S3 report cards. Permission `behavior.record` (teachers) / `behavior.manage` (admin edits/deletes with audit).

## Slice S2.6 — Requests & Approvals (generalizes substitutions)

**New `requests`:** `id, type enum(teacher_leave, parent_general, schedule_change, other), requester_id FK users, regarding_type/regarding_id nullable (student, class...), payload json (type-specific fields), reason text, status enum(pending, approved, rejected, cancelled), reviewed_by, reviewed_at, review_notes, timestamps`

Type handlers (registry, mini version of the component pattern): `teacher_leave` approval → creates `teacher_absences` rows (existing table) → substitution suggestions (existing logic) + timetable overlay. S5's leave balances later plug into the same handler (balance check on approve). Parent absence notes stay in their own table (already richer); the requests module covers everything else.

## Notifications added in S2
Absent/late SMS (parent), unfilled-register reminder (teacher, end of day), leave request decision (teacher), substitution assignment (substitute teacher), behavior incident with parent_visible (parent, optional setting).

## Tests (CI gates)
1. Conflict checker unit matrix: teacher/room/class × period-based/time-based × validity windows.
2. Register generation: calendar-aware (skips holiday), idempotent (no duplicates on re-run).
3. Attendance uniqueness + both modes; excused flip on absence-note approval; writer-contract arch test.
4. Notification throttle: second absent mark same day sends nothing.
5. Lock behavior: locked register rejects edits; admin unlock audited.
6. Leave approval creates teacher_absences + appears in timetable overlay.
7. Permission tests: parent sees only parent_visible behavior records, only own children's attendance.

## Definition of Done
- [ ] A teacher completes the full loop on a phone: open today's register → pick topic → mark attendance → submit → parent receives SMS for an absent student.
- [ ] Timetable builder live with conflict blocking; current real timetable entered for the active year.
- [ ] Unfilled-register and chronic-absence reports live; CSV exports on all listings.
- [ ] All S2 screens React; legacy timetable/announcement Blade screens removed.
- [ ] STATUS.md updated; ADR-004 (attendance modes + notification policy).

**Out of scope:** exam scheduling (S3), offering-session attendance (Phase 1B — separate table, shared Portal reporting), leave balances (S5). Event/elective seat-limited registration (ROADMAP §8.2) reuses `EnforceSeatLimitAction` from 1B.2.
