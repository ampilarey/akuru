# Phase D — Portal composition (D1–D3)

Parent/student and admin overview composition. Engine core stays subject-ignorant. Hifz frozen (read via `StudentHifzSummaryReader` only; no Hifz dashboard change).

## D1 — Composed parent/student home (done, #109)

One Inertia page at `/portal/home` (`portal.home`) assembling:

- class attendance (Academics `ListClassAttendanceAction`)
- published exam results (ExamsGrades `ListPublishedExamResultsForStudentsAction`)
- invoices (Finance `ListPortalInvoicesAction`)
- course progress (Courses `ListStudentPerformanceReportAction`)
- Hifz (Support contract `StudentHifzSummaryReader`, bound to Hifz `ListStudentHifzSummariesAction`)

Portal **new files** import Actions/Contracts only — no other domain `Models`, and no `App\Domains\Hifz\` string (rule 5). Architecture baselines must not grow.

Parent and student `/dashboard` redirect here. CSV export. AppShell **Home** link.

## D2 — Parent-teacher meeting slot booking (done, #110)

Time-scoped tables `meeting_slots` + `meeting_bookings` (`academic_year_id` required, `term_id` optional). Morph aliases `meeting_slot` / `meeting_booking`. Permission `meetings.manage`.

- Admin `/academics/meetings`: generate a window of slots (teacher, optional class, date, start/end, minutes), publish, CSV, cancel/remove.
- Portal `/portal/meetings`: published upcoming slots the family is eligible for; book/cancel; CSV. Linked from portal home. AppShell **Meetings** (staff).
- Capacity 1 by default; overlapping teacher times rejected; a student cannot hold two overlapping bookings.
- Class-scoped slots require the child on that class roster.
- Portal files import Academics/People Actions only.

## D3 — Admin overview: unfilled registers, ungraded exams, plan adherence (this slice)

Staff Inertia page at `/portal/overview` (`portal.overview`) composed from existing Actions — no new tables, no new Blade, no `course_type` branch, no Hifz:

- unfilled past-time registers (Academics `ListUnfilledRegistersAction`)
- per-teacher fill rates (`fillRates`)
- plan adherence (`planAdherence`)
- ungraded exams: `marks_entry` with `exam_date` before today (ExamsGrades `ListExamsAction::ungraded`)

Year filter + CSV of all four sections. Admin/headmaster `/dashboard` redirects here. AppShell **Overview**. Portal new files import Actions only.
