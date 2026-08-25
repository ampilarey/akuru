# 1B Spec — Offerings, modes, remaining blocks

**Phase:** 1B (after 1A). Source: `docs/SPEC.md` §46.3–46.4.
**Domains:** Offerings (modes/sessions/seats), Courses (engine blocks), Progress (unlock/completion).
No Arabic/Hifz/AI/payments/certificates.

## Slice 1B.1 — Offerings + delivery modes (done)

`course_offerings` with `delivery_mode` (`self_learning`, `face_to_face`,
`live_online`, `blended`, `hybrid`). Catalog CRUD under `/catalog/offerings`.
Publishing a course ensures one default self-learning offering. Seat locks,
pinning, and sessions are later slices.

## Slice 1B.2 — Pinning, offering enrollment, seat limits (done)

`pin_mode=pinned` stores `lesson_id => revision_id`. The learner player
reads the pinned revision when the enrollment has an offering. Enrollments
gain `course_offering_id`. Seat limits use `lockForUpdate` on the offering
row plus a locked enrollment count. Cancelled/rejected seats do not count.

## Slice 1B.3 — Sessions + attendance foundation (done)

`course_offering_sessions` and `attendance_records` live in Offerings.
Session types: face_to_face, live_online, hybrid, workshop, exam,
review_class, orientation. Attendance statuses: present, absent, late,
excused, pending. Modes: physical, online, not_applicable. Catalog
CRUD + CSV at `/catalog/offerings/{id}/sessions`. Attendance may be
marked only for enrollments on that offering. Learner dashboard and
course page list upcoming sessions. Distinct from Academics class
attendance.

## Slice 1B.4 — Remaining block types

## Slice 1B.5 — Unlock + completion evaluators

## Slice 1B.6 — PWA + full i18n/RTL polish
