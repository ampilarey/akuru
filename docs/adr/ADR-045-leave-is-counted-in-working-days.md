# ADR-045: Leave is counted in working days

## Context

`CountLeaveDaysAction` counted calendar days, so a leave across a weekend or
a public holiday spent entitlement on days nobody works. KNOWN_ISSUES
(2026-09-13) recorded it as an owner question: no spec said whether leave is
counted in calendar days or working days. On 2026-10-21 the owner said to
decide by best practice wherever a decision was needed.

## Decision

Leave is counted in **working days**:
- The weekend (Friday and Saturday, the Maldives' working week) is left out.
- The school calendar's holidays and closures in the academic years the
  leave touches are left out.
- A half day on a working day is half a day. On a day off it is nothing.
- A leave that falls only on days off is refused, as one with no days was
  before.
- Attendance records *on leave* only on the days the leave takes. A
  weekend inside it stays what it was.

`config/hr.php` holds the weekend and a switch:
- `HR_LEAVE_COUNTS=calendar` restores calendar counting for a school whose
  contracts say so.

## Consequences

- Balances last as long as the contract means them to. A week's leave costs
  five days, not seven.
- Payroll's unpaid-leave deduction reads the attendance rows
  (`leave_day_fraction`), so it follows: a weekend inside unpaid leave is
  not deducted.
- Leave approved before this keeps the days it was charged. No backfill:
  there are no real staff yet (ADR-021).
- A holiday added to the calendar after a leave is approved does not refund
  it. The office adjusts the balance on the balances screen, as for any
  correction.
