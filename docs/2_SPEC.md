# Phase 2 Spec — Activities, assessments, scheduled-course UI

**Phase:** 2 (after 1B). Source: `docs/SPEC.md` §47.
**Domains:** Courses (engine activities/assessments stay subject-ignorant),
Progress (attempts/scores via Actions), Offerings (fuller session UI).
No Arabic/Hifz/AI/payments/certificates/Capacitor.

Do not start this phase until 1B tests are green.

## Slice 2.1 — Activity patterns + builder (done)

Four base activity patterns (selection, text input, arrange, teacher-marked).
Admin can create activities without code changes. Autosave in-progress
answers. Auto-mark selection and text-input (normalization settings).
Arabic normalization flags apply only when configured. Teacher-marked
attempts stay `submitted` until 2.4 review. Answer keys are hidden from
students unless the attempt is scored and `show_correct_answer` is on.

## Slice 2.2 — Question bank (done)

Subject-agnostic question bank. Each question type maps to one of the
four activity patterns. Tag via ExamsGrades `*Standard*` Actions
(no ExamsGrades model imports; no-op when S3.5 tables are absent).
`SnapshotQuestionAction` returns a frozen copy — editing the live
question does not mutate a previously taken snapshot. Attachments go
through Media Actions.

## Slice 2.3 — Assessment builder + player (done)

Assessments attach to lesson, module, or course. React player, autosave,
attempt snapshots taken at start, retake/passing rules. Scoring reads
the snapshot, not the live question bank. Teacher-marked items leave
the attempt `submitted` until 2.4.

## Slice 2.4 — Teacher review (done)

Teacher review dashboard at `/catalog/reviews`. Reviewers score submitted
activity and assessment attempts and leave feedback. Students see that
feedback on the player. Teacher-marked 2.1/2.3 attempts become `scored`.

## Slice 2.5 — Session + attendance UI polish (done)

Fuller session management (update + teacher assignment), attendance
roster with student names and bulk mark, student schedule at
`/learn/schedule`, teacher schedule at `/teach/schedule`. Still
Offerings-owned.

## Slice 2 leftover — class quiz/assignment → engine (done)

Migrate ClassRoom-bound `Quiz` / `Assignment` rows onto engine `assessments`
(attachable to a **class** or a **course**, never both). Additive columns +
idempotent backfill; legacy tables stay until a later cleanup deploy (ROADMAP
§3.5 / rule 9). Gate: `php artisan assessments:verify-legacy-migration`.
Maps onto the four activity patterns only — no second quiz engine.

## Slice 2 leftover — unified gradebook via GradeItemContract (this slice)

School exam marks and engine class-assessment scores in **one** gradebook.
`ExamsGrades\Contracts\GradeItemContract` + `GradeItemProvider` registry
(Moodle grade-API pattern). Exam provider lives in ExamsGrades; classroom
assessments register from Courses (scores via Progress
`ListAssessmentScoresAction`). No `course_type` / Hifz / Arabic branch in
the gradebook core. Term % still uses published exams + weight scheme only.

## Out of scope

AI, payments, certificates, Capacitor, Hifz behavior change, Arabic
skill trees, quiz/assignment engines beyond the four patterns.
