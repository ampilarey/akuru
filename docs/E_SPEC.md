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

### W2.2 — Daily content store

Merged #119.

### W2.3 — Public display (this slice)

- Homepage widget: Settings `daily.homepage_layout` `stacked` (default, all types) or `rotate` (one type per day). Empty today falls back to the most recent published of that type.
- Archive `/daily/{type}` + permalink `/daily/{type}/{date}` (e.g. `/en/daily/ayah/2026-08-27`).
- Article JSON-LD + OG. Fixture gloss is not named as a published mushaf translation (ADR-023). Ayah pages may be indexed.
- Share cards 1080×1080 via Media `ImageProcessorInterface` + `StoreGeneratedPublicImageAction`. Hadith collection/number/grading/source always on the card spec. Pre-render on publish (queued); card failures do not block publish.
- Scheduler `daily-content:publish-due` at 00:05 `Indian/Maldives`.
- Prayer/Hijri widget is **W3**.

### W2.4 (next)

Subscriptions + SMS via `SmsSenderInterface` only; fake in testing; opt-in only.

## E3 — W3 prayer times (next phase)

`docs/W3_SPEC.md`. `PrayerTimeProviderInterface`. No live SMS.
