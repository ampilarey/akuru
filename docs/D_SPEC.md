# Phase D — Portal composition (D1–D3)

Parent/student and admin overview composition. Engine core stays subject-ignorant. Hifz frozen (read via `StudentHifzSummaryReader` only; no Hifz dashboard change).

## D1 — Composed parent/student home (this slice)

One Inertia page at `/portal/home` (`portal.home`) assembling:

- class attendance (Academics `ListClassAttendanceAction`)
- published exam results (ExamsGrades `ListPublishedExamResultsForStudentsAction`)
- invoices (Finance `ListPortalInvoicesAction`)
- course progress (Courses `ListStudentPerformanceReportAction`)
- Hifz (Support contract `StudentHifzSummaryReader`, bound to Hifz `ListStudentHifzSummariesAction`)

Portal **new files** import Actions/Contracts only — no other domain `Models`, and no `App\Domains\Hifz\` string (rule 5). Architecture baselines must not grow.

Parent and student `/dashboard` redirect here. CSV export. AppShell **Home** link.

## D2 — Parent-teacher meeting slot booking (next)

Time-scoped slot tables (`academic_year_id`, `term_id` where relevant). Admin publishes slots; parent books; CSV. Portal reads Academics/People contracts. No Hifz namespace in new Portal files.

## D3 — Admin overview: unfilled registers, ungraded classes, plan adherence (next)

Staff Inertia overview composed from Academics/ExamsGrades contracts. No new Blade. Listing CSV.
