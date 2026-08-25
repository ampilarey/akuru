# ADR-011: Class attendance modes and parent notification

## Context

S2.7 introduces `class_attendance` as the single writer-owned table for
class-level presence. Schools need either a per-lesson grid (EduPage loop)
or a once-a-day homeroom mark. Parents should be told when a child is
absent, without a burst of SMS if the teacher corrects the same day.

ADR-004 already exists (prayer times / SMS safety). This record is the
attendance-mode decision the S2 spec called “ADR-004”.

## Decision

1. **Modes** (setting `attendance_mode`, default `per_lesson`):
   - `per_lesson` — register grid writes one row per student per period
     (`period_id` set). Daily homeroom is disabled.
   - `daily` — homeroom writes one row per student per date (`period_id`
     null). Register entry does not write attendance.
2. **Writer contract:** every insert/update of `class_attendance` goes
   through `AttendanceWriterInterface::record(StudentAttendanceDTO)`.
   The register grid, daily screen, CSV import, and a future device are
   all callers of that contract.
3. **Notification** (setting `attendance_notify`, default `absent_only`):
   `StudentMarkedAbsent` fires for `absent` (and for `late` when the
   setting is `absent_and_late`). Excused is quiet. Throttle: one SMS
   per student per calendar day (cache until end of day, Indian/Maldives).
4. **Uniqueness:** `(student_id, date, period_key)` where the writer
   stores `period_key = period_id ?? 0` so daily mode has one row per
   student/day (MySQL unique does not treat NULL period_id as equal).

## Consequences

- Absence-note approval (S2.8) must flip rows via the same writer.
- Offering-session attendance (Phase 1B) stays on a different table.
- Changing mode mid-year does not rewrite history; new marks follow the
  current setting.
