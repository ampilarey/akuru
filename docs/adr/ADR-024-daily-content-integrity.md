# ADR-024: Daily content integrity (W2.2)

## Context

W2.2 stores curated ayah, hadith, saying, and reminder rows for the public site. Spec integrity is non-negotiable: hadith must carry collection + number + grading + grading source; Islamic content cannot become scheduled/published without a second reviewer; nothing is auto-generated or scraped. Website must not query `quran_ayahs` (rule 5 / ADR-023). Rule 10 usually puts `academic_year_id` on time-scoped tables.

## Decision

1. **Table `daily_contents`** lives in Website. Unique `(publish_date, content_type)`. Ayah rows store `quran_ayah_id` only; meanings attach through `QuranTextProviderInterface` (`findAyah` / `ayahWithMeaningsById`). No parallel Quran tables.

2. **Hadith publish gate.** `ApproveDailyContentAction` rejects a hadith unless collection, number, grading, and grading source are all present. Drafts may be incomplete.

3. **Maker–checker.** Permissions `daily_content.manage` (create/edit/archive/CSV) and `daily_content.approve` (queue). `created_by !== approved_by`. Save cannot set `scheduled` or `published`. Editing a scheduled/published row returns it to `draft` and clears `approved_by`.

4. **No auto-generation.** Admin Blade under `/admin/public-site/daily-content` (same CMS as leads/funnel). Theme batches create reminder *drafts* only. No AppShell link (nav wrap still 83). Recorded in `docs/WORKING_RULES.md`.

5. **No `academic_year_id`.** Public-site calendar content, same exemption as `leads` / `funnel_events` (not school operational records).

## Consequences

- W2.3 can render published rows without Website importing Hifz Models.
- A production translation edition remains an operator import (ADR-023).
- Two staff accounts are required to publish.
