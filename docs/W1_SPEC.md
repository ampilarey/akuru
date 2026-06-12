# W1 Spec — Website Conversion Layer

**Phase:** W1 — independent of all other phases; runs on the CURRENT Blade site. Can ship before, during, or in parallel with Phase 0.
**Goal:** make course pages convert visitors into registrations using data the database already holds. No schema changes except one small settings group and one table.

---

## Slice W1.1 — Urgency from existing data

Course detail + course cards show, from existing `courses` columns:
- **Seats left:** `seats − count(active enrollments)`; thresholds (Settings): hide above 20, show "Limited seats" 11–20, show exact "N seats left" ≤10, "Full — join waiting list" at 0 (waiting list = simple interest form into ContactInquiry with course ref until Admissions backlog item ships).
- **Deadline countdown:** from `enrollment_deadline` — date + "X days left" badge under 14 days; auto-hide expired courses from "Open Courses".
- **Early-bird display:** if `registration_fee_*` early-bird fields are set and active, show struck-through normal price + early-bird price + its end date.
Rules: never show false scarcity — all three render only from real data; if seats is null, show nothing.

## Slice W1.2 — Trust above the fold

Homepage hero additions (Settings-driven, trilingual): registration/accreditation line (e.g. ministry reg. no.), years operating, students-taught counter (manual setting or computed), partner/affiliation logos (Media). One `trust_settings` group in existing Settings — no hardcoding.

## Slice W1.3 — Outcome-led course pages

- New `courses.learning_outcomes` (json, trilingual list) + admin field: rendered as "What you'll be able to do" above the description.
- **Per-course testimonials:** add nullable `course_id` FK to existing `testimonials`; course page shows its own first, falls back to general.
- Instructor block: surface existing instructor qualifications prominently (data exists, underused).

## Slice W1.4 — Mobile CTA + lead capture

- Sticky bottom bar on mobile course pages: price + "Register" + WhatsApp icon.
- **"Ask on WhatsApp"** deep link (`wa.me/<number>?text=<course name prefilled>`) — number per course (nullable) falling back to Settings default.
- **Syllabus download lead magnet:** "Get full syllabus" button → name + mobile mini-form → stores lead → sends PDF link (existing media). New `leads` table: `id, course_id, name, mobile, email nullable, source enum(syllabus, waiting_list, callback), status enum(new, contacted, converted, closed), notes, timestamps` + minimal admin listing with CSV export. (Becomes Admissions-owned at Phase 0 move.)

## Slice W1.5 — SEO + sharing

- schema.org JSON-LD: `Course` (+ `CourseInstance` with dates/price) on course pages, `Organization` sitewide, `FAQPage` where FAQs render.
- OG/Twitter meta per course (title, cover, price); localized `hreflang` triplets; XML sitemap incl. courses/articles/events.

## Slice W1.6 — Funnel measurement

Lightweight events (existing AnalyticsService or GA4): course_view → register_click → registration_started → payment_completed, plus whatsapp_click, syllabus_download. One admin funnel report per course. Decision rule recorded: iterate W1 content based on this data.

## Tests / DoD
- Seats/deadline logic unit-tested incl. null/expired/full cases; no false-scarcity rendering.
- Lighthouse mobile ≥ 85 on course page; JSON-LD validates.
- DoD: a real course shows seats left + deadline + outcomes + per-course testimonial + sticky CTA; a WhatsApp lead and a syllabus lead arrive; funnel report shows the clicks. STATUS.md updated.

**Out of scope:** visual redesign, React migration of public site (post-Phase-0 decision), W2 daily content, curriculum preview (auto-arrives with 1A).
