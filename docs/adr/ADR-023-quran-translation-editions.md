# ADR-023: Quran translation editions, license, and W2 robots

W2_SPEC asked to record translation edition + license and ayah-page robots in “ADR-009”. That number already records student data retention. This decision is **023**.

## Context

W2 daily ayah content must show Arabic plus Dhivehi and English meaning. Spec §2b / ROADMAP: reuse existing `surahs` / `quran_ayahs` — never create parallel Quran source tables. A full published translation (Saheeh International, official Dhivehi Quran, etc.) is **copyrighted**. Dumping one into git would be a license violation.

Hifz is frozen (rule 7): no dashboard redesign. Qur’an A-track already reads ayahs through `QuranReferenceReader`. W2 needs meanings as well.

## Decision

1. **Arabic lives in existing `quran_ayahs`.** New `quran_translations` is meaning-only: `quran_ayah_id`, `language` (`en` | `dv` | `ar_tafsir`), `text`, `source_name`, `source_note`, `verified_by`. Unique `(ayah, language, source_name)`.

2. **Read-only contract `QuranTextProviderInterface`** in `App\Support\Contracts`, implemented by Hifz `ReadQuranTextAction`. Website, Courses, and W2 consume the contract only. Nobody outside Hifz queries `quran_ayahs` / `quran_translations`.

3. **Import, do not scrape.** `php artisan quran:import-translations {path}` upserts a JSON edition the operator supplies. No auto-generation.

4. **What ships in the repo.** A **fixture teaching gloss** for Al-Fātiḥah 1:1 only (`Akuru teaching gloss (fixture)`):
   - English 1:1 uses the public-domain Pickthall (1930) wording of the basmala.
   - Dhivehi 1:1 is an internal teaching gloss, **not** the official Dhivehi Quran.
   Operators replace this by importing a licensed or public-domain set and setting `QURAN_TRANSLATION_SOURCE` / `config('quran.translation_source')` to that `source_name`.

5. **Robots (W2.3).** Ayah meaning archive pages may be indexed. Do not present the fixture gloss as a published mushaf translation in `schema.org` / meta. Full Arabic remains the existing mushaf text.

6. **Hifz freeze.** This slice adds a table, model, actions, command, and a container binding. No Hifz dashboard, session, or enrollment change.

## Consequences

- W2 can attach meanings when an admin picks an ayah.
- A production Dhivehi+English edition is an **operator import**, not a git blob.
- Rule 5 stays: Website files must not contain `App\Domains\Hifz\`.
