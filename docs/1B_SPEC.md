# 1B Spec — Offerings, modes, remaining blocks

**Phase:** 1B (after 1A). Source: `docs/SPEC.md` §46.3–46.4.
**Domains:** Offerings (modes/sessions/seats), Courses (engine blocks), Progress (unlock/completion).
No Arabic/Hifz/AI/payments/certificates.

## Slice 1B.1 — Offerings + delivery modes (done)

`course_offerings` with `delivery_mode` (`self_learning`, `face_to_face`,
`live_online`, `blended`, `hybrid`). Catalog CRUD under `/catalog/offerings`.
Publishing a course ensures one default self-learning offering. Seat locks,
pinning, and sessions are later slices.

## Slice 1B.2 — Pinning, offering enrollment, seat limits

## Slice 1B.3 — Sessions + attendance foundation

## Slice 1B.4 — Remaining block types

## Slice 1B.5 — Unlock + completion evaluators

## Slice 1B.6 — PWA + full i18n/RTL polish
