# ADR-029: F5 executed — the Qur'an dataset moves to the engine, and mushaf editorial is ported rather than deleted

Date: 2026-09-12
Status: Accepted
Phase: F5 (ROADMAP §2b, final slice). Supersedes the "gated" status of ADR-025.

## Context

ADR-025 held F5 behind a four-condition gate and stated the coupling that makes
it one slice: the reader implementations cannot move without the dataset models,
and the dataset models cannot move while Hifz code still reads them.

The gate is now met:

1. **Engine parity** — three-lane session-record entry (F5-P1, #136), §52.18
   assignments (F5-P2, #137) and the milestone recommend → review → approve
   workflow (F5-P3, #138) exist and were walked in a browser.
2. **`halaqa:verify-structure` green** against the seeded representative
   dataset; output captured in STATUS for this slice.
3. **Operator sign-off** given.
4. **Casualty inventory re-run** — see "What this slice touched" below.

Condition 4's re-run found what ADR-025's inventory did not: a **fourth**
unreplaced workflow. The Blade app owned mushaf editorial — upload a mushaf,
import ayahs and their words, map word positions onto a page image, approve,
lock. That is how the Qur'an dataset is built and reviewed, and the engine had
no equivalent: zero references to "mushaf" existed anywhere in `Courses`.

Those three files (`QuranMushafController`, `QuranPageController`,
`QuranMushafImportService`) hold `QuranMushaf`, `QuranPage` and `QuranWord`, so
keeping them would have blocked the move F5 exists to perform. Deleting them
would have removed the only browser path to work the engine could not do — the
precise outcome ADR-025 was written to prevent.

## Decision

**1. The dataset moves, whole.** `Surah`, `QuranAyah`, `QuranMushaf`,
`QuranPage`, `QuranWord`, `QuranWordPosition` and `QuranTranslation` — plus
`QuranTranslationLanguage`, the five reader/importer Actions and the import
command — now live under `App\Domains\Courses\Components\Quran`. The
`QuranReferenceReader` and `QuranTextProviderInterface` bindings move from
`HifzServiceProvider` to `CoursesServiceProvider`. **The contracts themselves do
not change**, so no consumer of either one moved.

**2. Mushaf editorial is ported to Inertia, not deleted.** Losing the ability to
build the dataset would have been a worse outcome than either alternative
ADR-025 contemplated, and the code is small (≈10 controller actions, a 53-line
service, 108 lines of Blade). It is now `/quran/mushafs/*`, rendering
`Courses/Quran/Mushafs/{Index,Create,Show}` and `Courses/Quran/Pages/Show`.

The routes deliberately sit **outside** the `catalog` role group. Authorization
stays exactly what it was — `QuranMushafPolicy`, which requires
`manage_quran_mushaf` **and** `isHifzDean()`. A `role:` group here would have
been a different rule wearing the same name.

Two behaviours improved in the port, because the Blade versions were working
around Blade: the page screen ships its word list with the render instead of
fetching it from a second JSON endpoint (`quran.words.index`, now gone), and
saving a word position no longer calls `location.reload()`.

**3. The Hifz-side dataset consumers are deleted in the same change.**
`HifzSessionController`, `HifzSessionRecordController`, `HifzMistakeController`
and the unrouted `RecitationPracticeController`, with their Blade views and
routes. Their engine replacements are `teach.quran-sessions.*` and
`teach.recitations.*`.

**4. Hifz reads Qur'an reference data through the support contract.** The
`currentSurah`, `newFromSurah`, `newToSurah`, `quranPage`, `quranAyah`,
`quranWord` and `surah` relations are gone from the surviving Hifz models —
Hifz importing `Courses\Components\Quran\Models` would be the same rule 3
violation in the opposite direction. `ListStudentHifzSummariesAction`, the only
surviving reader that needed a surah *name*, now calls
`QuranReferenceReader::findSurah()` with a small memo, because it runs once per
child on a guardian's portal page. **Every foreign-key column is untouched**
(rule 9); only the Eloquent relations went.

**5. The reverse direction is guarded by a test of its own.**
`tests/Architecture/QuranDatasetOwnershipTest.php`, as ADR-025 required. It
exists separately because the general scanners cannot see this:
`crossDomainModelViolators()` matches only `App\Domains\X\Models\…`, so a
`Components\Quran\Models\…` import is invisible to it, and
`crossDomainNonContractViolators()` would report one as a *new baseline entry* —
which reads as "add it to the baseline" rather than "this is forbidden".

## What this slice did NOT do

**The rest of the legacy Hifz Blade app stays.** Eleven controllers — the five
dashboards, the hub, programs, enrollments, milestones, mistakes and reports —
never touched the Qur'an dataset, so they do not block anything this ADR
decides, and deleting them is a separate question with its own parity work
(engine halaqa programs are offerings; `HifzReportService`'s five reports have no
engine equivalent yet). Retiring them is an information-architecture decision,
not a dataset one.

Three links inside those surviving screens pointed at deleted routes and were
repointed: the teacher dashboard's session buttons now open `/teach/schedule`,
the dean dashboard's "Quran Source" opens the ported `/quran/mushafs`. The
programme page's per-student "History" link has **no** replacement offered —
both candidate engine screens would 403 for most viewers of that page, and a
link that always fails is worse than none.

**Legacy tables are archived, never dropped** (rule 9). `hifz_*`,
`quran_progress` and `recitation_practices` keep every row; `HifzReportService`
still reads `hifz_mistakes`. The `surahs` / `quran_*` dataset tables are live and
unchanged — they are the one Qur'an dataset (rule 11) regardless of which
namespace owns the models. No migration ships with this slice.

## Consequences

- `cross_domain_models` shrinks by 3, `cross_domain_non_contract` by 3,
  `unregistered_route_names` by 2. Recorded in the PR body.
- `QuranMushafImportService::activate()` takes an approver **id** rather than an
  `Identity\Models\User`, and `QuranMushaf::approver()` is gone: inherited into
  the engine, those imports would have been *new* rule 3 violations rather than
  grandfathered ones. `approved_by` still records who approved.
- W2's `QuranTextProvider` dependency resolves to the engine binding now.
  Nothing about the interface changed, so nothing else moved.
- `hifz.quran.*` route names no longer exist; `quran.*` replaces them.
  `HifzRouteNamesTest` asserts both halves — the new names registered, the
  retired ones *not* registered, because two live systems over one dataset is
  what F5 exists to end.
