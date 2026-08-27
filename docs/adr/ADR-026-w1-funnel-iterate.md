# ADR-026: Iterate W1 content from the public course funnel

## Context

W1.6 requires lightweight conversion events (`course_view` → `register_click` → `registration_started` → `payment_completed`, plus `whatsapp_click` and `syllabus_download`) and **one recorded decision rule**: iterate W1 content from this data rather than from guesswork.

ADR-002 keeps operational analytics (`AnalyticsService`, dashboard metrics) in Settings. That service already grandfather-imports many domain Models. Website conversion is a different product surface (public course pages, checkout, BML confirmation) and already owns `leads`.

Rule 10 wants `academic_year_id` on time-scoped academic tables. Website conversion events are not academic backbone data (same exemption pattern as `leads` and ADR-004 prayer broadcasts).

Money rule: access to paid courses depends on BML **webhook** confirmation, never the return URL.

## Decision

1. **Website owns funnel events.** New `funnel_events` table, `RecordFunnelEventAction`, and the per-course admin report live in Website. Settings analytics are unchanged.

2. **Hooks, not a fat SDK.**
   - `course_view` — public course show (server).
   - `register_click` / `whatsapp_click` — client beacon only (`POST /funnel-events`, throttled). Server rejects other names.
   - `registration_started` — Admissions checkout (open enrollment) and a successful new-registration `start()`.
   - `syllabus_download` — `CaptureCourseLeadAction` when the source is syllabus.
   - `payment_completed` — Finance `PaymentService` after webhook/`finalizeByReference` success (`confirmed`). Idempotent retries skip because final statuses return early. The return URL does not write this event.

3. **Cross-domain = Actions + strings.** Admissions and Finance call `RecordFunnelEventAction` with event **name strings**. They must not import `FunnelEventName` (Enums are not an allowed cross-domain layer).

4. **No `academic_year_id` on `funnel_events`.** Website conversion log, like `leads`.

5. **Iterate-from-data rule** (`ComposeCourseFunnelReportAction::decide()`), evaluated in this order:

   | Condition | Decision |
   |---|---|
   | No events | Keep collecting |
   | Views ≥ 20 and register clicks / views < 5% | Iterate W1 content (hero, urgency, outcomes, sticky CTA) |
   | Clicks ≥ 10 and started / clicks < 50% | Iterate checkout first step |
   | Started ≥ 10 and paid / started < 30% | Iterate payment / fee copy |
   | WhatsApp clicks > register clicks and WhatsApp ≥ 5 | WhatsApp is the stronger path |
   | Else | Keep iterating W1 content from this funnel — no stage is clearly stuck |

## Consequences

- One admin report per course (plus CSV) is enough to choose the next W1 edit.
- GA4 `gtag('event', …)` is best-effort when a measurement id is configured; the database funnel is the source of truth.
- Engine stays subject-ignorant. Hifz is untouched. AppShell nav is not extended (nav IA still proposed).
