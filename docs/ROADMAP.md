# Akuru Institute — Platform Roadmap

**Scope:** Restructure the current repo (`ampilarey/akuru`) into a modular monolith that contains both:

1. The **full School Management System** (SMS)
2. The **General Learning Platform & Course Builder** (per `general_learning_platform_final_spec_with_build_strategy.md` — includes §51 Arabic Skills + local AI, §52 Qur'an/Hifz recitation module, §57 build slices)

**Audit baseline:** repo state as of June 2026 (`main`, last push 2026-06-08).

**Governing principle:** The Akuru platform is **one modular monolith**. The School Management System, Course Engine, Arabic Skills, and Qur'an/Hifz are not separate apps — they are separate domains/components inside the same platform. Shared identity, people, payments, media, notifications, review workflows, and reporting must never be duplicated.

---

## 1. Current State Audit (what the repo has today)

### Working and wired (routes + controllers + views exist)

| Feature | Where | Notes |
|---|---|---|
| Students / Teachers CRUD | `StudentController`, `TeacherController` | Blade UI |
| Hifz program | `app/Http/Controllers/Hifz/*` (15 controllers), `app/Services/Hifz/*` (6 services) | Best-structured area; role dashboards (dean, supervisor, teacher, parent, student) |
| Quran progress / recitation | `QuranProgressController`, `RecitationPracticeController` | |
| Admissions + public registration | `AdmissionApplicationController`, `PublicRegistrationController`, `RegistrationFlow` | |
| Payments (BML Connect) | `app/Services/Payment/*` | Already has `PaymentProviderInterface` — keep this pattern |
| OTP auth + rate limiting | `OtpService`, `Auth/Otp*` controllers | Keep |
| Public website CMS | Pages, Posts, Banners, FAQs, Testimonials, Gallery | Blade |
| Announcements, substitutions | `AnnouncementController`, `Substitutions/*` | |
| Settings, instructors, analytics | `Admin/*`, `AnalyticsController` | |

### Skeletons only (migration + stub model, **no controller, no UI**)

| Feature | Model size | Missing |
|---|---|---|
| Attendance | 10 lines | Everything: marking UI, reports, parent notify |
| Grades / Exams | `Grade` 72 lines, no Exam model | Exam entity, marks entry, report cards |
| Fees / Invoicing | `Invoice`, `FeeItem`, `InvoiceLine` ~10 lines each | Fee structures, invoice generation, billing UI |
| Academic Year / Terms | `AcademicYear` 10 lines | Year/term lifecycle, promotion/rollover |
| ClassRoom management | 58 lines, relations only | Sections, capacity, class CRUD UI |
| Timetable | Model + `Period` exist | Builder UI, conflict checks |
| Internal messaging | `Message` 172 lines | Wiring into portals |

### Structural problems

1. **Two student models.** `Student` (school: class, grades, Hifz) and `RegistrationStudent` (course registration, payments, `student_guardians`). One child = two records.
2. **`courses` table conflates course and offering.** Holds `fee`, `seats`, `schedule`, `start_date`, `end_date`, `enrollment_deadline` — offering data per the spec.
3. **Flat architecture.** ~80 models in `app/Models`, logic in controllers, concrete services called directly (`SmsGatewayService`, `BmlConnectService`, `WebPImageService`).
4. **No React/Inertia.** Blade + Alpine only (171 Blade views, 0 JSX). Spec requires Inertia + React for all interactive platform features.
5. **`AcademicYear`/term not the backbone.** `course_enrollments.term_id` is a bare nullable int (no FK, no Term model). Nothing else is term-scoped.
6. **No learning-content engine.** No modules, lessons, lesson_revisions, content_blocks, offerings, student_lesson_progress, glossary, question bank.
7. **Old `Quiz`/`Assignment` models** are ClassRoom-bound; not reusable as the spec's Activities/Assessments.
8. **Tests:** PHPUnit, 19 files, no architecture tests. Spec requires Pest arch tests.
9. **Minor:** duplicate `create_otps_table` migrations (2025_10_15 and 2026_02_16); ~10 overlapping deployment docs in repo root.

---

## 2. Target Domain Map

```text
app/
  Domains/
    Identity/        users, roles (spatie), OTP auth, sessions
    People/          unified Student, Teacher/Staff, ParentGuardian,
                     guardian-student links
    Academics/       academic years, terms, classes/sections, subjects(school),
                     timetable, periods, substitutions, class attendance
    ExamsGrades/     exams, marks entry, report cards   (Phase S3)
    Hifz/            Phase 0 ONLY: existing live program moved as-is, untouched.
                     End state: dissolves into Courses/Components/Quran after
                     Phase 2 (see §2b) — structure goes to Courses/Offerings,
                     Quran-specific tracking becomes the Quran component.
                     There is ONE Quran domain across all phases, never two.
    Admissions/      admission applications, registration flows
    Finance/         fee structures, invoices, payments, BML provider
    HR/              full staff HR — recruitment, contracts, staff attendance,
                     leave entitlements/balances, appraisals, training/CPD,
                     payroll (Phase S5; payroll consumes Finance + attendance)
    Commerce/        PLATFORM-WIDE wallet, gift cards, discount codes,
                     campaigns, free-access coupons — operates on a generic
                     "purchasable" concept; consumed by Library, course/offering
                     payments (spec Phase 4), and Finance invoices. Separate
                     module by design so it can be enhanced independently (§9)
    Library/         Akuru Knowledge Library — catalog (books/articles/research/
                     course materials), protected reader, reading progress,
                     writer portal, editorial + peer-review workflow, writer
                     sales/payouts (§9; L-track)
    Courses/         ENGINE CORE — course templates, hierarchical subjects + audiences
                     + levels (taxonomy), modules, lessons, lesson revisions,
                     content blocks, component registry + contracts. Knows NO
                     specific subject. Taxonomy = admin-managed seed data only
                     (ADR-003); engine stays subject-ignorant.
    Courses/Components/   Moodle-style pluggable course components (§2a):
      Core/          spec block types, generic activities, assessments,
                     question bank (Phases 1A–2)
      Arabic/        Arabic Skills module — listening/speaking/reading/writing
                     activities, letters/harakas data (spec §51)
      Quran/         Qur'an/Hifz module — recitation submissions, mistake marks,
                     revision schedules, memorization progress (spec §52)
    Offerings/       course offerings, delivery modes, sessions,
                     offering attendance, enrollments, seat limits
    Progress/        student_lesson_progress, unlock + completion evaluators,
                     unified gradebook (grade-item contract)
    Pronunciation/   SHARED local/offline Arabic pronunciation AI service behind
                     an interface + feature flag; consumed by Arabic AND Quran
                     components (spec §51.9–51.18, §52.3–52.4). Never called
                     directly — always via contract.
    Media/           media library, storage interface, WebP/processing
    Notifications/   notification channels (in-app, SMS, email, push), templates
    PrayerTimes/     Maldives prayer timetable engine (salat.db import, island
                     resolver, versioned cache, public contract) + prayer-time
                     SMS broadcast orchestration (sends via Notifications
                     SmsSenderInterface only)
    Portal/          parent/student portal composition (reads other domains'
                     public interfaces only)
    Website/         public CMS: pages, posts, banners, FAQs, testimonials,
                     gallery, contact; conversion layer + Islamic Daily
                     Content engine (ayah/hadith/sayings/reminders),
                     research & publications; consumes PrayerTimes for widgets
    Settings/        system settings
  Support/           shared base classes, DTO base, helpers
```

### Boundary rules (enforced by Pest arch tests)

- A domain never imports another domain's Eloquent models.
- Cross-domain calls go through public **Actions / Services / DTOs / Contracts**.
- Cross-domain side effects use **events** (e.g. `StudentEnrolled` → Notifications listener).
- No domain calls a third-party SDK directly — always behind a domain-owned interface (extend the existing `PaymentProviderInterface` pattern to SMS, storage, video, notifications).
- Controllers: authorize → validate/DTO → call action → return Inertia response. Nothing else.

### Internal domain layout (each domain)

```text
Domains/Academics/
  Actions/          MarkClassAttendanceAction, PromoteStudentsAction...
  Models/           internal Eloquent models
  Services/
  DTOs/
  Events/
  Contracts/        public interfaces other domains may consume
  Http/
    Controllers/
    Requests/
  Policies/
  Database/
    migrations/  factories/  seeders/
  routes.php
  Providers/AcademicsServiceProvider.php
```

---

## 2a. Course Components — the Moodle Pattern

Courses differ widely — Hifz, Arabic language, kids courses, Umrah training, staff certification — in pedagogy, tracking, and completion logic. The architecture follows **Moodle's proven plugin model**: the engine core never knows what a quiz or a recitation is; every learning and exam component is a registered plugin against core contracts. The course leader/lecturer builds any course from one **"Add component" palette** — and a Hifz or Arabic course is just a course whose leader picked specialized components. Variation is handled through four extension points, never by branching inside the engine:

### 1. Data-driven structure (no code)
Most variation is just content shape: number of modules, block mix, glossary use, assessment weight, drip/unlock rules, completion rules. Admins configure this per course in the builder. A conversation-heavy language course and a document-heavy compliance course are the *same engine*, different data.

### 2. Pluggable strategies (interfaces, already required by spec §42)
Per-course-configurable implementations behind interfaces:
- `UnlockRuleEvaluator` — sequential, date-drip, prerequisite-based, teacher-released
- `CompletionRuleEvaluator` — view-all, pass-assessment, attendance-%, teacher-sign-off, milestone-based
- `ProgressCalculator` — lesson-count, weighted, mastery-based
- `CertificateEligibility` — per offering (spec §11.11)

A course (or offering) stores *which* strategy + its config (JSON settings); the container resolves the implementation. New pedagogy = new strategy class + registry entry, zero engine changes.

### 3. Component registry (the heart of the Moodle pattern)
Every content block, activity, and exam component is **registered, not hardcoded**. Each component declares:
- its settings **schema + validation**
- its **React builder form** (what the lecturer configures) and **React player/renderer** (what the student sees)
- whether it is **gradable** — gradable components implement a `GradeItemContract` and push results into the unified gradebook (Moodle's grade API pattern), so an Arabic speaking drill, a Quran recitation, and a fiqh quiz all land in one gradebook
- optional **capabilities** (needs media recorder, needs AI service, teacher-marked vs auto-marked)

Phase 1A/1B ship the spec's core types. The Arabic and Quran components (below) register through the exact same mechanism — proof the registry is real.

**Phase 1A scope guard:** the registry is a **simple internal registry** (a service-provider registration list) — no marketplace, no dynamic installer, no external code loading, no public plugin API, no package discovery. Phase 1A registers only the spec's internal block types (Text, Rich Text, Instruction, Image, Audio, Video, PDF).

### 4. Course-type extension domains (own tables + dashboards)
When a course type needs tracking the generic engine shouldn't know about, it becomes an **extension domain** that *attaches* to engine entities via their public contracts:

```text
Engine owns:            course, offering, enrollment, sessions,
                        attendance, lessons, progress, review queue
Extension owns:         its specialty tables, keyed by enrollment_id /
                        session_id / lesson_id, + its own dashboards
Extension listens to:   engine events (SessionHeld, LessonCompleted,
                        EnrollmentCreated...)
```

**Hifz is the reference extension** (see §2b). Future examples: tajweed analysis, lab/practical skill sign-offs, speaking-exam rubrics.

> Rule of thumb: if a feature could apply to more than one subject, it belongs in the engine (generic). If it's intrinsically about one subject's pedagogy, it belongs in an extension.

### 2b. Hifz migration (the proof the engine is general) — ONE Quran domain, not two

Hifz is live; the engine isn't. So:

| Step | When | What |
|---|---|---|
| 1 | Phase 0 | Move Hifz code into `Domains/Hifz` — **namespace/route updates only**: no behavior change, no business-logic refactor, no dashboard redesign, no engine migration |
| 2 | After Phase 2 (offerings + sessions + attendance + teacher review exist) | Map: HifzProgram → Course (type: hifz), halaqa/batch → Offering (face-to-face/hybrid), HifzSession → offering session + attendance, HifzEnrollment → course enrollment, milestones → completion rules/progress |
| 3 | Same migration | Quran-specific tracking becomes `Courses/Components/Quran` per spec §52: recitation submissions, mistake marks, revision schedules, memorization progress — re-keyed to engine IDs. **Reuse the existing repo tables** (`surahs`, `quran_ayahs`, `quran_words`, mushaf/page tables, June 2026 migrations) — spec §52.16–52.17's `quran_surahs`/`quran_ayahs` must map onto these, never duplicate them |
| 4 | After verification | Retire duplicated Hifz enrollment/session/attendance structures and the temporary `Domains/Hifz`; dashboards (student/teacher/supervisor/dean per spec §52.7–52.13) read structure from Offerings + specialty data from the Quran component |

Until step 2, **do not refactor Hifz** — it's in production.

### 2c. Admin capabilities — runtime vs developer

What the **admin can do from the dashboard** (no developer, no deploy):

| Capability | Mechanism |
|---|---|
| Enable/disable any installed component — globally or per course | component registry status flags |
| Toggle AI and other risky features | feature flags (spec §52.27 pattern) |
| Configure component settings, permissions, grading weights | per-component settings schema |
| Control which components appear in each course leader's palette | per-course component allow-list |
| Choose strategy implementations per course (unlock/completion/progress) | §2a strategy config |
| Swap providers (payment, SMS, video conferencing) among installed implementations | container binding config |

What requires a **developer + deployment**:

| Capability | Why |
|---|---|
| Installing a genuinely new component type | someone must write the package (schema, builder form, player, grade contract) |
| New strategy implementations or provider integrations | same — new code |
| Component upgrades | versioned deploys; lesson revisions + offering pinning keep running courses stable during upgrades |

> Deliberate non-goal: a Moodle-style browser ZIP-upload plugin installer. For a single institute it adds heavy security risk (uploaded code = code execution) with no benefit. The registry is the foundation such an installer could be built on later if Akuru goes SaaS.

### 2d. Live online classes (Zoom-style)

Two levels, both behind a `VideoConferencingInterface` in Offerings (createMeeting, getJoinUrl, getAttendance, getRecording):

- **Level 1 — meeting link (Phase 1B, per spec):** teacher attaches a Zoom/Meet/Teams link to an online or hybrid session; students get a "Join class" button at the scheduled time.
- **Level 2 — API integration (post-Phase 2):** auto-create the meeting when a session is scheduled; pull participant logs to **auto-mark session attendance**; ingest cloud recordings into the Media domain so the recording becomes lesson content for absentees.

Provider notes: Zoom = first implementation candidate (paid plan needed for cloud recording APIs, per-host licensing); **BigBlueButton** (education-focused, self-hosted, whiteboard/breakout rooms, the Moodle-ecosystem standard) and **Jitsi** (free, self-hosted) are swap-in alternatives — the interface makes the choice reversible. Engine and attendance must function fully in Level 1 mode if no provider is configured.

---

## 3. Table-by-Table Changes

### 3.1 Unify (breaking, do first)

| Current | Change |
|---|---|
| `students` + `registration_students` | Merge into one `students` table in **People**. Map `registration_students` rows to `students` (match by user/contact); repoint `course_enrollments.student_id`, `student_guardians`, payments. Keep a `legacy_registration_student_id` column during transition. |
| `parent_guardians` + `student_guardians` | One guardian model + one pivot in **People**. |
| `otps` (duplicated migrations) | Keep the 2026 `user_contacts`-based system; drop/squash the 2025 one. |

### 3.2 Promote to backbone

| Table | Change |
|---|---|
| `academic_years` | Add proper fields (name, start/end, status: upcoming/active/closed). New `terms` table (`academic_year_id`, name, dates, status). |
| `classes` (ClassRoom) | Scope to `academic_year_id`; add section, capacity, class_teacher per year. |
| `course_enrollments.term_id` | Real FK to `terms` (replace bare int + generated `term_key`). |
| Everything new (attendance, exams, invoices, timetables) | Always carries `academic_year_id` (and `term_id` where relevant). |

### 3.3 Build out the SMS skeletons

| Domain | Tables |
|---|---|
| Academics | `class_attendance` (replaces stub `attendance`; student, class, date, status, marked_by, term), `timetables` + `periods` (exist — add conflict constraints), keep `teacher_absences`, `substitution_*` |
| ExamsGrades | `exams` (type, term, class, subject, max marks, weight), `exam_marks` (replaces stub `grades` usage), `report_cards`, `grade_scales` |
| Finance | `fee_structures` (class/year/optional items), `invoices` + `invoice_lines` (generate from structures), link to existing `payments`. Reuse `PaymentService` + `PaymentProviderInterface`. |
| PrayerTimes | `prayer_categories`, `prayer_islands`, `prayer_times`, `prayer_recipient_groups`, `prayer_broadcasts`, `prayer_broadcast_recipients` — see `docs/W3_SPEC.md` (PLANNED; operational logs, no `academic_year_id`) |

### 3.4 Course platform split (per spec §10–11, §28, §43)

| Current `courses` columns | Destination |
|---|---|
| title, slug, descriptions, cover, language, `subject_id`, prerequisites, objectives | stays on `courses` (template) |
| `fee`, `registration_fee_*`, `seats`, `schedule`, `start_date`, `end_date`, `enrollment_deadline`, `requires_admin_approval`, **`audience_id`**, **`level_id`** | move to new `course_offerings` (audience + level per offering — ADR-003) |

Data migration: every existing course → course + **one auto-created offering** (mode = face-to-face or as appropriate); `course_enrollments` gain `course_offering_id` and are repointed.

New tables (spec §43): `course_subjects` (hierarchical course taxonomy — separate from school `subjects`), `audiences`, `course_levels`, `course_modules`, `lessons`, `lesson_revisions`, `content_blocks`, `course_offerings` (`audience_id`, `level_id`), `course_offering_sessions` (incl. meeting link/provider fields for §2d live classes), `attendance_records` (offering attendance — separate table from class attendance; shared reporting contract in Portal), `activities`, `activity_attempts`, `student_submissions`, `teacher_feedback`, `assessments`, `assessment_questions`, `assessment_attempts`, `questions`, `glossary_items`, `lesson_glossary_items`, `student_lesson_progress`, `certificate_templates`, `issued_certificates`.

Additionally (for §2a): `courses.course_type` (default `general`; `hifz` etc. binds extension domains), and JSON strategy config columns — `courses.completion_config`, `lessons.unlock_config` / offering-level overrides — storing which evaluator strategy + settings apply.

### 3.5 Deprecate / remove

| Item | When |
|---|---|
| `ELearningController` + `e-learning` views | When React lesson player ships (Phase 1A) |
| `Quiz`, `QuizQuestion`, `QuizAttempt`, `Assignment`, `AssignmentSubmission` (ClassRoom-bound) | When Assessments domain ships (Phase 2); migrate any live data |
| Offering columns on `courses` | After split migration verified |
| Hardcoded content in views/seeders/config | Phase 1A rule: all content from DB |
| Hifz structural tables (`hifz_enrollments`, `hifz_sessions`, halaqa/batch structures) | After Hifz→engine migration (§2b, post-Phase 2); specialty tables (mushaf, mistakes, scoring) stay |
| Root-level doc clutter (10 deployment MDs) | Anytime → move to `docs/` |

---

## 4. Build Order

**Build discipline (spec §57, applies to every phase):** vertical slices with tests included in each slice; update `STATUS.md` after every slice; record ADRs for major decisions (e.g. the multi-branch call); architecture tests run from Phase 0; **no future-phase implementation "while you're there"**; **no AI code anywhere in Phase 1A/1B**; teacher review precedes any AI; AI ships only after dataset → labeling → training → measured accuracy. Standing rule: every major listing screen ships with CSV/Excel export (§8.5).

### Phase 0 — Foundation (no user-visible change)

1. Install **Inertia.js + React + Vite glue** alongside existing Blade (both run together).
2. Install **Pest** (+ keep PHPUnit tests green); add **arch tests** for domain boundaries.
3. Create `app/Domains/` skeleton + per-domain service providers + route registration.
4. **Move existing code into domains** with zero behavior change: Hifz → `Domains/Hifz`, CMS → `Domains/Website`, payments → `Domains/Finance`, OTP/auth → `Domains/Identity`, admissions → `Domains/Admissions`, settings/notifications/analytics accordingly. Update namespaces; arch tests lock the boundaries.
5. **Interfaces in front of concretes:** `SmsSenderInterface` (→ SmsGatewayService), `MediaStorageInterface`, `ImageProcessorInterface` (→ WebPImageService), `NotificationChannelInterface`; container bindings. Payment already done — replicate the pattern.
6. CI: pint + pest + arch tests must pass.

**Done when:** site behaves identically; arch tests fail on any cross-domain model import.

### Phase S1 — People + Academic backbone  *(prerequisite for everything)*

1. Unify `Student`/`RegistrationStudent` (§3.1) with data migration + verification script.
2. **Rich profiles** (§8.5): medical info, multiple emergency contacts, document vault (Media domain), staff profiles with qualifications; **custom fields engine**; **enrollment status lifecycle** with `student_status_history`; **consent records** + data-retention ADR.
3. AcademicYear + Term lifecycle, year activation, **promotion/rollover action** (`PromoteStudentsAction`, archive year).
4. ClassRoom management UI (Inertia/React): classes, sections, capacity, subject-teacher assignment per year.

### Phase S2 — Attendance, Timetable + Class Register (school)

1. Timetable builder (React drag-drop), rooms as first-class entities, conflict checks (teacher/room/period), room/event booking, integrate substitutions.
2. **Class register loop (EduPage-style core):** timetable slot → register entry → teacher picks topic from teaching plan (`CoursePlan`/`PlanTopic`) → marks per-lesson attendance → parent notified. Daily/period modes, reports, reminders for unfilled registers.
3. Absence notes (parent submits, teacher approves), unexcused-absence tracking.
4. Behavior records (compliments/notices, parent-visible).
5. Requests/approvals workflow (teacher leave → auto-absence + cover suggestions; generalizes substitutions).
6. School calendar wired to register (events can cancel/override lessons); event/elective registration with seat limits.

### Phase S3 — Exams, Grades, Report Cards

Exam types, scheduling per term/class/subject (calendar-aware to avoid clashes), marks entry UI, **weighted gradebook** (assignment-type weights → final grade), grade scales incl. **competency-based evaluation**, **curriculum standards** (define, tag, coverage + per-standard results analytics), report card generation, **cumulative transcripts** (across-years record, optional GPA), **awards/achievement certificates**, ID cards/transfer documents — all rendering through one `DocumentRendererInterface` (shared with course certificates).

### Phase S4 — Finance

Fee structures per class/year → invoice generation (term billing) → **payment plans/installments** (`invoice_installments` — designed so spec Phase 4 course payments inherit it) → pay online via existing PaymentService → receipts; arrears reports; sibling discounts/scholarships/waivers. Parent portal payment view. Backlog: bank-statement import + auto-matching (EduPage-style). S4's Finance structures are also the foundation Phase S5 payroll builds on.

### Phase S5 — Full HR (after S4; payroll needs Finance + staff attendance)

`Domains/HR`, built in dependency order:

1. **Staff attendance** — check-in/out, reuse the attendance design's external-source interface (a future biometric/card device is just another attendance writer).
2. **Leave management** — leave types, entitlements, balances, accrual + carry-over, sits on top of the S2 requests/approvals workflow; leave taken feeds payroll.
3. **Contracts & compliance** — contract terms, renewal alerts, and **work permits/visas with expiry alerts** (essential if the institute employs expatriate teachers).
4. **Recruitment & onboarding** — job postings, applicant pipeline (reuse Admissions' application pattern), interviews, offer, onboarding checklist; offboarding/exit mirror.
5. **Performance** — appraisal cycles, goals, lesson observations (links to Academics' class data); training/CPD records.
6. **Payroll** — salary structures, allowances/deductions, unpaid-leave deductions from attendance/leave data, **Maldives Retirement Pension Scheme contributions and income-tax withholding as configurable rules** (never hardcoded — rates change), payslip generation via `DocumentRendererInterface`, bank-transfer file export, payroll periods with lock/approval. Behind a `PayrollCalculatorInterface` so local statutory logic stays swappable.

> Honest scope note: payroll is the riskiest module in the whole roadmap — statutory rules, audits, and money. Build it last within S5, gate it behind its own feature flag, run it in parallel with the existing payroll process for at least two cycles before switching, and record an ADR if you instead choose export-to-accounting-software (both are valid; the schema supports either).

### Phase 1A — Course engine core *(can run in parallel with S2–S4 after S1)*

Per spec §46.1 and the **§57 Build Strategy slice order** (Slices 1–7: foundation → course CRUD → modules/lessons/revisions → text blocks → media pipeline → enrollment/progress → parent links + arch tests). Built on the §2a component registry and strategy interfaces from day one: taxonomy CRUD (hierarchical subjects, audiences, levels — ADR-003), modules, lessons, **lesson revisions**, dynamic content blocks (Text, Rich Text, Image, Audio, Video, PDF, Instruction), React block builder + lesson player, media pipeline, self-learning enrollment, basic progress, thin controllers + actions, Phase 1A tests.

### Phase 1B — Offerings + modes

Per spec §46.3: `course_offerings` + the courses-table split migration (§3.4), five delivery modes, sessions, offering attendance, **concurrency-safe seat limits** (DB transaction + lock + test), content pinning, Phase 1B blocks, unlock/completion evaluators, PWA manifest + service worker, RTL/i18n polish.

### Phase 2+ — per spec

Activities + Assessments as **Core components** through the registry (then migrate school quizzes/assignments onto the same engine, attachable to class **or** course), teacher review, question bank, unified gradebook via `GradeItemContract` → certificates (Phase 3) → payments for offerings (Phase 4, reuse Finance) → Capacitor mobile (Phase 5). **Post-Phase 2 option (independent of Arabic/Quran):** Live classes Level 2 (§2d) — provider API integration, auto-attendance from participant logs, recordings → Media domain.

### Arabic Module (spec §51) — after Phase 2 core components are stable

- **Arabic Module A (non-AI):** letters/harakas reference data, listening/speaking/reading/writing activity components registered through the §2a registry, Arabic text normalization, activity metadata. No AI dependency.
- **Arabic Module B (local AI):** stand up `Domains/Pronunciation` — local/offline pronunciation AI behind an interface + feature flag (spec §51.9–51.15), prediction storage, pronunciation attempts, human-in-the-loop training samples + model versions (§51.16–51.18). Arabic components consume it only via contract.

### Qur'an/Hifz Module A — human-first (after Phase 2; NO AI dependency)

Run the §2b migration as soon as the engine is stable — do **not** wait for AI. Live Hifz dissolves into `Courses/Components/Quran`: structure mapped to courses/offerings/sessions, recitation submissions, **teacher** mistake marking (haraka-strict per §52.2), revision schedules, memorization progress, manual recording mode, supervisor/dean oversight dashboards. Teacher review is the first useful pronunciation workflow. **Reuse existing repo Quran tables** — see §2b step 3. Feature-flagged (§52.27).

### Qur'an/Hifz Module B — AI-assisted (after Arabic Module B / Pronunciation AI is stable)

Add AI assistance on top of the human workflow: isolated letter + haraka checking, low-confidence flags routed to teacher review, future recitation assistance. Same `Pronunciation` service as Arabic (§52.3) — one model family, two consumers. AI arrives only after dataset collection, labeling, training, and measured accuracy (spec §51.16–51.18 human-in-the-loop). The Hifz program must already be fully functional without it.

### L-track — Akuru Knowledge Library (parallel; see §9)

L1–L7 run as an independent parallel track after Phase 0 + W1/W2. Key shared milestone: **L4 ships `Domains/Commerce`**, which spec Phase 4 (course payments) then adopts — gift cards, wallet, and discounts work across both courses and the library from that point.

### Portal (continuous)

After each phase, surface it in the Portal domain: one parent/student login showing class attendance, Hifz, exams, invoices, and later course progress — composed only from other domains' public contracts. Includes parent-teacher meeting slot booking and the admin overview dashboard (unfilled registers, ungraded classes, plan adherence).

---

## 5. Frontend Rules

- **All new UIs**: Inertia + React. No new Blade screens.
- Existing Blade pages keep running until their replacement ships; public Website domain may stay Blade until its redesign.
- Shared React layout shell with RTL/LTR switching (English, Dhivehi/Thaana, Arabic) — reuse existing mcamara localization.
- PWA-ready from Phase 1B; keep platform abstraction (storage, recorder) per spec §6.4 for the later Capacitor wrap.

## 6. Testing Gates (CI-blocking)

| Gate | From |
|---|---|
| Pest arch tests: no cross-domain model imports, no SDKs in domain logic, thin controllers | Phase 0 |
| Student unification migration verification | S1 |
| Promotion/rollover feature test | S1 |
| Attendance + timetable conflict tests | S2 |
| Invoice generation + payment flow tests (BML webhook already tested — keep) | S4 |
| Phase 1A tests from the final spec — §46.1 Definition of Done, §53 Testing Scope, and §57 per-slice Definition of Done | 1A |
| Concurrent enrollment / seat-limit test; pinning tests | 1B |
| Arabic normalization tests | 1B/2 |

## 7. Risk Notes

- **Live data:** never drop `courses` columns, `registration_students`, or old quiz tables until migrated data is verified in production. Use additive migrations + backfill + verify + remove (3 separate deploys).
- **Hifz untouched rule:** Hifz is in active use — Phase 0 moves it without behavior change; resist refactoring it while moving. Its engine migration happens only after Phase 2 (§2b).
- **Two attendance tables are intentional** (class vs offering-session). Unify only at the reporting/Portal layer.
- **One Quran domain rule:** `Domains/Hifz` (Phase 0 move) and the spec's §52 module are the same thing at different stages — never let both exist as live systems; §2b retires the former.
- **Never duplicate Quran source tables:** spec §52.16–52.17 must be implemented against the repo's existing `surahs`/`quran_ayahs`/`quran_words`/mushaf tables.
- **AI is swappable infrastructure:** all pronunciation AI behind `Pronunciation` contracts + feature flags; engine and components must work fully with AI disabled (spec local/offline policy).
- **Don't over-split domains early** (spec §41): start with the map above; split further only when a real boundary appears.

---

## 8. Feature Benchmark — EduPage, Moodle/Canvas, PowerSchool-class systems

Benchmarked against EduPage's full feature set (audited from edupage.org) plus mainstream LMS/SIS platforms. Each feature is assigned a tier so nothing is forgotten.

### 8.1 Already in the roadmap (no change)

Timetable + substitutions (exists), admissions + online application (exists), payments (exists; EduPage-style bank-statement import is a Finance backlog item), grades, attendance with parent notification, certificates, exams, parent accounts seeing all children in one login, messaging, role-based rights (spatie), school webpage/CMS (exists), calendar/events (`Event`/`EventRegistration` models exist — wire them up in S2), e-learning tests/homework (the course engine's Activities/Assessments cover EduPage's e-learning, homework, and results features and go further).

### 8.2 Add to existing phases (data-model impact — plan now)

| Feature (EduPage equivalent) | Where | Notes |
|---|---|---|
| **Class register** — per-lesson record: topic taught + attendance, driven by timetable | S2 | Repo's `LessonLog` stub is exactly this; make it the S2 centerpiece: timetable slot → register entry → topic from teaching plan + attendance in one screen |
| **Teaching plans / curriculum plans** | S2 | Repo's `CoursePlan`/`PlanTopic` stubs; teacher creates plan at year start, picks topics in class register, copies plans to parallel classes / next year |
| **Absence notes** — parent submits, teacher approves; unexcused-absence tracking | S2 | `AbsenceNote` model exists; ties into attendance + parent portal |
| **Behavior & notices** — compliments, notes, behavior grades, parent-visible | S2/S3 | New `behavior_records` table (the "discipline records" gap) |
| **Curriculum standards** — define standards, tag lessons/questions, coverage + per-standard results analytics | S3 + Phase 2 | Add `standards` + tagging pivots; question bank questions get optional standard_id so results can be analyzed per standard |
| **Gradebook** — weighted assignment→final grade rules, teacher matrix view | S3 | Grade-weighting config per exam/assignment type |
| **Competency-based evaluation** (incl. self-evaluation) | S3 | Alternative grading scheme alongside marks — design `grade_scales` to support it |
| **Rubrics** for teacher-marked work | Phase 2 | Design into teacher review from the start |
| **Report designer / report cards** | S3 | Renderer behind interface (shared with certificates) |
| **Requests/approvals workflow** — teacher leave, parent requests; approval → auto absence + cover list | S2 | Generalize existing substitutions into a small Requests module (also covers the "staff leave" gap) |
| **Room/resource booking** + clash detection | S2 | Rooms become first-class in timetable; ad-hoc booking |
| **Event/elective registration** — min/max seats, parent confirmation, second round | S2/Portal | `EventRegistration` exists; reuse Offerings seat-limit logic |
| **Awards & achievements** — batch award certificates, publish on website | S3/Website | Reuses certificate renderer |
| **Parent-teacher meeting booking** — time slots, registration | Portal (after S2) | |
| **Admin overview dashboard** — ungraded classes, unfilled registers, plan adherence | After S2/S3 | Reads other domains' public contracts |
| **Message read receipts / "claim receipt"** | Notifications | Add `read_at`/acknowledgement to messaging |
| **Multi-branch** | S1 decision | **Recommendation: start single-institute.** Keep the existing `School` model as a future-safe reference column, but do not complicate every migration/policy with tenancy unless multi-branch is truly required. Decide before S1 migrations and record an ADR |
| **Sibling discounts, scholarships, fee waivers** | S4 | Fee structure adjustments/waiver tables |
| **Admission waiting lists** | Admissions backlog | Status addition to applications |
| **ID cards, transfer certs, document generation** | S3+ | Same renderer interface |

### 8.3 Deferred backlog (no schema pain — bolt on later)

Discussion forums / per-course Q&A; gamification & badges; bank-statement import & auto-matching; interactive in-class presentations (students answer live from phones); material sharing library between teachers (Phase 2 question bank partially covers this); student progress comparison analytics (class/subject/time); library module.

### 8.4 Optional / likely skip for an institute (decide explicitly)

Canteen/meals, transport, hostel, hardware access control (card readers — but keep attendance design able to accept an external check-in source later, so a future device integration is just another attendance writer behind an interface).

> Architecture note: the component registry deliberately adopts **Moodle's activity-module plugin pattern** (core knows only contracts; lecturers compose courses from a component palette; gradable components feed one gradebook via a grade-item contract) — while avoiding Moodle's weaknesses (no course/offering split, poor content versioning).
>
> Positioning note: EduPage's strength is the timetable→class-register→attendance→parent-notification loop — S2 replicates that loop. EduPage's e-learning is weaker than this spec's course engine (no course/offering split, no content versioning, no delivery modes); the course engine is the differentiator, and RTL/Thaana/Arabic support exceeds all benchmarked systems.

### 8.5 SIS benchmark — PowerSchool, Gibbon, openSIS, RosarioSIS, Classter/Fedena (student-management depth)

The EduPage benchmark covered the school *operations* loop; this pass benchmarks **student information system** depth. The repo's `Student` model already has a good base (trilingual names EN/AR/DV, national ID, one emergency contact, photo, status, admission date) — better than most starting points. Gaps by tier:

#### Add to phases (schema impact — plan now)

| Feature (benchmark source) | Where | Notes |
|---|---|---|
| **Rich student profile** — medical info (allergies, conditions, doctor), multiple emergency contacts, document vault (birth cert/ID/photos via Media domain) | S1 | Extends the existing `students` base during unification; documents reuse Media domain |
| **Enrollment status lifecycle + audit** (PowerSchool/openSIS) — active/withdrawn/transferred/graduated with dated status history | S1 | Status enum exists; add `student_status_history`; feeds reporting + alumni later |
| **Custom fields engine** (Gibbon/openSIS) — admin-definable fields on students/applications without migrations | S1 | One `custom_field_definitions` + values table; pays off across every program type the institute runs |
| **Staff profiles** — qualifications, contracts, documents | S1 (People) | Same profile/document pattern as students; full HR incl. payroll follows in Phase S5 |
| **Student notes/log with confidentiality levels** (Gibbon) — counseling vs teacher vs admin visibility | S2/S3 | Distinct from behavior records; policy-gated visibility |
| **Cumulative transcript / historical academic record** (PowerSchool) — grades across years, optional GPA | S3 | Report cards are per-term; add the across-years view + transcript document via the same renderer |
| **Payment plans / installments** (Classter/Fedena) | S4 + spec Phase 4 | Invoices payable in scheduled installments — high value for an institute; design `invoice_installments` into Finance from the start so course-offering payments inherit it |
| **Privacy & consent** (EduPage GDPR) — consent records (photos/media use), data-retention policy, guardian-scoped access | S1 + Phase 0 ADR | Cheap now, painful retrofitted; also governs Pronunciation-AI training-sample consent (spec §51.17) |
| **Data exports** — CSV/Excel export on every major listing | All phases (build rule) | Trivial per-screen if made a standing rule; institutes live in Excel |

#### Deferred backlog

Alumni tracking (graduated status from S1 makes this a view + contact info later); clubs/co-curricular enrollment (`EventRegistration` covers the core); individual education plans / special-needs module (Gibbon "Individual Needs"); full health module (immunizations, medication administration, nurse visits) — basic medical profile in S1 suffices for an institute; ad-hoc report/query builder (openSIS) — S3 report designer + exports cover the need first.

#### Skip (consistent with §8.4)

Cafeteria, hostel, transport, library, district/state government reporting. (Payroll moved into scope — Phase S5.)

> Reference-implementation note: when building each module, read the corresponding open-source code — **Gibbon** (PHP, teacher-designed) for student profiles, markbook, and individual-needs schema; **openSIS** for transcript/GPA structures; **Moodle** for component/plugin and gradebook contracts. Borrow schema ideas, not code.

### 8.6 Website benchmark — course-promotion conversion + Islamic content

Audit of `resources/views/public/*`: the site already has the standard skeleton (hero, open courses, why-us, gallery, testimonials, news, events, CTA; course pages with description, instructors, schedule, FAQ, related courses; articles/events/admissions/search; trilingual). Benchmarked against top 2026 education landing-page practice, the gaps are the **persuasion layer**, not structure.

#### Phase W1 — Conversion layer (can ship anytime; pure Website domain, no engine dependency)

| Change | Notes |
|---|---|
| Trust signals above the fold | accreditation/ministry registration, years operating, student count, partner logos |
| **Urgency from existing data** | show "N seats left" + enrollment-deadline countdown — `courses.seats`/`enrollment_deadline` already exist, currently unused on the page |
| Outcome-led course pages | "What you'll be able to do" section; per-course testimonials (currently global only) |
| Sticky mobile enroll CTA + low-friction lead capture | download syllabus, "Ask on WhatsApp" (Maldives-essential), free intro lesson form |
| Pricing clarity | early-bird display from existing `registration_fee_*` fields |
| SEO structured data | schema.org Course/Organization markup, OG tags, sitemap |
| Analytics on the funnel | track view→enroll-click→registration; iterate sections by data |

#### Phase W2 — Islamic Daily Content engine (Website domain)

One curated engine, not separate features: `daily_content` (type: **ayah / hadith / famous saying / reminder**; publish date; trilingual EN/DV/AR; source + attribution; status) with:

- **Ayah with meaning:** reuses existing `quran_ayahs`/`surahs` tables + new `quran_translations` (per-language) — never duplicate Quran source tables (§2b rule); admin picks the ayah, translation auto-attaches
- **Hadith:** admin-curated only, with mandatory source (collection, number) and grading — never auto-generated or scraped
- Homepage daily widget + archive pages + share cards (image generation for social/WhatsApp sharing)
- **Daily reminders subscription:** opt-in delivery via existing notification channels (SMS/email) + PWA push (Phase 1B service worker); per-user channel + content-type preferences
- **Research & publications:** extend existing articles/posts with categories, authors (link to instructor profiles), and PDF attachments via Media domain
- **Prayer times + Hijri date widget** — consumes `PrayerTimeProviderInterface` from **Phase W3** (not owned by Website)

Timing: W1 anytime (highest ROI now, while courses are promoted manually); W2 after Phase 0 (uses domain structure + notification contracts); **W3 after Phase 0** (like W2 — prayer data engine + SMS broadcast; broadcast needs S1 `consents` + People/Identity contacts + `SmsSenderInterface`); curriculum-preview sections on course pages arrive automatically with Phase 1A's public course outline.

#### Phase W3 — Prayer times engine + prayer-time SMS broadcast (PrayerTimes domain)

Per `docs/W3_SPEC.md` — replicate Bake&Grill model (`salat.db` import, 366-day categories, leap-year resolver, island offsets, versioned cache, Haversine nearest-island). Public page + JSON API + admin dashboard via `PrayerTimeProviderInterface`; deprecate `IslamicCalendarService::getPrayerTimes()`. Optional **prayer-time SMS broadcast** (daily / date-range / change-only) with mandatory preview, full audit, consent gating (`prayer_reminders`), fake SMS sender + `Http::preventStrayRequests()` in tests. Engine stays subject-ignorant; taxonomy unrelated — W3 is independent of course engine phases.

---

## 9. Akuru Knowledge Library (L-track)

A reading, publishing, and digital-sales platform inside the same monolith, per the "Akuru Knowledge Library — Complete Project Plan" (separate document; this section integrates it with corrections). Books, articles, research papers, and course materials; free and paid; protected online reader; writer marketplace with editorial approval and commission.

### 9.1 Integration corrections (override the library plan where they conflict)

1. **Commerce is platform-wide, not library-scoped.** The plan's `library_wallets` / `library_gift_cards` / `library_discount_codes` become `Domains/Commerce` tables without the `library_` prefix, operating on a polymorphic **purchasable** (library item, course offering, …). Gift cards and discounts work across courses AND the library; one campaign can span both. Admin toggle controls which product types accept wallet payment. Build once, enhance independently.
2. **Writer is a role, not a person-type.** No separate `library_authors` identity: writer = role + `writer_profiles` extension on the unified People/User record (the §3.1 rule). An instructor who writes a book is one person with two roles.
3. **Library/course content boundary:** course teaching content lives in lessons (engine); the Library holds **standalone publications**. "Course-included" reading = a `library_access_grants` row created by listening to the engine's `EnrollmentCreated` event — never a second content store.
4. **Domain collapse:** the plan's eight domains become two — `Library` (catalog, protected reader, progress, writers, editorial, sales/payouts) and shared `Commerce` — reusing existing Finance (BML, invoices), Media (private storage), Notifications, Identity/People.

### 9.2 Rules to preserve verbatim from the plan

- Protection honesty: promise "protected online reading — downloading and copying restricted," never "impossible to copy" (screenshots/cameras can't be stopped). Private storage, no direct file URLs, signed expiring page tokens, per-user dynamic watermark (name/mobile/date), session/device limits, abuse logging.
- Access depends on **BML webhook confirmation**, never the return URL alone.
- Gift cards and wallet are **payment methods**; discount codes are **price reductions**. Discounts never apply to gift-card purchases. Wallet transactions are append-only (reversals, never deletes).
- Discount funding source per code/campaign: **shared (default) / Akuru-funded / writer-funded** — determines writer-earnings math.
- Writer earnings become payable only after the refund window closes; payouts admin-approved.
- Writers see aggregate analytics only; reader notes/highlights are private.
- No writer publishes without editorial approval; Islamic content gets scholarly review before publishing.
- Gift card codes stored hashed; plain code shown once.

### 9.3 L-phases (parallel track; starts only after Phase 0 + W1/W2)

| Phase | Scope | Depends on |
|---|---|---|
| **L1 — Catalog + free reading** | library items/categories/tags, public listing + detail pages, admin upload, free content, search | Phase 0 (domains, Inertia), Media |
| **L2 — Protected reader** | private storage, PDF→protected page conversion, watermarking, reading progress, continue-reading, bookmarks/notes/highlights, RTL/Thaana reader | L1 |
| **L3 — Paid content** | purchasable contract, BML checkout, webhook access grants, purchase history, invoices, sales reports | L2, Finance |
| **L4 — Commerce module** | wallet, gift cards (purchase/redeem), discount codes, free-access coupons, campaigns — **platform-wide**; course payments (spec Phase 4) adopt the same module | L3 |
| **L5 — Writer portal** | applications + approval, profiles (role on People), upload/drafts, editorial workflow, submission statuses, revision loop | L1–L3 |
| **L6 — Writer sales & payouts** | commission engine (incl. funding-source rules), earnings lifecycle (pending→available→paid), payout reports | L4, L5 |
| **L7 — Research workflow** | reviewer role, review assignment, peer-review loop, citations; (DOI, journals, subscriptions, bundles, audiobooks = post-L7 backlog per plan §38) | L5 |

Launch strategy per the plan: Akuru-owned content first → paid → invite trusted writers → gift cards/discounts → public writer applications → research. MVP = plan §46.

### 9.4 L-track risk notes

- **Wallet balances and unredeemed gift cards are financial liabilities** on Akuru's books — surface them in Finance reports from day one; verify Maldives Monetary Authority rules on stored value before public launch (ADR).
- **Writer payouts create tax/accounting obligations** — confirm treatment before L6 ships; until then, sales can accrue with payouts disabled.
- This is a third product competing for the same build hours as S1–S5 and the course engine — the L-track must never block an S-phase or engine phase; pause L before pausing S/1A.
