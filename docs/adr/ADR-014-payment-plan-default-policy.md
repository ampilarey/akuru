# ADR-014: Payment-plan default policy

## Context

S4.4 adds installment plans for school-fee invoices. The S4 spec asked for
“ADR-006” on defaulting; ADR-006 already records the unified-student schema.
Plans need a single allocation gateway and a clear rule when an installment
is overdue. Course and library access are owned by other domains.

## Decision

1. **Default is a follow-up flag, not a lockout.** When any installment is
   more than `finance.plan_default_days` (default 14) past due, the plan
   status becomes `defaulted`. School access is never cancelled from Finance.
   Course / library access rules stay in those domains.
2. **`AllocatePaymentAction` is the only writer** of installment
   `paid_amount` / status and of the matching invoice `paid_amount`.
   Payments apply to the oldest unpaid (or partial) installment first.
   An amount larger than the remaining invoice balance is rejected.
3. **Phase 4 course payments inherit this pattern** (plan + installments +
   oldest-first allocation). They must not invent a second allocator.

## Consequences

- Collections and the parent portal show plan progress even after default.
- A defaulted plan can still be completed by later payments.
- Concurrent allocations take a row lock on the invoice (`lockForUpdate`).
