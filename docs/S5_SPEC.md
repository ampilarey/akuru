# S5 Spec — Full HR

**Phase:** S5 (after S4 — payroll consumes Finance + staff attendance; staff profiles exist from S1.4; leave approvals extend S2.6 requests)
**Domains:** HR (new), People (read/extend), Finance (payroll posting), Notifications, Media (documents)
**Build order inside S5 is strict:** attendance → leave → contracts/compliance → recruitment → performance → payroll last.

---

## Slice S5.1 — Staff Attendance

**New `staff_attendance`:** `id, staff_profile_id FK, date, check_in time nullable, check_out time nullable, status enum(present, absent, late, half_day, on_leave, holiday), source enum(manual, self, external, import), minutes_late nullable, marked_by nullable, remarks, timestamps, unique(staff_profile_id, date)`

Reuses the S2 writer-contract pattern: `StaffAttendanceWriterInterface` — manual admin entry, staff self check-in (Portal, optional setting, IP/geo note logged), CSV import, future biometric device are all writers. Approved leave (S5.2) auto-fills `on_leave`; `calendar_days` holidays auto-fill. Monthly summary per staff feeds payroll (S5.6). Reports: late patterns, absence summary, per-department if `department` field used (add nullable `department` + `designation` to `staff_profiles`).

## Slice S5.2 — Leave Management

**New `leave_types`:** `id, name (+trilingual), code (annual, sick, family, hajj_umrah, maternity, paternity, unpaid, other), days_per_year decimal(5,1), carry_over_max decimal(5,1) default 0, requires_document bool (sick > N days), paid bool default true, active, timestamps`
**New `leave_entitlements`:** `id, staff_profile_id FK, leave_type_id FK, academic_year_id FK (or calendar year — ADR-007 decides the leave year basis), entitled_days, carried_over_days, adjusted_days (admin +/− with reason), timestamps, unique(staff, type, year)`
**New `leave_requests`:** extends S2.6 `requests` (type `teacher_leave` generalized to `staff_leave`): payload gains `leave_type_id, from_date, to_date, half_day bool, document_id nullable`. Approval handler now: (1) checks balance (entitled + carried − taken ≥ requested, unless type unpaid), (2) creates `teacher_absences` (existing, drives substitutions) when staff teaches, (3) writes `staff_attendance` on_leave rows, (4) decrements via **`leave_ledger`** (append-only: `id, entitlement_id FK, request_id nullable, days +/-, reason, timestamps`) — balance is always a ledger sum, never a mutable column.

Year-end `CarryOverLeaveAction`: min(remaining, carry_over_max) → next year, ledger entries both sides, report. Balances visible to staff in Portal; admin balance screen + adjustments (audited).

## Slice S5.3 — Contracts & Compliance

**New `staff_contracts`:** `id, staff_profile_id FK, contract_type enum(permanent, fixed_term, part_time, consultant), start_date, end_date nullable, probation_until nullable, basic_salary decimal(10,2), allowances json ([{name, amount}]), working_hours_per_week, document_id FK nullable, status enum(active, expired, terminated, superseded), timestamps`
One active contract per staff (validation); new contract supersedes old (history kept).

**Compliance via shared `documents` table (S1.1)** with `expires_at`: work permits, visas, registration cards for expatriate staff. `ExpiringDocumentsReport` + scheduled notifications at 90/60/30 days to HR admin + the staff member. Nothing expat-specific hardcoded — it's just document types with expiry.

## Slice S5.4 — Recruitment & Onboarding

**New `job_postings`:** `id, title (+trilingual), description, requirements, department, employment_type, closes_at, status enum(draft, published, closed), public bool (shows on Website careers page), timestamps`
**New `job_applications`:** `id, job_posting_id FK, name, mobile, email, cv_document_id, cover_note, status enum(received, shortlisted, interview, offer, hired, rejected, withdrawn), stage_notes json (timeline), reviewed_by, timestamps` — mirrors the Admissions pattern deliberately.
`HireApplicantAction`: creates user + staff_profile (S1.4) + onboarding checklist instance.
**New `onboarding_checklists`** (template json in Settings) → `staff_onboarding_items`: `id, staff_profile_id, item, done bool, done_by, done_at` (contract signed, documents collected, account roles, induction…). Offboarding mirror template (revoke roles, exit form, final-pay flag for payroll).

