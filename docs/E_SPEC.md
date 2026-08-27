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

### W1.6 — Funnel measurement

Merged #117.

## E2 — W2 daily content

### W2.1 — Quran translations

Merged #118.

### W2.2 — Daily content store (this slice)

- `daily_contents` in Website. Unique `(publish_date, content_type)`. No `academic_year_id` (ADR-024).
- Hadith cannot publish without collection + number + grading + grading source.
- Maker–checker: `daily_content.manage` vs `daily_content.approve`; creator ≠ approver. Save cannot set scheduled/published.
- Ayahs via `QuranTextProviderInterface` only. Admin Blade under public-site (no AppShell link).
- No auto-generation (WORKING_RULES + ADR-024).

### W2.3 (next)

Homepage widget, archive/permalink, share cards. Prayer/Hijri widget is **W3**.

## E3 — W3 prayer times (next phase)

`docs/W3_SPEC.md`. `PrayerTimeProviderInterface`. No live SMS.
