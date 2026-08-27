# Phase E — Public website (E1–E3 = W1–W3)

Website domain. Engine core stays subject-ignorant. Hifz frozen. New Website files import other domains via Actions/Contracts only.

## E1 — W1 conversion layer

### W1.1 — Urgency from existing data

Merged #112.

### W1.2 — Trust above the fold

Homepage hero reads Settings group `trust_settings` (merged #113).

### W1.3 — Outcome-led course pages

Merged #114.

### W1.4 — Mobile CTA + lead capture

Merged #115.

### W1.5 — SEO + sharing

Merged #116.

### W1.6 — Funnel measurement (this slice)

- `funnel_events` in Website (not Settings; ADR-002 unchanged). No `academic_year_id` (website conversion, like `leads`).
- Events: `course_view` → `register_click` → `registration_started` → `payment_completed`, plus `whatsapp_click` and `syllabus_download`.
- Client beacon may only post click names. `payment_completed` only on BML webhook / `finalizeByReference` success.
- Admin Blade report + CSV at `/admin/public-site/funnel`. Decision rule recorded in ADR-022: iterate W1 content from this funnel.
- Admissions and Finance call `RecordFunnelEventAction` with **strings** (Enums are not a cross-domain layer).

Engine subject-ignorant. Hifz untouched. No AppShell nav link. Arch baselines must not grow.

## E2 — W2 daily content (next phase)

Ayah/hadith/reminder engine reusing `surahs` / `quran_ayahs`. No parallel Quran tables.

## E3 — W3 prayer times (next phase)

`docs/W3_SPEC.md`. `PrayerTimeProviderInterface`. No live SMS.