## Slice S5.5 — Performance & CPD

**New `appraisal_cycles`:** `id, name, academic_year_id, opens_at, closes_at, template json (sections/criteria/rating scale), status` → **`appraisals`:** `id, cycle_id, staff_profile_id, appraiser_id, ratings json, strengths, development_areas, goals json, status enum(draft, submitted, acknowledged), acknowledged_at, timestamps` (staff acknowledges, comments allowed).
**New `lesson_observations`:** `id, staff_profile_id, observer_id, date, class_id nullable, subject_id nullable, criteria json, summary, shared_with_staff bool, timestamps` — links to S2 register data read-only.
**New `cpd_records`:** `id, staff_profile_id, title, provider, hours decimal, date, certificate_document_id nullable, timestamps` + per-staff CPD summary.

## Slice S5.6 — Payroll (last; own feature flag `payroll.enabled`)

**New `payroll_periods`:** `id, year, month, status enum(open, processing, review, approved, paid, locked), processed_by, approved_by, paid_at, timestamps, unique(year, month)`
**New `payslips`:** `id, payroll_period_id FK, staff_profile_id FK, basic_salary, allowances json, deductions json, gross, employee_pension, employer_pension, tax_withheld, unpaid_leave_deduction, net_pay, document_id nullable, status enum(draft, final), timestamps, unique(period, staff)`
**New `payroll_rules` (Settings-managed, never hardcoded — rates change):** pension employee %, pension employer %, tax brackets json, rounding rule, working-days basis.

`PayrollCalculatorInterface` (per WORKING_RULES rule 4) with `MaldivesPayrollCalculator` first implementation: gross = contract basic + allowances; unpaid-leave deduction = (basic / working days in month) × unpaid days (from leave ledger + attendance); pension per MRPS configured rates; tax per configured brackets; everything traceable in the payslip json. `RunPayrollAction(period)`: snapshot contracts + attendance + leave → draft payslips → review screen (diff vs last month highlighted) → approve (locks inputs) → mark paid (bank-transfer CSV export, receipt of posting into Finance reports) → lock.

**Hard rules:** payslips immutable after final (corrections = adjustment line next period); period lock blocks retro attendance/leave edits for that month (or flags them for next-period adjustment); parallel-run requirement from ROADMAP §S5 honored — keep `payroll.enabled` off in production until two parallel cycles match the existing process (DoD gate).

## Tests (CI gates)
1. Leave ledger: balance = ledger sum property test; over-balance request rejected; unpaid type bypasses balance; carry-over caps.
2. Approval side-effects: teacher leave → teacher_absences + attendance rows + ledger, all-or-nothing transaction.
3. Attendance uniqueness + holiday/leave auto-fill precedence.
4. Contract: single-active invariant; supersede keeps history.
5. Expiry notifications fire at configured horizons once each.
6. Payroll golden tests: known contract + leave inputs → expected payslip (multiple scenarios incl. mid-month join/exit, unpaid days, rate change between periods); immutability + lock behavior; double-run idempotency.
7. Permission matrix: staff see own payslips/balances only; `hr.manage` vs `payroll.run` vs `payroll.approve` separated (maker-checker).

## DoD
- [ ] Staff month-in-the-life works end to end: check-ins recorded → leave requested/approved (balance moves, substitution created) → permit-expiry alert fires → appraisal completed → payroll period run, reviewed, approved → payslip PDF (trilingual) downloaded by staff in Portal → bank CSV exported.
- [ ] Two parallel payroll cycles match the manual process before `payroll.enabled` goes on (recorded in STATUS.md).
- [ ] ADR-007 (leave year basis) + ADR-008 (payroll rounding + adjustment policy) recorded.
**Out of scope:** wallet/Commerce anything, biometric device drivers (writer interface ready), org-chart/department hierarchy beyond a field, recruitment public-site styling beyond a careers list (Website W-track).
