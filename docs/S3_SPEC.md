# S3 Spec — Exams, Grades & Report Cards

**Phase:** S3 (after S2; needs terms, class_student, class_attendance for report-card summaries)
**Domains:** ExamsGrades (new), Academics (read), Media/Support (DocumentRenderer), Portal
**Repo state:** `Grade` model exists as a thin stub with no exam entity and no controllers — S3 is a greenfield build inside the new architecture. The legacy `Quiz`/`Assignment` models stay untouched (replaced by engine components in Phase 2; their historical data migrates then).

**Design constraint:** grading concepts here (grade scales, weighted items, term grades) must be reusable by the course engine's gradebook (`GradeItemContract`, Phase 2) — define them in ExamsGrades but keep them subject-agnostic. **Implemented** as `App\Domains\ExamsGrades\Contracts\GradeItemContract` + tagged `GradeItemProvider`s (exam + classroom assessment). The gradebook lists `grade_items` alongside existing `exams`.

---

## Slice S3.1 — Grading Foundations

**New `grade_scales`:** `id, name, type enum(percentage_bands, letter, competency_levels), bands json` — e.g. `[{min:85, grade:"A", point:4.0, descriptor_en/dv/ar}]` or competency levels `[{level:"mastered",...}]` — `active bool, is_default bool, timestamps`

**New `exam_types`:** `id, name (+ trilingual), code (midterm, final, quiz, assignment, practical, oral), default_weight smallint, counts_toward_final bool default true, active, timestamps`

**New `assessment_weight_schemes`:** `id, academic_year_id FK, class_id FK nullable (null = year default), subject_id FK nullable (null = class default), weights json ({exam_type_id: percent}), timestamps`
Rule: weights must sum to 100 (validation); resolution order: subject-specific → class default → year default. Admin UI shows the resolved scheme per class/subject.

**ADR-012 (required before coding; amended 2026-08-26):** `DocumentRendererInterface` in Support. **HTML is the supported production output** (`HtmlDocumentRenderer`). Browsershot/Chrome PDF remains a future container binding swap — do not claim PDF in UI until that bind exists. Original S3.1 recommendation (Browsershot + Dompdf fallback) is historical; see `docs/adr/ADR-012-document-renderer.md`.

## Slice S3.2 — Exams

**New `exams`:** `id, academic_year_id FK, term_id FK, class_id FK, subject_id FK, exam_type_id FK, name, exam_date date nullable, start_time/end_time nullable, room_id FK nullable, max_marks decimal(8,2), weight_override smallint nullable, instructions text nullable, status enum(scheduled, marks_entry, review, published, locked) default scheduled, created_by, published_at, timestamps`

**Scheduling rules (calendar-aware):**
1. Warn-block if `exam_date` is a `calendar_days` holiday/closure.
2. Warn if same class has another exam the same date (setting: max exams/class/day, default 1).
3. Optional room conflict check via S2's `TimetableConflictChecker` (room + time).
4. Bulk scheduler: create one exam per subject for a class/term in one screen ("Term 1 Finals — Grade 5").

**Status flow:** scheduled → marks_entry (opens grid) → review (entry complete, awaiting check) → published (visible to students/parents in Portal) → locked (no edits; admin unlock audited). Publishing triggers `ExamResultsPublished` event → Portal notification.

## Slice S3.3 — Marks Entry

**New `exam_marks`:** `id, exam_id FK, student_id FK, marks decimal(8,2) nullable, is_absent bool default false, is_exempt bool default false, remarks nullable, entered_by, updated_by, timestamps, unique(exam_id, student_id)`

Rules: marks ≤ max_marks (validation); absent/exempt are mutually exclusive with marks; exempt excluded from averages, absent counts as 0 unless setting says exclude (per-school setting, recorded in ADR). Roster comes from `class_student` (active as of exam_date — historical accuracy).

**Marks entry UI (React):** spreadsheet grid — students as rows, keyboard navigation (Enter/arrows), absent/exempt toggles, autosave per cell, entry-progress bar, anomaly flags (mark > max, blank cells on submit), CSV import/export. Permission: subject teacher of that class, or `exams.enter-any`.

## Slice S3.4 — Term Grades (weighted gradebook)

**New `term_grades` (computed cache):** `id, student_id FK, class_id FK, subject_id FK, term_id FK, academic_year_id FK, weighted_percent decimal(5,2), grade string, grade_point decimal(3,2) nullable, rank smallint nullable, components json (per-exam breakdown for transparency), computed_at, timestamps, unique(student_id, subject_id, term_id)`

