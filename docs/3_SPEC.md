# Phase 3 — Certificates and reports (C1–C3)

Course-engine Phase 3 (`docs/SPEC.md` §39 / §48). Parent/student **composed dashboard** is Phase D, not this file.

Engine core stays subject-ignorant. No `if (course_type === …)`. Hifz frozen until Phase F.

## C1 — Course certificates (this slice)

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

## C2 — Completion and performance reports (this slice)

Staff `/catalog/reports/completions` (`courses.manage`): offering completion summaries, course completion summaries, roster with progress / attendance / lessons, CSV.

Student and parent `/portal/performance`: own enrollments (`relationship: self`) and/or linked children via `ListGuardianChildrenAction`. CSV. Portal controller calls Courses actions only (no cross-domain Models).

## C3 — Teacher review reports (next)

Pending-review / weakness / revision reports. Extend `/catalog/reviews` without subject branches in the engine.

## Out of scope here

Paid course access (Phase 4). Portal composition (D1). Hifz dashboards (F4).
