# S4 Spec — Finance: Fees, Invoicing & Payment Plans

**Phase:** S4 (after S1; benefits from S2's class data; independent of S3)
**Domains:** Finance (primary), People, Academics (read), Notifications, Portal
**Repo head start (better than expected):** `invoices` (numbers, statuses, totals, paid_amount), `invoice_lines` (fee_item link, qty, discounts), `fee_items` (type, frequency, is_mandatory, applicable_grades json), `payments` (BML, webhook-confirmed statuses, merchant_reference) + `PaymentProviderInterface` all exist. S4 is mostly: fee *structures*, generation, installments, receipts, and UI — the tables just need scoping + wiring.

**Note:** `payments.student_id` references `registration_students` — repointed during S1's unification (S1 deploy 1 adds the mapping; S4 assumes it's done).

---

## Slice S4.1 — Schema scoping & gaps

| Table | Change |
|---|---|
| `invoices` | add `academic_year_id` FK, `term_id` FK nullable (backbone rule); add `invoice_type` enum(`school_fees, course_fee, other`) ; add `payment_plan_id` FK nullable (S4.4); `student_id` now FK unified `students` |
| `fee_items` | replace `applicable_grades` json with explicit assignment via fee structures (kept during transition); add trilingual name columns |
| `invoice_lines` | no change (already right) |
| New `receipts` | `id, invoice_id FK, payment_id FK nullable, receipt_number unique, amount, method enum(bml, cash, transfer, wallet, gift_card, waiver), received_by, received_at, document_id FK nullable (rendered PDF), timestamps` — cash/transfer recorded manually with permission `finance.record-manual-payment` |

## Slice S4.2 — Fee Structures

**New `fee_structures`:** `id, academic_year_id FK, name, applies_to enum(class, all_classes), class_ids json nullable, status enum(draft, active, archived), timestamps`
**New `fee_structure_items`:** `id, fee_structure_id FK, fee_item_id FK, amount (overrides default_amount), frequency (inherits fee_item default, overridable), due_day smallint nullable (for monthly: day of month), is_mandatory bool, timestamps`

Rules: one active structure per class per year (validation). Optional items appear at invoice generation as toggles. Admin UI: structure builder per year, copy-from-last-year action.

## Slice S4.3 — Invoice Generation

`GenerateInvoicesAction(structure | class, period)`:
1. Resolve students from `class_student` (active).
2. Per student: create invoice (draft) with lines from structure items due in the period (frequency-aware: monthly items → one invoice per month or consolidated, per setting).
3. Apply **adjustments** (S4.5) before totals.
4. Idempotent: regeneration skips students already invoiced for (structure, period) — `unique(student_id, fee_structure_id, period_key)` on a generation log table.
5. Bulk review screen → "Issue" flips draft → sent + Portal/SMS notification to financially-responsible guardian (`guardian_student.financial_responsible` from S1).

Status automation: scheduled job marks `sent` → `overdue` past due_date; arrears report (by class, by guardian, aging buckets 30/60/90); reminder schedule (setting: days after due) via Notifications with throttle.

## Slice S4.4 — Payment Plans (installments)

**New `payment_plans`:** `id, invoice_id FK unique, total_amount, status enum(active, completed, defaulted, cancelled), created_by, approved_by nullable, timestamps`
**New `payment_plan_installments`:** `id, payment_plan_id FK, sequence, due_date, amount, paid_amount default 0, status enum(pending, partial, paid, overdue), timestamps`

Rules: installment amounts must sum to invoice balance at plan creation; payments allocate to oldest unpaid installment first (`AllocatePaymentAction` — single allocation gateway, unit-tested); invoice shows plan progress; defaulting (N days overdue, setting) flags for follow-up, never auto-cancels access to *school* (policy decision — record **ADR-014**; course/library access rules differ and live with their own domains). **Designed as the shared pattern spec Phase 4 course payments inherit.**

## Slice S4.5 — Discounts, Scholarships, Waivers (school fees)

**New `fee_adjustments`:** `id, student_id FK, academic_year_id FK, type enum(sibling_discount, scholarship, staff_child, hardship_waiver, other), basis enum(percent, fixed), value, applies_to enum(all_items, item_types json), approved_by, valid_from/valid_until, notes, status, timestamps`

Applied at generation time as invoice-line discount rows (transparent on the invoice). Sibling auto-suggestion: students sharing a financially-responsible guardian. **Boundary:** these are administrative fee reductions — *not* Commerce discount codes; when Commerce (L4) ships, promotional codes for course/library purchases live there; school-fee adjustments stay here. One sentence in code comments + arch note.

## Slice S4.6 — Payment + Portal

- BML checkout for invoices/installments through the existing `PaymentService` — webhook confirmation creates receipt + allocates (Rule 12 of WORKING_RULES).
- Manual receipt entry (cash/transfer) with permission + audit.
- Receipt PDF via `DocumentRendererInterface` (trilingual template).
- **Parent Portal:** invoices list (per child), balance, plan progress, pay-now (full / next installment), receipt downloads. Admin: collections dashboard (collected vs billed per class/month), arrears, CSV exports everywhere.
- Reconciliation report: payments ↔ receipts ↔ invoice balances; daily totals by method. (Bank-statement import = backlog, per roadmap.)

## Tests (CI gates)
1. Generation idempotency; frequency expansion (monthly × term length); optional-item toggles.
2. Allocation: partial payment → oldest installment; overpayment rejected; concurrent payment race (DB transaction + lock test).
3. Adjustment math: percent + fixed stacking order (fixed after percent, documented), validity windows.
4. Webhook double-delivery: second confirmation is a no-op (existing merchant_reference uniqueness leveraged).
5. Status automation: overdue flip + reminder throttle.
6. Permission matrix: manual receipts gated; guardians see own children only; financially-responsible targeting correct.

## DoD
- [ ] Real cycle: build structure → generate class invoices → issue → guardian pays one via BML sandbox and one by cash entry → receipts render (Thaana template verified) → arrears + collections reports correct → CSV exports work.
- [x] Payment-plan flow live incl. allocation; **ADR-014** (default policy) recorded. *(Drafted as ADR-006; that number was taken by unified-student.)*
- [ ] STATUS.md updated. **Out of scope:** Commerce module (L4), course-offering pricing (spec Phase 4), payroll (S5), bank import (backlog).
