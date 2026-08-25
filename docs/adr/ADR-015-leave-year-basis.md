# ADR-015: Leave year basis

## Context

S5.2 leave entitlements need a year key. The S5 spec asked for “ADR-007”
to choose academic year vs calendar year. ADR-007 already records the
S1.1b student-unification backfill. Rule 10 says time-scoped records
carry the academic-year backbone.

## Decision

Leave entitlements, carry-over, and ledger entries are scoped to
`academic_year_id`. A leave year is the institute academic year, not
the calendar year. `CarryOverLeaveAction` moves remaining days from one
academic year to the next.

## Consequences

- Payroll (S5.6) and attendance already key off academic years, so
  unpaid-leave deductions line up with the same backbone.
- A staff member who joins mid-year still uses the current academic
  year’s entitlement (admin may adjust via the ledger).
- Calendar-year statutory reports, if ever required, are derived reads
  — they do not become a second entitlement key.
