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

## Slice 2.2 — Question bank

Subject-agnostic question bank. Tag via ExamsGrades standards contract
(no ExamsGrades model imports). Editing a question must not mutate
existing attempt snapshots.

## Slice 2.3 — Assessment builder + player

Assessments attach to lesson, module, or course. React player, autosave,
attempt snapshots, retake/passing rules.

## Slice 2.4 — Teacher review

Teacher review dashboard, scores, feedback. Students see feedback.
Teacher-marked submissions work.

## Slice 2.5 — Session + attendance UI polish

Fuller session management, attendance marking UI, student schedule,
teacher schedule. Builds on 1B.3 foundation; still Offerings-owned.

## Out of scope

AI, payments, certificates, Capacitor, Hifz behavior change, Arabic
skill trees, quiz/assignment engines beyond the four patterns.
