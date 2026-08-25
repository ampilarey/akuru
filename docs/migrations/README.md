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

Expected unguessable rows are listed, not guessed (ADR-007). The archived
JSON carries **both** the raw 1:1 check and the manifest verdict:

- `verification.raw_ok` — strict “every RS maps to exactly one student”.
- `verification.expected_unresolved` — RS ids the seeder planted as
  unguessable.
- `verification.unexpected_failures` — anything unresolved that is **not**
  in that manifest. **The gate is green only when this array is empty.**
- `verification.verdict` — `OK_AGAINST_MANIFEST` or `FAILED_UNEXPECTED`.

Do **not** “fix” these seeded outcomes (fresh `akuru_test` ids):

| Artifact | Meaning |
|---|---|
| unresolved RS **11** and **12** | Genuine-duplicate case: two `Ahmed Naseem` / `2009-04-04` rows. Name+dob yields students **12** and **13**. ADR-007 forbids guessing; both stay ambiguous. |
| `guardians.unmapped: [2]` | Seeded guardian pivot whose user account was deleted (no user to invent a `parent_guardians` row). |
| `enrollments.missing: [11, 12]` | Follows from RS 11/12 staying unresolved — those enrollments have `unified_student_id` null on purpose, not a wrong student. |

Staging synthetics (`a a` / `b b`) are **not** this dataset. The older
staging capture remains `docs/migrations/s11b-student-unification-report.json`
(historical).

## Production-data copy (reactivates at first real use)

`docs/migrations/restore-production-copy.md` is the procedure for the
day a real production dump exists. It is **not** the current Deploy 2
gate. `--backfill` is never run against production itself.
