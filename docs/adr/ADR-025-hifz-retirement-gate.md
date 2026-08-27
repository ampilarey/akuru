# ADR-025: Hifz retirement gate — Blade stays until engine parity; dataset models move with the Blade deletion

Date: 2026-08-27
Status: Accepted
Phase: F5 (ROADMAP §2b, final slice)

## Context

Phase F planned F5 as "retirement": archive the legacy Hifz structures, move the
`QuranReferenceReader`/`QuranTextProvider` implementations into
`Courses/Components/Quran`, and close §2b. Investigation before building showed
the two halves of that plan are coupled in a way the plan did not state:

1. **The reader implementations cannot move alone.** `ReadQuranReferenceAction`
   and `ReadQuranTextAction` query the Quran dataset models (`Surah`,
   `QuranAyah`, `QuranMushaf`, `QuranPage`, `QuranWord`, `QuranWordPosition`,
   `QuranTranslation`), all in `Hifz\Models`. Moving the actions into
   `Components/Quran` while the models stay would make Courses import
   `App\Domains\Hifz\` — forbidden by the courses-never-imports-Hifz guard in
   `QuranReferenceTest`. So the move is really a move of the **whole dataset**
   (7 models + morph aliases + every reference).

2. **The dataset models cannot move while the Blade app lives.** The frozen
   Hifz Blade controllers/services (`HifzSessionController`,
   `HifzSessionRecordController`, `RecitationPracticeController`,
   `QuranMushafController`, `QuranPageController`, `QuranMushafImportService`)
   and the Hifz models' own relations (`currentSurah`, `newFromSurah`, …) all
   use those models. After a move they would import
   `Courses\Components\Quran\Models\*` from Hifz — a rule 3 violation (models
   across a domain boundary). The arch scanners happen not to see that path
   (`crossDomainModelViolators` matches only `App\Domains\X\Models\…`, and
   `crossDomainNonContractViolators` would flag each `use` as a NEW baseline
   entry — baselines may only shrink). Writing the imports as inline FQCNs
   would evade both scanners, which is gaming the tests, not architecture.

3. **The Blade app cannot be deleted yet.** Three legacy workflows have no
   engine replacement: three-lane session-record entry
   (new/recent-revision/old-revision with scores — F2 mirrors only attendance),
   Hifz assignments (§52.18 was deliberately not built in F3), and the
   milestone recommend→supervisor-review→approve workflow UI (the engine only
   CONSUMES approved milestones via `SyncHifzMilestoneProgressAction`).
   Deleting the Blade app now would remove the only browser path for work the
   engine cannot yet do — failing the definition of done ("a user can complete
   the task in a browser").

## Decision

F5 becomes a **gated slice** rather than an immediate build:

- The legacy Hifz Blade app **stays frozen and operational** until the
  retirement gate below is met. The freeze rules (rule 7) remain in force.
- The Quran dataset models and the reader implementations move **together, in
  the same slice as the Blade deletion** — never before. That slice removes the
  Hifz-side consumers in the same change that relocates the models, so no
  cross-domain model imports are ever introduced.
- Legacy tables are **archived, never dropped** (rule 9): `hifz_*` structural
  tables plus `quran_progress` / `recitation_practices` keep their data;
  specialty tables (`surahs`, `quran_*` dataset, mistakes, scoring) stay live —
  they are the one Quran dataset (rule 11) regardless of which namespace owns
  the models.

### Retirement gate (all must hold before the deletion slice starts)

1. Engine parity: session-record entry (three lanes + scores), assignments
   (§52.18), and the milestone approval workflow exist on the engine side and
   are walked in a browser.
2. `halaqa:verify-structure` output captured green in STATUS against the
   seeded representative dataset.
3. Operator sign-off that staff will use the engine surfaces (nav/IA decision
   included).
4. The casualty inventory (below) is re-run and every listed test/reference has
   a rewrite plan in the deletion PR body.

### Casualty inventory (2026-08-27 grep; re-run before the deletion slice)

Tests importing `App\Domains\Hifz\*` that the move/deletion will touch:
`QuranReferenceTest`, `QuranRecitationTest`, `QuranRecitationActivityTest`,
`QuranDashboardTest`, `HifzStructureBackfillTest`, `HalaqaMappingTest`,
`HalaqaDualWriteTest`, `HalaqaMirrorVerifyTest` (dataset/structure model
imports — mechanical FQCN rewrites); `Hifz/HifzAuthorizationTest`,
`Hifz/HifzMilestoneWorkflowTest`, `Hifz/HifzMistakeCountTest`,
`Hifz/QuranMushafAuthorizationTest`, `Hifz/QuranTextProviderTest` (cover Blade
behavior — deleted or rewritten against engine equivalents);
`AdminResourcePagesSmokeTest`, `PortalHomeTest`,
`tests/Support/WebsiteDailyContentHelpers.php` (reference rewrites). App-side
non-domain files needing FQCN rewrites: `app/Policies/QuranMushafPolicy.php`,
`app/Console/Commands/DemoDataCommand.php`. Baseline-listed Hifz files and the
Portal dashboards shrink the `cross_domain_models` /
`hifz_referenced_outside_hifz` baselines when deleted — record the shrink in
the deletion PR.

### Guard to add in the deletion slice

Extend the architecture tests so the retired direction cannot reappear:
Hifz (whatever remains of it) must never import
`Courses\Components\Quran\Models`, and the components-isolation test keeps
guarding the component side. The scanner blind spot for `Components\*\Models`
paths is recorded here deliberately — do not rely on it.

## Consequences

- W2's `QuranTextProvider` dependency keeps resolving to the Hifz-bound
  implementation until the gate is met; nothing breaks, nothing moves twice.
- §2b is **functionally complete** for engine-side learning (F0–F4) while
  formal retirement waits for parity — the "never let both exist as live
  systems" rule (ROADMAP §2b) is honored by the freeze plus this gate, not by
  premature deletion.
- The ADR-022 filename collision (`progress-strategy-contracts` vs
  `w1-funnel-iterate`, created concurrently by two work tracks) is resolved by
  renumbering the W1 funnel ADR to ADR-026; references updated.
