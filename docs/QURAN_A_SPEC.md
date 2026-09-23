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

## Slice A.4 — Dual-write only (done; switch/cleanup later)

Deploy 1 of 3. `QURAN_HALAQA_DUAL_WRITE` defaults false. Catalog
`/catalog/offerings/{id}/halaqa/sync` mirrors unmapped Hifz sessions
and active enrollments onto the linked offering. New Hifz sessions
mirror only when the flag is on; failures never block Hifz. See
ADR-020. Switch reads and Hifz table cleanup are not in this slice.

## Out of scope until Module B

AI letter/haraka checking, low-confidence routing, model versions.

## Walked

- A.1 → A.2 → A.3 → A.4 as one loop: `scripts/smoke/quran.mjs`
  (2026-09-23, STATUS §5fm) — the reference and its CSV; a recitation
  activity refused past the end of its surah and saved inside it; the
  passage heading on the player; hand-in unmarked by the engine, scored
  by the marker; an offering linked to a Hifz program with one session
  mapped and dual-write reported off.
- §52.9's recording queue (F3/F4): `recite.mjs`.
- Ayah text on the player depends on the imported dataset (ADR-023); the
  walk reports it rather than requiring it.
