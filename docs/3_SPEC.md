# Phase 3 — Certificates and reports (C1–C3)

Course-engine Phase 3 (`docs/SPEC.md` §39 / §48). Parent/student **composed dashboard** is Phase D, not this file.

Engine core stays subject-ignorant. No `if (course_type === …)`. Hifz frozen until Phase F.

## C1 — Course certificates (done, #106)

**Tables**

- `certificate_templates` — catalog (not time-scoped). Trilingual name, `kind`, optional `course_id`, JSON `rules`, `body_html`, `active`.
- `issued_certificates` — time-scoped: `academic_year_id` required, `term_id` optional. ULID `public_id` (not sequential). Unique `certificate_number`.
- Additive `course_offerings.certificate_rules` JSON override (merged over template rules).

**Kinds:** `course_completion`, `offering_completion`, `assessment`, `manual`. Manual is always eligible. Others require a matching enrollment (`unified_student_id`) plus configured rules (min progress, attendance, assessment score, teacher approval, payment).

**Admin:** `/catalog/certificates` (permission `courses.manage`). Template builder, issue, revoke, CSV of issued rows, HTML download. Documents go through `DocumentRendererInterface` template `course-certificate`. UI never says PDF (ADR-012).

**Public QR verify (security surface)**

- Unlocalized `GET /verify/certificates/{public_id}` — **no auth**.
- Throttled. Unknown or malformed token → generic **404** (no existence oracle beyond 404).
- Confirms authenticity (or revoked) and shows **only the certificate face**: student name, course, offering, date, grade, certificate number, institute.
- Never student id, email, national ID, enrollment ids, or other records.

**Morph-map (same slice):** `certificate_template`, `issued_certificate`.

## C2 — Completion and performance reports (done, #107)

Staff `/catalog/reports/completions` (`courses.manage`): offering completion summaries, course completion summaries, roster with progress / attendance / lessons, CSV.

Student and parent `/portal/performance`: own enrollments (`relationship: self`) and/or linked children via `ListGuardianChildrenAction`. CSV. Portal controller calls Courses actions only (no cross-domain Models).

## C3 — Teacher review reports (this slice)

Pending-review / weakness / revision on `/catalog/reviews` (`courses.manage`). Engine stays subject-ignorant (no `course_type` branch).

- **Pending:** submitted activity/assessment attempts waiting for a teacher score. Cards show student name, course, wait time; scoring form unchanged. CSV section `pending_review`.
- **Weakness:** latest **scored** attempt per student + item. Weak if percent is below the passing bar, or below the page threshold (default 50%) when no passing score is set. If `passing_score` is greater than `max_score` (legacy quizzes stored 50/70 as percents), treat it as a percent, not raw points.
- **Revision:** same weak rows with a recommendation — retry when retakes remain, otherwise teacher review. Derived from activity `settings.retake_limit` / assessment `retake_limit`.
- Filters: academic year (via enrollment offering, or `attempts.academic_year_id`), course, threshold. CSV export of all three sections.

No new tables. Progress owns attempt reads (`ListPendingReviewsAction`, `ListScoredAttemptsAction`); Courses enriches titles and passing/retake rules. Arabic/Qur’an specialty reports stay on their own pages.

## Out of scope here

Paid course access (Phase 4). Portal composition (D1). Hifz dashboards (F4).
