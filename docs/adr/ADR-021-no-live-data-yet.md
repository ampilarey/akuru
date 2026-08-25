# ADR-021: No live data yet — recalibrating the live-data rules

The 2026-08-25 slice brief asked for “ADR-011”. That number already records
class attendance modes (`docs/adr/ADR-011-attendance-modes-and-notification.md`).
This decision is **021**.

## Context

The plan treated Akuru as a live-data system: production-copy unification
gates, Hifz frozen because it is “in active use”, and the full rule-9
three-deploy dance (dual-write windows, “≥2 weeks stable” before cleanup).

Operator confirmation **2026-08-25:** there is **no production system**.
Nothing is live. No real students, payments, or Hifz users exist anywhere.
`test.akuru.edu.mv` is the only deployment and its data is synthetic
(`a a` / `b b` / `v v` rows). The S1_SPEC line 147 “production-data copy”
gate is unsatisfiable until real data exists.

The live-data rules themselves are still correct **for the future**. This
ADR scopes when they apply; it does not delete them.

## Decision

1. **Production-copy gate (S1_SPEC tests item 1 / former A3).** Until first
   real use, `students:verify-unification` is gated on a **seeded
   representative dataset** (`UnificationRepresentativeSeeder`, artisan
   `--representative`) that exercises the A2 matcher: duplicate
   `national_id`s across two different people; blank and placeholder IDs
   (`N/A`, `0`, `-`); two people sharing a name with different DOBs; a
   genuine name+dob duplicate (unguessable, ADR-007); guardians with and
   without surviving user accounts; enrollments and payments on several
   rows. Staging `a a`/`b b` rows are **not** that dataset.

   The production-copy procedure in
   `docs/migrations/restore-production-copy.md` **reactivates the day real
   data exists**. It is not deleted.

2. **Hifz freeze (rule 7).** Keep the freeze: namespace/route changes only;
   no behavior change, no refactor, no dashboard redesign, until §2b.
   Justification is **scope discipline** (do not refactor a module while
   moving it), not production-safety. There are no live Hifz users.

3. **Three-deploy dance (rule 9).** Additive migrations remain good
   practice. Until first real use, dual-write windows and “≥2 weeks
   stable” waits are **optional**. Cleanup (including S1 Deploy 3) may be
   proposed and, once confirmed, executed without a stability wait.
   Rule 9 **reactivates in full at first real use**.

4. **Trigger for full reactivation (“first real use”).** The first time any
   deployment carries a real student record, a real payment, or a real
   Hifz user — i.e. data that is not a seeder, not staging synthetics, and
   not this representative unification fixture. On that day: production-copy
   verify before further student-keyed cutovers; full 3-deploy for any
   populated column drop/rename; Hifz freeze still holds until §2b on
   scope-discipline grounds.

5. **Branch protection** is unrelated to live data. It is still required
   (`docs/BRANCH_PROTECTION.md`). The agent token cannot apply it (HTTP 403).

## Consequences

- TRACK B is no longer blocked on an impossible production dump. Do not
  start B in the same slice that records this decision.
- `--backfill` remains refused when `APP_ENV=production` (defense in depth
  if a production host appears later).
- A representative run may list **expected** unresolved rows (genuine
  duplicates, orphan guardian pivots). Those must not be guessed (ADR-007).
  Deploy 2 safety is: no enrollment or payment resolves to the **wrong**
  student (null on unguessable rows is acceptable).
- S1 Deploy 3 cleanup is cheap now; it is proposed, not executed, until
  confirmed (`docs/migrations/s11-deploy-3-cleanup-proposal.md`).
