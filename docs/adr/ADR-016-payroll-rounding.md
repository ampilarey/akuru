# ADR-016: Payroll rounding and adjustment policy

## Context

S5.6 payslips need a single rounding rule and a correction policy. The
S5 spec asked for “ADR-008”; ADR-008 already records the S1.1c student
read switch. Rates (MRPS, tax brackets) stay in Settings — they are
never hardcoded.

## Decision

1. **Round half-up to 2 decimal places (Rufiyaa/laari)** after each
   derived amount: gross, unpaid-leave deduction, employee pension,
   employer pension, tax, and net. Intermediate products use PHP
   `round($value, 2)`.
2. **Payslips are immutable after `final`.** A correction is an
   adjustment line on the next open period, never an update or delete
   of a final payslip.
3. **Approving a period locks that calendar month.** Retro
   `staff_attendance` writes and leave approvals that touch a locked
   month are rejected. They must be handled as a next-period
   adjustment.
4. **`payroll.enabled` stays off** until two parallel cycles match the
   current manual process (DoD; recorded in STATUS.md by the operator).

## Consequences

- Golden tests pin known contract + leave inputs to exact 2-decimal
  outputs.
- Maker (`payroll.run`) and checker (`payroll.approve`) stay separate.
- Finance receives a posting receipt of totals; it does not invent a
  second money path.
