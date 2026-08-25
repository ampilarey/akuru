# Arabic Module A — Non-AI skills

**Phase:** Arabic A (after Phase 2). Source: `docs/SPEC.md` §51.22–51.23.
**Rule 6:** no subject branches in Courses/Offerings/Progress core.
**Rule 8:** no AI. Everything works with pronunciation AI off.

## Slice A.1 — Letters + harakas (done in this branch)

Admin-managed `arabic_letters` and `arabic_harakas`. Seeded. Catalog
CRUD + CSV. Listed through Actions so activities can attach letter/haraka
ids as metadata without a new engine.

## Slice A.2 — Skill activities on the four patterns

Listening/speaking/reading/writing activity types as configuration of
the 2.1 patterns + letter/haraka metadata. Teacher-marked speaking and
canvas submissions reuse 2.4 review.

## Slice A.3 — Skill reports

Read-only Arabic skill reports from Progress Actions (attempts/scores).
No parallel LMS.

## Out of scope

Pronunciation AI, training samples, model versions (Module B).
Hifz behavior change. New activity engines.