`ComputeTermGradesAction(class, subject, term)`: resolves weight scheme → normalizes each published exam to its weight share (exam mark / max_marks × weight; multiple exams of one type share that type's weight equally unless weight_override) → maps to grade via the class's grade scale → optional class rank (setting; ties share rank). Recompute is idempotent; triggered on exam publish + manual button. **Teacher gradebook view:** class × subject matrix — students vs exams + computed term grade, drill-down to components json.

**Competency mode (alternative scheme):**
**New `competencies`:** `id, subject_id FK, name (+trilingual), description, sort_order` and **`competency_assessments`:** `id, student_id, competency_id, term_id, level (from competency-type grade_scale), assessed_by, notes, timestamps, unique(student_id, competency_id, term_id)`. A class/subject configured with a competency-type scale uses this instead of marks; report card template renders whichever mode applies. (Self-evaluation: backlog.)

## Slice S3.5 — Curriculum Standards (definitions + tagging)

**New `standards`:** `id, subject_id FK nullable, code, title (+trilingual), description, parent_id nullable (hierarchy), active` and polymorphic **`standard_taggables`** (`standard_id, taggable_type/id`) — taggable now: exams, plan_topics; Phase 2 adds question-bank questions, enabling per-standard results analytics then. S3 ships definitions, tagging UI, and a coverage report (topics/exams tagged per standard per term). No per-student standard analytics yet — that needs question-level data.

## Slice S3.6 — Report Cards

**New `report_card_templates`:** `id, name, applies_to (class ids json nullable), sections json (ordered: grades_table, attendance_summary, behavior_summary, competencies, teacher_comment, head_comment, awards), header/footer config, active`
**New `report_cards`:** `id, student_id FK, term_id FK, class_id FK, template_id FK, status enum(draft, ready, published), document_id FK (Media) nullable, generated_at, published_at, timestamps, unique(student_id, term_id)`
**New `report_card_comments`:** `id, report_card_id FK, comment_type enum(class_teacher, head), comment text (trilingual columns), author_id, timestamps`

`GenerateReportCardsAction(class, term)`: pulls term_grades (or competency_assessments), attendance summary (S2: % by status), behavior summary (counts, parent_visible only), comments → renders PDF via `DocumentRendererInterface` (queued, per-student) → status ready. Publish action flips to published + Portal notification; parents view/download in Portal. Regeneration allowed until published; after, new version with audit.

**Cumulative transcript:** read-only view + PDF across all years/terms for a student (from term_grades history + status history) — `GenerateTranscriptAction`. Optional GPA column if scales define grade_points.

## Slice S3.7 — Awards & Documents

**New `awards`:** `id, title (+trilingual), description, level enum(class, school), active` and **`student_awards`:** `id, student_id, award_id, academic_year_id, term_id nullable, awarded_date, notes, certificate_document_id nullable, timestamps`
Batch issue UI (pick award → pick students → generate certificates via renderer). Same renderer pipeline produces **ID cards** (template w/ photo, QR of student number) and **transfer/leaving certificates** (pulls status history). Public website "achievements" section reads published awards (consent-gated by `photo_media_use` where photos shown).

## Portal additions (S3)
Parent/student: published exam results per term, report card download, transcript request, awards. Admin overview adds: ungraded exams (marks_entry past exam_date), unpublished report cards.

## Tests (CI gates)
1. Weight resolution order + sum-100 validation; multiple-exams-per-type share calculation.
2. ComputeTermGrades idempotency + components json correctness (golden-file test with known inputs).
3. Absent vs exempt handling per setting; marks > max rejected.
4. Roster historical accuracy: student who left class before exam_date excluded.
5. Rank ties; recompute after a mark correction updates rank.
6. Report card generation golden test (HTML snapshot, EN + DV RTL template); attendance/behavior summaries match S2 data.
7. Status flow: published exam visible in Portal; locked exam rejects edits; unlock audited.
8. Permission matrix: subject teacher enters only own class/subject; parents see only published results of own children.

## Definition of Done
- [ ] Full cycle on real data: schedule term exams → enter marks (grid) → publish → term grades computed → report cards generated (trilingual templates render correctly incl. Thaana) → published to Portal → parent downloads PDF.
- [ ] Gradebook matrix + transcript live; awards batch issue works; one ID-card template renders.
- [ ] DocumentRendererInterface has its first production implementation (ADR-005 recorded) — certificates (Phase 3) and Library invoices inherit it.
- [ ] CSV export on all listings; STATUS.md updated.

**Out of scope:** question bank, per-standard student analytics, rubrics (Phase 2 — designed to attach to class or course); school quiz/assignment migration (Phase 2); fee/invoice anything (S4); self-evaluation (backlog).
