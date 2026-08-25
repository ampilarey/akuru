# ADR-017: Immutable lesson revisions

## Context

Phase 1A must protect what a student already saw. The spec’s “ADR-001
lesson revisions” number is already used for the domain map. Draft
edits, deletes, and reorders must never mutate a published lesson.

## Decision

1. **Draft is mutable rows.** `content_blocks` belong to the lesson
   (`lesson_id` is the source of truth). `course_id` / `course_module_id`
   are denormalized and synced when a lesson is saved.
2. **Publish copies a JSON snapshot.** `lesson_revisions.snapshot_json`
   is an ordered immutable list of blocks plus lesson render data
   (`title`, `description`, `blocks[]`). Rows in that JSON are copies,
   not live FKs.
3. **The player reads only the current published revision.**
   `lessons.current_revision_id` points at the latest published
   snapshot. Unpublished lessons have no player payload.
4. **Republish appends.** A new revision_number is inserted. Old
   snapshots stay. Progress (1A.6) will store `lesson_revision_id`.
5. **1B offering pinning** reuses this mechanism: an offering pins a
   set of `lesson_revision_id`s (a course content version). Do not
   invent a second versioning system.

## Alternatives

- Versioned block rows per revision — more joins, easier accidental
  mutation of history.
- Whole-course snapshots only — too coarse for republishing one lesson.

## Consequences

- Builder edits `content_blocks`; player never queries them.
- Tests must prove draft mutation cannot change an existing snapshot.
