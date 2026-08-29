# ADR-027: Agents may merge their own PR once required CI is green

## Context

ROADMAP §4 and `CLAUDE.md` list "no bot self-merge" among the mechanical
merge gates. That rule was written after a specific incident in the S1
rollout, recorded in ROADMAP §4: **a four-slice PR self-merged in two
minutes with CI running only post-merge.** Two things went wrong there —
the PR carried four slices instead of one, and it merged before any check
had reported. "No self-merge" was the blunt instrument that stopped both.

In practice the owner asks the agent to merge, PR by PR, and on
2026-08-29 made it standing: *"always merge."* The written rule and the
way the repo is actually run had diverged, which is worse than either
option on its own — a rule nobody follows stops being read, and the next
session wastes a turn asking permission that was granted long ago.

The two failures the original rule prevented are separable from the merge
button itself. Merging before CI reports is the real hazard; merging a
green single-slice PR is not.

## Decision

Replace the blanket prohibition with the condition that actually
prevents the recorded failure. An agent may merge a PR when **all** hold:

1. The required CI check has **completed with `success` on the PR's head
   commit** — not queued, not in progress, not merely "no failures yet".
   The conclusion must be read back from the run before merging.
2. The PR is **one slice**, per the existing gate.
3. Any verification-script evidence the slice gates on is already
   captured in `STATUS.md`, per the existing gate.
4. The owner has not asked, in the session, to hold a specific PR.

Merging without a green required check remains prohibited, and no agent
may weaken branch protection to enable a merge. If protection blocks the
merge, that is the gate working — report it rather than routing around it.

The standing authorisation is the owner's and can be withdrawn at any
time; a session where the owner says to stop merging follows that
instruction over this ADR.

## Consequences

- `CLAUDE.md`, `docs/ROADMAP.md` §4 and `docs/BRANCH_PROTECTION.md` now
  describe the same rule, so an agent reading any of them behaves the
  same way. No more "stop and ask" on a settled question.
- The lesson from the S1 incident is preserved in the part that mattered:
  CI must have *reported green*, and a PR is still one slice.
- The gate is now conditional rather than absolute, so it depends on the
  agent actually reading the check conclusion. An agent that assumes CI
  passed because it usually does has violated this ADR, not merely been
  unlucky.
- Branch protection remains the mechanical backstop and is unchanged —
  see `docs/BRANCH_PROTECTION.md` for its current (unapplied) status.
