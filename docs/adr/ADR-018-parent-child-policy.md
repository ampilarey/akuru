# ADR-018 — Parent-child policy lives in People

## Status

Accepted (1A.7)

## Context

Guardians already attach to unified students through `guardian_student`.
Courses, Portal, and Academics need to know whether a signed-in user may
see a child's learning or school data. Importing People models from those
domains would grow the architecture baseline.

## Decision

People owns the relationship and the access question.

Other domains call `GuardianCanAccessStudentAction` or
`ListGuardianChildrenAction`. They never import `ParentGuardian` /
`Student` models. A guardian may view a child's enrollment progress; they
do not complete lessons as the child.

## Consequences

Portal `/portal/learning` is read-only. Lesson player access stays
staff / enrolled student / preview. Additive pivot columns hold consent
and verification without rewriting S1 attach behavior.
