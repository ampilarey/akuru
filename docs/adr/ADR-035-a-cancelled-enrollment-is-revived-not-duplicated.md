# ADR-035: A cancelled enrollment is revived, not duplicated

## Context

`course_enrollments` has carried the same unique key since the table was
created:

```sql
UNIQUE KEY (student_id, course_id, term_key)   -- term_key = IFNULL(term_id, 0)
```

Both enrollment Actions decided who counts as already enrolled by a different
rule:

```php
->whereNotIn('status', ['rejected', 'cancelled'])
```

…and inserted a new row when that found nothing. The generosity is deliberate
and right: somebody who withdrew, whose application was rejected, or whose
payment was refunded should be able to come back. The key does not share the
opinion — `cancelled` was not even one of the statuses the original migration
declared — and neither does the soft-delete column, which keeps the row and the
key with it (SPEC §29).

So the insert that followed that decision hit a duplicate key, and the learner
got a 500. The reachable paths are ordinary ones:

- a family is refunded, the listener cancels the enrollment, they try to enrol
  again;
- the office cancels a club member and adds them back next term;
- an application is rejected and the student applies again.

Neither half is wrong on its own. The guard reads as generosity; the key reads
as hygiene. Nothing brought them together until a browser walk refunded a
payment and then clicked Enroll (STATUS §5ei).

Two ways out:

1. **Narrow the key** so it ignores ended enrollments, and let each attempt be
   its own row.
2. **Honour the key** and revive the row that already holds it.

## Decision

Honour the key. One Action, `CreateOrReviveEnrollmentAction`, owns the question
of identity and both enrollment Actions go through it (rule 11):

- no row on that key → create;
- a row that is `rejected`, `cancelled`, or soft-deleted → revive it with the
  new enrollment's attributes;
- anything else → hand back what is there, untouched.

A revived enrollment keeps the `progress_percentage` it had earned.
`student_lesson_progress` is keyed by student and lesson rather than by
enrollment, so it survives a cancellation untouched; zeroing the rollup would
have the catalog report 0% over lessons the student can see are finished.

Nothing in the schema changes. The key stays as it is, and no migration is
needed — which also keeps this out of rule 9's three-deploy dance.

## Consequences

**Easier.** Every read that looks a student's enrollment up by course — reports,
certificate eligibility, access windows, the gradebook — keeps finding exactly
one row, which is what all of them already assumed. Re-enrolment works from
every entry point at once, including ones nobody has walked yet, because the
fix is below all of them rather than in each.

**Harder.** An enrollment row now records the *current* enrollment rather than
the last one that ended. A student who enrolled, was cancelled, and enrolled
again has one row saying "active", not two rows telling the story. If that
history is ever needed — how many times somebody re-took a course, or when each
attempt began — it has to come from somewhere that keeps it: `payments` and
`payment_refunds` are append-only already (rule 12) and hold the money side, but
there is no enrollment-event log, and this decision is the reason one would be
needed. Recorded here rather than built, because nothing asks for it today.

**Unchanged.** The unique key, the status vocabulary, and the deliberate
generosity of the guard. This makes the code agree with the database instead of
arguing with it.
