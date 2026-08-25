# Migration / verify archives

Operator copies live-data verification artifacts here. Do not invent
counts or JSON from local `akuru_institute` / `akuru_test` **except**
the ADR-021 representative unification gate, which is seeded on purpose.

## Student unification (S1.1b / S2.0 gate)

**Until first real use (ADR-021):** run

```text
php artisan students:verify-unification --representative
```

Archive the report as
`docs/migrations/s11b-student-unification-report-representative.json`
and paste verbatim stdout into `STATUS.md`.

Expected unguessable rows (genuine name+dob duplicates, orphan guardian
pivots) are listed, not guessed (ADR-007). The gate is green when every
*resolvable* RS maps 1:1, no enrollment/payment points at the **wrong**
student, and matcher skip/contradiction counts are recorded.

Staging synthetics (`a a` / `b b`) are **not** this dataset. The older
staging capture remains `docs/migrations/s11b-student-unification-report.json`
(historical).

## Production-data copy (reactivates at first real use)

`docs/migrations/restore-production-copy.md` is the procedure for the
day a real production dump exists. It is **not** the current Deploy 2
gate. `--backfill` is never run against production itself.
