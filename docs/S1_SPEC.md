# S1 Spec — People Unification + Academic Backbone

**Phase:** S1 (first phase after Phase 0; prerequisite for everything else)
**Domains touched:** People, Academics, Identity, Media (consumer)
**Format follows the course spec's pattern:** tables → fields → rules → actions → UI → tests → DoD.

**Why S1 is first:** every later phase (attendance, exams, fees, offerings, library access, Hifz migration) keys on *one* student identity and on academic years/terms. These are the most consequential schema decisions in the project.

---

## Slice S1.1 — Unified Student

### Current state (from repo audit)

- `students`: user_id, school_id, class_id, student_id (school number), trilingual first/last names (EN/AR/DV), date_of_birth, gender, national_id, phone, address, emergency_contact_name/phone, photo, admission_date, status enum(active/inactive/graduated/transferred), notes.
- `registration_students`: user_id (nullable, unique), first_name, last_name, dob, gender, national_id, passport. Referenced by `course_enrollments.student_id` and `student_guardians.student_id`.
- Two guardian systems: `parent_guardians` (rich profile keyed to users) and `student_guardians` (pivot: registration_students ↔ guardian users, relationship, is_primary).

### Target schema

**`students` (extend existing — it's the richer of the two):**

| Change | Detail |
|---|---|
| Make school-only fields nullable | `school_id`, `class_id`, `student_id`, `admission_date` become nullable — a course-only student has no class |
| Add from registration_students | `passport` (string 50, nullable) |
| Add | `email` (nullable — child may have none), `nationality` (nullable, default 'MV'), `place_of_birth` (nullable) |
| Add | `legacy_registration_student_id` (unsignedBigInteger, nullable, indexed) — transition key, dropped in S1 cleanup deploy |
| Status enum → string-backed PHP enum | values: `prospective, active, inactive, graduated, transferred, withdrawn` (add prospective + withdrawn) |
| Medical block | `medical_conditions` (text, nullable), `allergies` (text, nullable), `doctor_name`/`doctor_phone` (nullable) — simple fields, not a health module |

**New `emergency_contacts`** (replaces the single name/phone pair; keep old columns until cleanup deploy):
`id, student_id FK, name, phone, relationship, priority (1=first call), notes, timestamps`

**New `student_status_history`:**
`id, student_id FK, from_status, to_status, reason (nullable), effective_date, changed_by FK users, timestamps`
Written automatically by a `ChangeStudentStatusAction` — never raw status updates (arch-test: `status` column not mass-assignable).

**Guardians — unify to one system:**
- Keep `parent_guardians` as the profile (rich, trilingual — it wins).
- New pivot `guardian_student` (`guardian_id FK parent_guardians, student_id FK students, relationship enum(father,mother,guardian,grandfather,grandmother,uncle,aunt,other), is_primary bool, can_pickup bool default true, financial_responsible bool default false, unique(guardian_id,student_id)`).
- `student_guardians` (old pivot) is migrated then dropped in cleanup deploy.

**Documents vault:** no new table — use Media domain: `student_documents` view = media attached to student with `document_type` (birth_certificate, national_id, passport, photo, other) + `expires_at` (nullable; powers permit/ID expiry alerts later for HR too). Implement as a polymorphic `documents` table in Media: `id, documentable_type/id, media_path, document_type, title, expires_at, uploaded_by, timestamps` — HR (S5) and writers (L5) reuse it.

### Data migration (3-deploy rule)

**Deploy 1 — additive:** new columns + tables; backfill job:
1. For each `registration_students` row, match to `students` by (a) `user_id`, else (b) `national_id`, else (c) exact name + dob. Log match method.
   **Superseded by ADR-007 (A2):** skip `national_id` when blank, duplicated across RS or `students` rows, or in `config/unification.php` placeholders; a unique `national_id` hit whose name+dob contradicts the candidate is **ambiguous** (no fallthrough). See `docs/adr/ADR-007-s11b-unification-backfill.md`.
2. Match found → set `legacy_registration_student_id`, fill missing fields (passport etc.).
3. No match → create new `students` row (status by enrollment state: active if any active course_enrollment, else prospective), nullable school fields.
4. Migrate `student_guardians` → create/find `parent_guardians` by guardian user, insert `guardian_student` rows.
5. Add `course_enrollments.unified_student_id` (nullable FK students) and backfill via the mapping. (Final rename to `student_id` happens in the §3.4 offerings migration — don't rename twice.)

**Deploy 2 — switch reads:** all code paths read `students` (+ `unified_student_id`); `RegistrationStudent` model marked deprecated; dual-write kept ON.

**Deploy 3 — cleanup (≥2 weeks stable):** drop dual-write, drop `student_guardians`, drop old emergency columns. `registration_students` table is renamed `_archived_registration_students`, not dropped.

**Verification script (required, run after deploy 1 & 2):** counts match (every registration_student maps to exactly one student); every course_enrollment has unified_student_id; no guardian links lost (old pivot count == new pivot count); ambiguous matches (>1 candidate) listed for manual resolution — script outputs a report file; zero unresolved = gate for deploy 2.

### Rules

1. One human = one `students` row, regardless of how many programs/courses/classes.
2. A student need not have a class (course-only) or a user account (small child — guardian's account is the login).
3. Sensitive fields (medical, documents) readable only by roles with `students.view-sensitive` permission; guardians see their own children only.

---

## Slice S1.2 — Custom Fields Engine

**`custom_field_definitions`:** `id, entity_type (students|staff|admission_applications), key (slug, unique per entity), label_en/label_dv/label_ar, field_type enum(text, textarea, number, date, select, multiselect, boolean), options json (for selects), required bool, show_in_profile bool, show_in_admission_form bool, sort_order, active bool, timestamps`

**`custom_field_values`:** `id, definition_id FK, entity_type, entity_id, value json, timestamps, unique(definition_id, entity_type, entity_id)`

Rules: definitions are admin-managed (Settings UI inside People admin); deleting a definition soft-deletes (values preserved); values validated against field_type + required on save via one `SaveCustomFieldValuesAction` reused by student form, staff form, admission form. Rendered automatically by one React `<CustomFields>` component.

---

## Slice S1.3 — Consent & Privacy

**`consents`:** `id, person_type/person_id (student or guardian user), consent_type (photo_media_use, ai_training_samples, data_processing, marketing_messages), granted bool, granted_by FK users (guardian for minors), granted_at, revoked_at nullable, source (admission_form|portal|admin), timestamps`

Rules: consent is per-type, revocable, history kept (new row on change, never update granted→false in place). `photo_media_use` gates gallery/website usage of student photos; `ai_training_samples` pre-builds the spec §51.17 requirement. ADR-002: data-retention policy (what is deleted/anonymized when a student leaves, and when).

---

## Slice S1.4 — Staff Profiles (People)

**`staff_profiles`:** `id, user_id FK unique, staff_number nullable, trilingual names (same pattern as students), date_of_birth, gender, national_id, passport, nationality, phone, address, photo, joined_date, employment_type enum(full_time, part_time, contract, volunteer), status enum(active, on_leave, ended), timestamps`

**`staff_qualifications`:** `id, staff_profile_id FK, title, institution, year, document (media FK nullable), timestamps`

Existing `Teacher` model becomes a thin Academics-facing relation; teaching-specific data (subjects taught) stays in Academics. Documents + expiry via the shared `documents` table (S1.1). Contracts/leave/payroll are S5 — only the profile foundation ships in S1.

---

## Slice S1.5 — Academic Year & Term Backbone

### Current state
`academic_years`: name, start_date, end_date, is_current bool, description, **terms json** (to be replaced), timestamps. `course_enrollments.term_id` = bare nullable int, no FK.

### Target schema

**`academic_years` (alter):** add `status` enum(`upcoming, active, closed`) default upcoming; migrate `is_current=true` → active; **drop `terms` json** after terms table backfill; unique constraint: only one `active` row (enforced in action + partial check).

**New `terms`:** `id, academic_year_id FK, name (Term 1 / Semester 1...), start_date, end_date, status enum(upcoming, active, closed), sort_order, timestamps` — backfill from the json column where present.

**`course_enrollments`:** convert `term_id` to real FK → `terms` (verify orphan values first; orphans get a backfilled "Legacy" term per year). Drop generated `term_key` column, replace usages.

**`classes` (alter):** add `academic_year_id FK` (backfill: all existing → current active year), `section` (nullable string, e.g. "A"), `capacity` (nullable int), `class_teacher_id` (nullable FK staff_profiles). Unique(name, section, academic_year_id).

**New `class_student`:** `id, class_id FK, student_id FK, academic_year_id FK (denormalized for queries), enrolled_at, left_at nullable, status enum(active, promoted, left), timestamps, unique(class_id, student_id)` — replaces the single `students.class_id` (kept + dual-written until S1 cleanup; history needs the pivot).

### Actions

| Action | Behavior |
|---|---|
| `ActivateAcademicYearAction` | closes currently active year/terms? NO — validates no other active year, sets active; closing is separate |
| `CloseAcademicYearAction` | requires all its terms closed; archives |
| `PromoteStudentsAction` | input: mapping old class → new class (next year) + per-student overrides (repeat / leave / graduate). Transactionally: closes class_student rows (status promoted), creates new rows in target classes, writes student_status_history where status changes (graduated/withdrawn), report of every student's outcome. Dry-run mode REQUIRED before commit. |
| `ChangeStudentStatusAction` | single gateway for status changes (S1.1) |

### Rules
1. Every future record that is time-scoped (attendance, exams, invoices, timetables) carries `academic_year_id` (and `term_id` where relevant) — standing rule for S2+.
2. Exactly one active academic year; 0..1 active term per year.
3. Promotion is irreversible in UI (reversal = manual admin correction with audit) — hence mandatory dry-run.

---

## UI (Inertia + React — first production React screens)

1. **Students index** — search (any language name, national ID, student number), filters (status, class, year), CSV export (standing rule).
2. **Student profile** — tabs: Overview (incl. custom fields), Guardians, Documents, Medical (permission-gated), Status history, Consents. Edit per tab.
3. **Guardian management** — attach/detach guardian, relationship, primary/pickup/financial flags; guardian sees children list in Portal (read-only this phase).
4. **Academic years & terms** — CRUD + activate/close with confirmations.
5. **Classes** — CRUD per year, capacity, class teacher, student assignment (search + add), roster view.
6. **Promotion wizard** — pick source year → target year, map classes, per-student overrides, dry-run report screen, confirm.
7. **Custom field definitions** — admin CRUD.
8. **Staff profiles** — index + profile with qualifications/documents.

All screens trilingual-ready (labels via existing localization), RTL-safe.

---

## Tests (CI gates for S1)

1. Migration verification script green on a production-data copy (mandatory before deploy 2).
2. Unification edge cases: match by user_id / national_id / name+dob; no-match creates prospective; ambiguous goes to report.
3. Guardian migration: counts preserved; primary flags preserved.
4. `PromoteStudentsAction`: feature test covering promote/repeat/graduate/leave in one run + dry-run produces no writes.
5. Single-active-year invariant test; term FK integrity test.
6. Custom fields: validation per type; required enforcement; admission form rendering.
7. Status changes only via action (arch test) + history row created.
8. Permission tests: sensitive tabs blocked without `students.view-sensitive`; guardian sees own children only.

## Definition of Done

- [ ] 3 deploys completed; `RegistrationStudent` deprecated with zero remaining read paths; verification report archived in `docs/migrations/`.
- [ ] All S1 screens live in React; corresponding legacy Blade screens removed.
- [ ] Backbone rule documented in ROADMAP risk notes and enforced in code review checklist: new time-scoped tables must carry year/term FKs.
- [ ] STATUS.md + ADR-002 (retention) + ADR-003 (promotion semantics) recorded.
- [ ] S2 unblocked: attendance/timetable can key on class_student + terms.

**Out of scope for S1:** attendance, timetable, exams, fees (S2–S4); offerings split (§3.4, Phase 1B); HR beyond profiles (S5); any Hifz change.
