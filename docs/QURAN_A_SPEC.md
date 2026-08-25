# Qur’an / Hifz Module A — human-first

**Phase:** after Phase 2 + Arabic A. Source: `docs/SPEC.md` §52, `docs/ROADMAP.md` §2b.
**Rule 7:** Hifz is frozen until this designated migration phase. Do not
change live Hifz behavior, dashboards, or scoring in a drive-by slice.
**Rule 8 / §52:** no pronunciation AI here.
**Rule 5:** Courses never imports `App\Domains\Hifz\*`. Quran reads go
through `App\Support\Contracts\QuranReferenceReader`.

Reuse the existing `surahs` / `quran_*` tables. Never create parallel
Quran datasets.

## Slice A.1 — Read Actions (done)

Hifz `ListSurahsAction` / `ListAyahsAction` / `ReadQuranReferenceAction`
over the existing tables. Courses catalog `/catalog/quran` (+ CSV)
consumes the Support contract only. No Hifz dashboard, scoring, or
route behavior change.

## Slice A.2 — Recitation as teacher-marked activities (done)

Recitation submissions reuse the 2.1 `teacher_marked` pattern and 2.4
review. Range metadata (`surah_id`, ayah start/end) is validated
through the A.1 reader. The learner player shows the passage. No new
scoring engine. No Hifz UI change.

## Slice A.3 — Offering / session mapping (done)

Map halaqa onto Offerings sessions without redesigning Hifz
dashboards. `offering_halaqa_links` / `offering_halaqa_session_links`
store integer Hifz ids (no FK, no dual-write). Labels come from
`HalaqaReferenceReader`. See ADR-019.

## Slice A.4 — Dual-write / switch / cleanup (not started)

Only after mapping is agreed and verified. 3-deploy rule.

## Out of scope until Module B

AI letter/haraka checking, low-confidence routing, model versions.
