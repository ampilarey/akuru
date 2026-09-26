<?php

/**
 * Tables that record something dated but carry no `academic_year_id`, and why
 * each is allowed to (CLAUDE.md rule 10 — see AcademicBackboneTest).
 *
 * **This list may only shrink.** A table that gains the column must be deleted
 * from here, and so must one that stops existing; the test fails on a stale
 * entry as loudly as on a new violation. Nothing may be added without a reason
 * a reader can check.
 *
 * Three kinds of entry, and they are not equivalent:
 *
 *  - **Not year-scoped** — a profile, a catalogue entry, website content,
 *    telemetry. These are permanent; the rule does not reach them.
 *  - **Superseded** — a pre-unification table whose replacement carries the
 *    backbone. These leave when the old screens are retired, not before.
 *  - **A miss** — the rule applies and the column is simply absent. These are
 *    the ones to fix. Adding the column to a populated table is rule 9's three
 *    deploys (additive migration → backfill from `terms.academic_year_id` or
 *    the row's own date → switch the writers), so each is its own slice.
 *
 * @return array<string, string>
 */
return [
    // ---- Not year-scoped: a person, not an event ----------------------------
    'students' => 'A person record. `admission_date` is an attribute of the human; the year-scoped fact is their `class_student` row, which carries the backbone.',
    'staff_profiles' => 'A person record. `joined_date` is an attribute; employment deliberately spans academic years.',
    'teachers' => 'Legacy profile table superseded by `staff_profiles`. Same reason, and it leaves when the Blade teacher screens do.',
    'staff_contracts' => 'A contract spans academic years by design — its own `start_date`/`end_date` are the scope, and forcing a year would have to pick one arbitrarily.',

    // ---- Not year-scoped: catalogue, website, telemetry ---------------------
    'courses' => 'A catalogue entry, not a run of one. The dated, year-scoped thing is the offering (ROADMAP §3.4); `courses.start_date` is the legacy column that split is moving off.',
    'announcements' => 'Website content, published and expired by calendar date. A notice is not a school record.',
    'media_galleries' => 'Website gallery. `event_date` is editorial — the date shown under the album.',
    'daily_contents' => 'W2.2 daily content for the public site, published by calendar date and read by visitors with no year at all.',
    'daily_content_deliveries' => 'The send log for the above. A delivery receipt, not a school record.',
    'shop_daily_stats' => 'Bookstore funnel counters (BOOKSHOP_PLAN B9e) keyed by calendar day and shop, like `dashboard_analytics`; the Bookstore is commerce, and none of its tables carry the year (precedent B2).',
    'dashboard_analytics' => 'Platform telemetry keyed by calendar date. Scoping it to an academic year would make the platform-health charts unreadable across a rollover.',
    'admission_applications' => 'An applicant is not in a year yet — the intake they are applying for is `expected_start`. If admissions ever reports per intake year, this becomes a miss and should move down to that group.',

    // ---- Not year-scoped: the parent carries it -----------------------------
    'payment_plan_installments' => 'A schedule line on a `payment_plans` row, which hangs off an `invoices` row — and `invoices` carries the backbone (S4.1). The year is one hop away and cannot disagree.',
    'student_status_history' => 'A row on a person\'s timeline rather than in a year\'s ledger, written by `ChangeStudentStatusAction` alongside the `class_student` rows that are year-scoped.',

    // ---- Superseded: the replacement carries the backbone -------------------
    'assignments' => 'Pre-S3 Blade assignments, superseded by the Courses engine\'s activities.',
    'teacher_absences' => 'Blade substitution module, unreferenced by any current code path; superseded by `staff_attendance`, which carries the backbone.',
    'substitution_requests' => 'Same Blade substitution module as `teacher_absences`.',
    'quran_progress' => 'Pre-split Hifz progress. The engine\'s replacements — `quran_hifz_assignments` and `quran_revision_schedules` — both carry the backbone.',
    'hifz_enrollments' => 'Pre-split Hifz table (2026-06). Superseded by offerings + enrolments; frozen while §2b\'s successors carry the backbone.',
    'hifz_assignments' => 'Pre-split Hifz table. Superseded by `quran_hifz_assignments`, which carries the backbone.',
    'hifz_sessions' => 'Pre-split Hifz table. Superseded by `quran_session_records`.',

    // ---- Misses: the rule applies and the column is absent ------------------
    'course_enrollments' => 'MISS. Carries `term_id` with no year — rule 10\'s parenthetical exactly inverted, on the most-reported table in the system. Needs the additive migration + backfill from `terms.academic_year_id`.',
    'competency_assessments' => 'MISS. Created in the same migration as `term_grades` (S3.4), which carries both. This one got `term_id` only.',
    'report_cards' => 'MISS. `term_id` with no year (S3.6), so "every report card this year" has to join through `terms`.',
    'pickup_windows' => 'MISS. Created forty lines above `pickup_notices` in the same migration (E8), which carries the backbone — and a window is literally a row per date.',
    'absence_notes' => 'MISS. A per-date student absence record. Its sibling `class_attendance` carries the backbone; this one predates S2 and was never brought along.',
    'lesson_observations' => 'MISS. A dated observation that belongs to a term (S5.5). Appraisal reporting is per year.',
    'cpd_records' => 'MISS. A dated staff-development record (S5.5), reported per academic year in the same screens as appraisals.',
];
