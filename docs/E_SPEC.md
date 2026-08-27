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

### W2.1 — Quran translations (this slice)

- `quran_translations` attached to existing `quran_ayahs`. No parallel Quran tables.
- `QuranTextProviderInterface` in Support contracts; Hifz `ReadQuranTextAction` implements it. Website/W2 must not import Hifz Models.
- Import: `php artisan quran:import-translations`. Repo ships a 1:1 teaching gloss only (ADR-023).
- Hifz dashboards untouched.

### W2.2 (next)

Daily content store (ayah/hadith/saying/reminder) with hadith integrity + maker–checker.

## E3 — W3 prayer times (next phase)

`docs/W3_SPEC.md`. `PrayerTimeProviderInterface`. No live SMS.
