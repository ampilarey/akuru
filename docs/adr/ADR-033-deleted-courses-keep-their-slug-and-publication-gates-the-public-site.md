# ADR-033: A deleted course keeps its slug, and publication — not enrolment status — gates the public site

Date: 2026-09-12
Status: Accepted
Phase: follow-up to #272 (SPEC §29 soft deletes)

## Context

#272 made `admin.courses.destroy` safe. A course with a roster, attempts,
progress, certificates or payment line items is soft-deleted rather than removed,
because `course_enrollments.course_id` and `payment_items.course_id` both CASCADE
and rule 12 says ledgers are append-only.

It built the deleting half and not the recovering half. Nothing in the
application called `withTrashed()`, `onlyTrashed()` or `restore()`, so a deleted
course left every screen with no way to see it or bring it back. Two further
facts made that worse:

- `unique:courses` on `slug` queries the raw table, so the deleted row kept its
  address. Re-creating a course there failed with "The slug has already been
  taken" and no course anywhere to explain it.
- The catalogue already uses the word **Archive** for a different thing —
  `workflow_status = 'archived'`, a status on an ordinary visible row, set from
  the Catalog screen.

Then the browser walk for the recovery screen failed on a step that should have
passed — *restoring did not put it back on the public site* — and the cause was
not the restore. `scopeOpenForPublicListing()` filtered on `status` and never on
`workflow_status`, and `PublicSite\CourseController::show()` filtered on nothing
at all.

## Decision

**1. A deleted course keeps its slug, and re-creating there is refused by name.**
The slug is the course's public address. Releasing it would let a different
course claim it, so every link, bookmark and search result pointing at the old
address would silently serve unrelated content. A 404 is recoverable; quietly
serving something else under a known URL is not. So the address stays held, and
the refusal names the course holding it and points at Restore.

The cost: an admin who genuinely wants that slug back must restore the course
and rename it, or pick another. That is a deliberate extra step, and it is the
cheaper error of the two.

**2. A restored course comes back as a draft.** Deleting is usually a withdrawal
— a duplicate, something created in error, an intake pulled. A restore that
re-listed the course would publish content to the public website as a side
effect of clicking "Restore". Whoever restores it can publish it deliberately.

**3. The screen is called "Deleted courses", not "Archived courses".** One word
per concept in one admin. The delete flash was changed for the same reason: it
said "Course archived", which now means something else.

**4. Publication gates the public site; enrolment status does not.**
`status` (open / upcoming / closed) answers *can somebody enrol?*
`workflow_status` (draft / in_review / published / archived) answers *was this
meant to be seen?* Only the second is a publication decision, and it is now the
first condition of `scopeOpenForPublicListing()` and an explicit check in
`show()`.

A course in `draft` or `in_review` is unreviewed copy, provisional pricing and a
syllabus still being argued over. In the seeded dataset 11 of 12 courses were
drafts and every one of them was publicly listed.

**5. `CourseFactory` defaults to `published`.** Adding the gate broke 13 existing
tests, all of which asserted that a public course page renders — for courses the
factory had silently left as drafts. Not one test in the suite had ever asserted
a published course, which is why the hole survived this long. A factory course is
meant to be an ordinary usable course; the unpublished states are now set
explicitly by the tests that are about them.

## Consequences

- Delete is recoverable, and the recovery screen states what each course holds,
  so an admin can see that nothing was lost.
- Unpublished course content is no longer readable by URL or listed publicly.
  **This changes what the public site shows**: any course currently `draft` with
  `status = open` disappears from it. Under ADR-021 there are no live users, so
  no visitor loses access to something they were using — but on a deployment
  with real content, publishing those courses is a content decision somebody has
  to make deliberately.
- A slug held by a deleted course cannot be reused without restoring or renaming.
- `isPublished()` lives on the model rather than being an enum comparison at each
  call site, so `Website` does not import `Courses\Enums` to ask the question
  (rule 3).
