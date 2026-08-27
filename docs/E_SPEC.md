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

### W1.5 — SEO + sharing (this slice)

- schema.org JSON-LD: `Course` + `CourseInstance` (dates + displayed price, including early-bird) on course pages; `Organization` sitewide; `FAQPage` matching the FAQs that render.
- OG/Twitter per course: title, cover, price (`product:price:*`). Price omitted when `fee` is null.
- Localized `hreflang` triplets (en/dv/ar + x-default) on the public layout.
- XML sitemap includes courses, articles, news, events, with `xhtml:link` alternates. Course list via Courses `ListPublicCourseSitemapEntriesAction` (Website does not import Courses Models). News loc uses slug (the public route).

Engine subject-ignorant. Hifz untouched. New Website files do not import Courses Models. Arch baselines shrunk (`SitemapController` no longer imports Course).

### W1.6 (next)

Funnel events.

## E2 — W2 daily content (next phase)

Ayah/hadith/reminder engine reusing `surahs` / `quran_ayahs`. No parallel Quran tables.

## E3 — W3 prayer times (next phase)

`docs/W3_SPEC.md`. `PrayerTimeProviderInterface`. No live SMS.
