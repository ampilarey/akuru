# ADR-022: Unlock and completion are contracts with one implementation each

**Status:** accepted (2026-08-27, 1A audit)

## Context

ROADMAP §2a and spec §42 promise pluggable strategies as a core architectural
property: `UnlockRuleEvaluator`, `CompletionRuleEvaluator`, `ProgressCalculator`
behind interfaces, chosen per course via JSON config
(`courses.completion_config`, `lessons.unlock_config` — both listed in
ROADMAP §3.4). The stated benefit is "new pedagogy = new strategy class +
registry entry, zero engine changes".

The 1A audit found none of that was built:

- `EvaluateLessonUnlockAction` hardcodes **sequential** unlock (preview always open).
- `EvaluateCourseCompletionAction` hardcodes **required lessons + required sessions**.
- Neither config column exists; nothing resolves a strategy.
- STATUS recorded "1B.5 unlock + completion evaluators (done)".

The behaviour itself is fine — sequential and count-the-required are the right
defaults, and no course today needs date-drip, prerequisite graphs, or
teacher-released unlock. The problem is that the roadmap sells an extension
point that the code does not have, and the discrepancy was never recorded.

This matters because the **second consumer is already specced and named**: the
Hifz → engine migration (ROADMAP §2b, Phase F) maps memorisation milestones onto
"completion rules". If it arrives to find completion is a hardcoded policy, the
migration either teaches the engine what a milestone is — breaking rule 6 — or
stops to do this extraction at the worst possible moment.

## Decision

1. **Introduce the two interfaces now, with the existing behaviour as the only
   implementation.** `App\Domains\Progress\Contracts\LessonUnlockEvaluator` and
   `CourseCompletionEvaluator`, implemented by the existing actions and bound in
   `ProgressServiceProvider`. Call sites in Courses resolve the **contract**,
   which also moves them from a cross-domain `Actions` reference to a
   `Contracts` one — closer to rule 3, not further.

   Normally an interface with one implementation is premature. It is justified
   here because the second implementation is named, specced, and scheduled; the
   extraction is ~40 lines of pure indirection with zero behaviour change; and
   doing it during Phase F would mix a refactor into a live-data migration.

2. **Do NOT build the config layer.** No `completion_config` / `unlock_config`
   columns, no per-course strategy resolution, no registry. Those are speculative
   until something actually needs a second policy. Phase F adds its
   implementation by swapping the binding; per-course selection arrives when a
   real course needs a different policy from another real course.

3. **`ProgressCalculator` is not extracted.** `CalculateCourseProgressAction` is
   a percentage calculation with no plausible second implementation. It stays a
   plain action; §42's third strategy is explicitly declined.

4. **Correct the record.** ROADMAP §2a and STATUS overstate what exists. Both are
   amended to say the strategies are contracts with a single implementation and
   no per-course configuration.

## Consequences

- Phase F binds `CourseCompletionEvaluator` to a Quran/Hifz implementation
  without touching Courses, Offerings or Progress internals — the property the
  roadmap advertised is now real for the case that needs it.
- Anyone reading ROADMAP §2a gets an accurate picture: extension points exist at
  the two decision points that have a known second consumer, not everywhere.
- If a third policy appears (date-drip, teacher-released), the per-course config
  layer becomes worth building. Until then it would be unused machinery.
- Block types and activity patterns remain **enums**, not a registry. Phase 1A's
  scope guard permits this ("simple internal registry — spec block types only");
  revisit if a component ever needs to be added without a deploy.
